<?php
declare(strict_types=1);
require_once __DIR__.'/NotifyDb.php';
final class NotifyBlockNotice
{
    private NotifyDb $db;
    public function __construct($db){$this->db=$db instanceof NotifyDb?$db:new NotifyDb($db);}
    public static function evaluate(?array $notice,string $cycle,float $debt,int $days,int $now,array $statuses): array
    {
        $result=['ready'=>false,'sent_at'=>0,'due_at'=>0,'reason'=>'Brak skutecznie wysłanego ostrzeżenia dla bieżącego zadłużenia.'];
        if($debt<=0.009){$result['reason']='Brak długu abonamentowego.';return $result;}
        if($days===0){$result['ready']=true;$result['reason']='Odliczanie wyłączone — obowiązuje próg zadłużenia.';return $result;}
        if(!$notice || !in_array($notice['origin']??'legacy',['automatic','manual'],true) || $cycle==='' || $notice['cycle_key']!==$cycle)return $result;
        $items=json_decode($notice['source_items'],true);
        if(!is_array($items)||!$items)return $result;
        foreach($items as $item)if(!in_array((int)($statuses[(int)$item]??0),[2,5],true)){
            $result['reason']='Wynik ostrzeżenia wymaga sprawdzenia — brak potwierdzonej wysyłki lub zwrot wiadomości.';return $result;
        }
        $sent=(int)$notice['sent_at'];if($sent<=0 || $sent>$now)return $result;
        $result['sent_at']=$sent;$result['due_at']=$sent+$days*86400;
        $result['ready']=$now>=$result['due_at'];
        $result['reason']=$result['ready']?'Termin od ostrzeżenia upłynął.':'Oczekiwanie na upływ '.$days.' pełnych dób od wysyłki ostrzeżenia.';
        return $result;
    }
    public function status(array $customer,array $settings,?int $now=null): array
    {
        return $this->statuses([$customer],$settings,$now)[(int)$customer['id']];
    }
    public function statuses(array $customers,array $settings,?int $now=null): array
    {
        if(!$customers)return [];
        $ids=array_map('intval',array_column($customers,'id'));$notices=[];$items=[];
        foreach($this->db->GetAll('SELECT * FROM lnp_block_notices WHERE customerid IN ?',[$ids])?:[] as $row){
            $notices[(int)$row['customerid']][$row['origin']]=$row;
            foreach(json_decode($row['source_items'],true)?:[] as $item)$items[]=(int)$item;
        }
        $statuses=[];
        if($items)foreach($this->db->GetAll('SELECT id,status FROM messageitems WHERE id IN ?',[array_values(array_unique($items))])?:[] as $row)$statuses[(int)$row['id']]=(int)$row['status'];
        $out=[];foreach($customers as $c){
            $id=(int)$c['id'];$days=(int)$settings['block_after_days'];$cycle=(string)($c['debt_cycle']??'');
            $best=self::evaluate(null,$cycle,(float)$c['debt'],$days,$now??time(),$statuses);
            $manualReady=false;
            foreach(['automatic','manual'] as $origin){
                $candidate=self::evaluate($notices[$id][$origin]??null,$cycle,(float)$c['debt'],$days,$now??time(),$statuses);
                if($origin==='manual')$manualReady=$days>0 && $candidate['ready'];
                if($candidate['sent_at']>0 && ($best['sent_at']===0 || $candidate['sent_at']<$best['sent_at']))$best=$candidate;
            }
            $best['manual_ready']=$manualReady;$out[$id]=$best;
        }
        return $out;
    }
    // Caller holds the shared customer lock. Repeated reminders preserve the first valid warning.
    public function record(array $customer,array $settings,int $event,array $items,int $sentAt,string $origin='automatic'): void
    {
        if(empty($customer['debt_cycle']) || $customer['debt']<=0.009 || !$items)return;
        $recordSettings=$settings;$recordSettings['block_after_days']=max(7,(int)$settings['block_after_days']);
        $notice=$this->db->GetRow('SELECT * FROM lnp_block_notices WHERE customerid=? AND origin=?',[(int)$customer['id'],$origin]);
        $itemsStatus=[];foreach(json_decode($notice['source_items']??'[]',true)?:[] as $item){$itemsStatus[(int)$item]=(int)$this->db->GetOne('SELECT status FROM messageitems WHERE id=?',[(int)$item]);}
        $existing=self::evaluate($notice?:null,(string)$customer['debt_cycle'],(float)$customer['debt'],(int)$recordSettings['block_after_days'],$sentAt,$itemsStatus);
        if($existing['sent_at']>0)return;
        $this->db->Execute('INSERT INTO lnp_block_notices(customerid,cycle_key,sent_at,source_historyid,source_items,origin) VALUES(?,?,?,?,?,?)
            ON CONFLICT(customerid,origin) DO UPDATE SET cycle_key=EXCLUDED.cycle_key,sent_at=EXCLUDED.sent_at,source_historyid=EXCLUDED.source_historyid,source_items=EXCLUDED.source_items,origin=EXCLUDED.origin',
            [(int)$customer['id'],$customer['debt_cycle'],$sentAt,$event,json_encode(array_map('intval',$items),JSON_THROW_ON_ERROR),$origin]);
    }
}
