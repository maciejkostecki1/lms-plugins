<?php
declare(strict_types=1);
require_once __DIR__.'/NotifyDb.php';
require_once __DIR__.'/NotifySmsApi.php';
final class NotifySmsDelivery
{
    private NotifyDb $db;
    public function __construct($db){$this->db=new NotifyDb($db);}
    public function begin(int $item,string $number): string
    {
        $number=NotifySmsApi::normalizeNumber($number);
        if(!$number)throw new RuntimeException('Nieprawidłowy numer SMS.');
        $idx=bin2hex(random_bytes(32));
        $created=$this->db->GetOne('INSERT INTO lnp_sms_delivery(itemid,idx,number,updated_at) VALUES(?,?,?,?) ON CONFLICT(itemid) DO NOTHING RETURNING itemid',[$item,$idx,$number,time()]);
        if(!$created)throw new RuntimeException('Próba wysyłki tego SMS-a jest już zapisana. Sprawdź historię.');
        return $idx;
    }
    public function accepted(int $item,array $result): void
    {
        $state=$result['gateway_status']??'UNKNOWN';
        if(!in_array($state,['QUEUE','ACCEPTED','SENT','DELIVERED'],true))$state='UNKNOWN';
        $external=$result['id']??null;
        if($external!==null && !preg_match('/^[a-zA-Z0-9]{1,32}$/D',$external))$external=null;
        $this->db->Execute('UPDATE lnp_sms_delivery SET externalid=?,gateway_status=?,updated_at=? WHERE itemid=? AND report_at=0',[$external,$state,time(),$item]);
    }
    public static function reports(array $input): array
    {
        $keys=['MsgId','status','idx','to','donedate'];$data=[];$count=null;
        foreach($keys as $key){
            if(!isset($input[$key]) || !is_string($input[$key]))throw new InvalidArgumentException('Nieprawidłowy raport.');
            $data[$key]=explode(',',$input[$key]);
            $count=$count??count($data[$key]);
            if(count($data[$key])!==$count || $count<1 || $count>5)throw new InvalidArgumentException('Nieprawidłowy raport.');
        }
        $states=[401=>'NOT_FOUND',402=>'EXPIRED',403=>'SENT',404=>'DELIVERED',405=>'UNDELIVERED',406=>'FAILED',407=>'REJECTED',408=>'UNKNOWN',409=>'QUEUE',410=>'ACCEPTED',411=>'RENEWAL',412=>'STOP'];
        $reports=[];
        for($i=0;$i<$count;$i++){
            $code=$data['status'][$i];$date=$data['donedate'][$i];$number=NotifySmsApi::normalizeNumber($data['to'][$i]);
            if(!preg_match('/^[a-zA-Z0-9]{1,32}$/D',$data['MsgId'][$i]) || !preg_match('/^[a-f0-9]{64}$/D',$data['idx'][$i]) || !isset($states[$code]) || !$number || !ctype_digit($date) || (int)$date>time()+300)throw new InvalidArgumentException('Nieprawidłowy raport.');
            $reports[]=['id'=>$data['MsgId'][$i],'idx'=>$data['idx'][$i],'number'=>$number,'state'=>$states[$code],'date'=>(int)$date];
        }
        return $reports;
    }
    public function pendingNotice(array $c,int $event,array $items,string $origin): void
    {
        if(empty($c['debt_cycle']) || !in_array($origin,['manual','automatic'],true))return;
        $this->db->Execute('INSERT INTO lnp_sms_notices(eventid,customerid,cycle_key,origin,items) VALUES(?,?,?,?,?) ON CONFLICT(eventid) DO NOTHING',[$event,(int)$c['id'],$c['debt_cycle'],$origin,json_encode($items,JSON_THROW_ON_ERROR)]);
    }
    public function reconcile(int $customer,$panel): void
    {
        require_once __DIR__.'/NotifyBlockNotice.php';
        foreach($this->db->GetAll('SELECT * FROM lnp_sms_notices WHERE customerid=? AND finished=0 ORDER BY eventid',[$customer])?:[] as $intent){
            $s=$panel->settings();$c=$panel->inspect($customer,$s);
            if(!$c || $c['debt']<=0.009 || $c['debt_cycle']!==$intent['cycle_key']){
                $this->db->Execute('UPDATE lnp_sms_notices SET finished=1 WHERE eventid=?',[$intent['eventid']]);continue;
            }
            if($c['excluded'] || $c['state']==='review' || (int)$s['block_after_days']===0)continue;
            $items=json_decode($intent['items'],true,512,JSON_THROW_ON_ERROR);if(!$items)continue;
            $all=true;foreach($items as $item)if(!in_array((int)$this->db->GetOne('SELECT status FROM messageitems WHERE id=?',[(int)$item]),[MSG_SENT,MSG_DELIVERED],true))$all=false;
            if(!$all)continue;
            // Use the same existing warning rule, starting conservatively at confirmation time.
            (new NotifyBlockNotice($this->db))->record($c,$s,(int)$intent['eventid'],$items,time(),$intent['origin']);
            $this->db->Execute('UPDATE lnp_sms_notices SET finished=1 WHERE eventid=?',[$intent['eventid']]);
        }
    }
    public function receive(array $reports,$panel=null): void
    {
        $this->db->BeginTrans();
        try{
            foreach($reports as $r){
                $row=$this->db->GetRow('SELECT d.*,m.customerid FROM lnp_sms_delivery d JOIN messageitems m ON m.id=d.itemid WHERE d.externalid=? AND d.idx=? AND d.number=? FOR UPDATE OF d,m',[$r['id'],$r['idx'],$r['number']]);
                if(!$row)throw new RuntimeException('Raport nie pasuje do zapisanej wysyłki.');
                $lock=$this->db->GetOne('SELECT pg_try_advisory_xact_lock(762319,?)',[(int)$row['customerid']]);
                if(!in_array($lock,[true,1,'1','t'],true))throw new RuntimeException('Operacja klienta trwa. Ponów raport.');
                if($r['date']<(int)$row['report_at'] || $row['gateway_status']===$r['state'])continue;
                // A terminal delivery/failure must not regress on a delayed duplicate callback.
                if(in_array($row['gateway_status'],['DELIVERED','EXPIRED','UNDELIVERED','FAILED','REJECTED','STOP'],true))continue;
                if($row['gateway_status']==='SENT' && in_array($r['state'],['QUEUE','ACCEPTED','RENEWAL'],true))continue;
                $status=$r['state']==='DELIVERED'?MSG_DELIVERED:($r['state']==='SENT'?MSG_SENT:(in_array($r['state'],['EXPIRED','UNDELIVERED','FAILED','REJECTED','STOP'],true)?MSG_ERROR:MSG_NEW));
                $this->db->Execute('UPDATE lnp_sms_delivery SET gateway_status=?,updated_at=?,report_at=? WHERE itemid=?',[$r['state'],time(),$r['date'],$row['itemid']]);
                $this->db->Execute('UPDATE messageitems SET status=?,lastdate=?NOW?,error=? WHERE id=?',[$status,$status===MSG_ERROR?'SMSAPI: '.$r['state']:null,$row['itemid']]);
                if($panel!==null)$this->reconcile((int)$row['customerid'],$panel);
            }
            $this->db->CommitTrans();
        }catch(Throwable $e){$this->db->RollbackTrans();throw $e;}
    }
    public function history(): array
    {
        return $this->db->GetAll("SELECT d.itemid,d.number,d.externalid,d.gateway_status,d.updated_at,m.customerid,
            trim(COALESCE(c.lastname,'') || ' ' || COALESCE(c.name,'')) AS customername
            FROM lnp_sms_delivery d JOIN messageitems m ON m.id=d.itemid
            LEFT JOIN customers c ON c.id=m.customerid ORDER BY d.updated_at DESC,d.itemid DESC LIMIT 100")?:[];
    }
}
