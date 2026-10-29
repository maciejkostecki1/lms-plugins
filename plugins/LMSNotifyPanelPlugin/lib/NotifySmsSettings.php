<?php
declare(strict_types=1);
require_once __DIR__.'/NotifyDb.php';
require_once __DIR__.'/NotifySmsApi.php';
final class NotifySmsSettings
{
    private NotifyDb $db;
    public function __construct($db){$this->db=new NotifyDb($db);}
    public static function defaults(): array{return ['enabled'=>false,'sender'=>'Test','test_number'=>'','allow_manual_sms'=>false,'allow_customer_sms'=>false,'segment_rate'=>null,'callback_url'=>''];}
    private function row(): array
    {
        $row=$this->db->GetRow('SELECT payload,token,revision FROM lnp_sms_settings WHERE id=1');
        return $row?:['payload'=>'{}','token'=>'','revision'=>0];
    }
    public function settings(): array
    {
        $row=$this->row();$s=array_replace(self::defaults(),json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR));
        $s['revision']=(int)$row['revision'];$s['has_token']=$row['token']!=='';
        return $s;
    }
    public static function validate(array $input): array
    {
        $s=self::defaults();$s['enabled']=!empty($input['enabled']);$s['allow_manual_sms']=!empty($input['allow_manual_sms']);
        $s['sender']=trim((string)($input['sender']??''));
        if(!preg_match('/^[a-zA-Z0-9 .&@+_!%#*\-]{1,11}$/D',$s['sender']))throw new InvalidArgumentException('Nadawca SMS: od 1 do 11 dozwolonych znaków.');
        $s['test_number']=preg_replace('/[ ()+\-]/','',(string)($input['test_number']??''));
        if(strlen($s['test_number'])===9)$s['test_number']='48'.$s['test_number'];
        if($s['test_number']!=='' && !preg_match('/^48[0-9]{9}$/D',$s['test_number']))throw new InvalidArgumentException('Podaj polski numer testowy z prefiksem +48.');
        $rate=str_replace(',','.',trim((string)($input['segment_rate']??'')));
        if($rate!==''){
            if(!preg_match('/^\d{1,4}(?:\.\d{1,6})?$/D',$rate))throw new InvalidArgumentException('Nieprawidłowa stawka za segment SMS.');
            $s['segment_rate']=$rate;
        }
        $s['allow_customer_sms']=!empty($input['allow_customer_sms']);
        $s['callback_url']=trim((string)($input['callback_url']??''));
        if($s['callback_url']!=='' && (strlen($s['callback_url'])>500 || !preg_match('~^https://[a-zA-Z0-9.-]+/[a-zA-Z0-9/_.-]+$~D',$s['callback_url'])))throw new InvalidArgumentException('Nieprawidłowy adres HTTPS raportów SMS.');
        return $s;
    }
    public function save(array $input,int $user): void
    {
        $s=self::validate($input);$token=trim((string)($input['token']??''));
        if($token!=='' && !preg_match('/^[a-zA-Z0-9._~+\/=-]{20,4096}$/D',$token))throw new InvalidArgumentException('Nieprawidłowy format tokenu SMSAPI.');
        $this->db->BeginTrans();
        try{
            $row=$this->db->GetRow('SELECT token,revision FROM lnp_sms_settings WHERE id=1 FOR UPDATE');
            if((int)($row['revision']??0)!==(int)($input['revision']??-1))throw new RuntimeException('Ustawienia SMSAPI zmieniły się. Odśwież panel.');
            $token=$token!==''?$token:(string)($row['token']??'');
            if(!empty($input['delete_token']))$token='';
            if($s['enabled'] && $token==='')throw new InvalidArgumentException('Włączona integracja wymaga tokenu.');
            if($row)$this->db->Execute('UPDATE lnp_sms_settings SET payload=?,token=?,revision=revision+1 WHERE id=1',[json_encode($s,JSON_THROW_ON_ERROR),$token]);
            else $this->db->Execute('INSERT INTO lnp_sms_settings(id,payload,token,revision) VALUES(1,?,?,1)',[json_encode($s,JSON_THROW_ON_ERROR),$token]);
            $this->db->Execute('INSERT INTO lnp_history(created_at,customerid,action,result,details,userid) VALUES(?,0,?,?,?,?)',[time(),'smsapi_settings','saved','Zmieniono ustawienia bramki SMSAPI; automatyczne SMS: '.($s['allow_customer_sms']?'włączone':'wyłączone').'.',$user]);
            $this->db->CommitTrans();
        }catch(Throwable $e){$this->db->RollbackTrans();throw $e;}
    }
    public function send(string $number,string $body,string $id,array $options=[]): array
    {
        $s=$this->settings();$number=NotifySmsApi::normalizeNumber($number)??'';
        $test=!empty($options['lnp_test']) && $s['test_number']!=='' && hash_equals($s['test_number'],$number);
        $manual=!empty($options['lnp_manual']) && $s['allow_manual_sms'];
        $automatic=!empty($options['lnp_automatic']) && $s['allow_customer_sms'];
        if(!$test && !$manual && !$automatic)return ['status'=>MSG_ERROR,'errors'=>['SMSAPI: ten rodzaj wysyłki jest wyłączony.']];
        $row=$this->row();
        return NotifySmsApi::send($number,$body,$id,['enabled'=>$s['enabled'],'token'=>$row['token'],'sender'=>$s['sender'],'notify_url'=>$s['callback_url'],'idx'=>$options['lnp_idx']??null,'dry_run'=>$test && !empty($options['lnp_dry_run'])]);
    }
    public function test(): string
    {
        $s=$this->settings();
        if($s['test_number']==='')throw new RuntimeException('Najpierw zapisz numer testowy.');
        $result=$this->send($s['test_number'],'Test polaczenia LMS z SMSAPI.','connection'.bin2hex(random_bytes(8)),['lnp_test'=>true,'lnp_dry_run'=>true]);
        if(($result['status']??MSG_ERROR)===MSG_ERROR)throw new RuntimeException(implode(' ',$result['errors']??['Błąd połączenia SMSAPI.']));
        return 'Połączenie z SMSAPI poprawne. Nadawca zaakceptowany przez API. Test nie wysłał SMS-a.';
    }
}
