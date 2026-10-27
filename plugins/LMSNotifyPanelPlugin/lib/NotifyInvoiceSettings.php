<?php
declare(strict_types=1);
require_once __DIR__.'/NotifyDb.php';
final class NotifyInvoiceSettings
{
    public const PROFILES=[
        'notify-reminder'=>['type'=>'reminder','days'=>2,'label'=>'Przed terminem faktury','schedule'=>'Codziennie o 08:00 — aktywne'],
        'notify-overdue'=>['type'=>'debtors','days'=>3,'label'=>'Po terminie — zaległość','schedule'=>'Wyłączone w cron'],
        'notify-overdue-7'=>['type'=>'debtors','days'=>7,'label'=>'Kolejne wezwanie do płatności','schedule'=>'Wyłączone w cron'],
    ];
    private $db;private int $user;
    public function __construct($db,int $user){$this->db=new NotifyDb($db);$this->user=$user;}
    public function profiles(): array
    {
        $out=[];
        foreach(self::PROFILES as $section=>$p) {
            $p['section']=$section;$t=$p['type'];
            foreach(['days','subject','message'] as $key)$p[$key]=ConfigHelper::getConfig($section.'.'.$t.'_'.$key,$key==='days'?$p['days']:'');
            $p['sender_email']=ConfigHelper::getConfig($section.'.sender_email',ConfigHelper::getConfig($section.'.mailfrom',''));
            $p['sender_name']=ConfigHelper::getConfig($section.'.sender_name','');
            $p['enabled']=ConfigHelper::checkConfig($section.'.lnp_enabled',$t==='reminder');
            $p['schedule']=$p['enabled']?'Automatyczna wysyłka włączona':'Automatyczna wysyłka wyłączona';
            if($t==='debtors' && ConfigHelper::getConfig($section.'.lnp_scope','')!=='current_invoice')$p=self::adapt($section,$p);
            $out[]=$p;
        }
        return $out;
    }
    public static function adapt(string $section,array $p): array
    {
        if($section==='notify-reminder')return $p;
        $p['subject']=$section==='notify-overdue'?'Przypomnienie o płatności za fakturę %invoice':'Ponowne przypomnienie o fakturze %invoice';
        $p['message']="Dzień dobry %name,\n\nNie odnotowaliśmy pełnej płatności za bieżącą fakturę abonamentową %invoice.\nTermin płatności: %deadline-y-%deadline-m-%deadline-d\nKwota faktury: %value\nPozostało do zapłaty: %unpaid\n\nProsimy o wpłatę na rachunek: %bankaccount\nJeśli płatność została już wykonana, prosimy o kontakt w celu jej wyjaśnienia.\n\nNet-24h";
        return $p;
    }
    public function save(string $section,array $input): void
    {
        if(!isset(self::PROFILES[$section]))throw new RuntimeException('Nieznany szablon powiadomień.');
        $days=filter_var($input['days']??null,FILTER_VALIDATE_INT);
        if($days===false || $days<1 || $days>365)throw new RuntimeException('Liczba dni musi mieścić się w przedziale 1–365.');
        foreach(['subject','message','sender_email','sender_name'] as $key)if(!is_string($input[$key]??null)
            || strlen($input[$key])>($key==='message'?16000:250)
            || ($key!=='message' && preg_match('/[\r\n]/',$input[$key])))throw new RuntimeException('Nieprawidłowe pole wiadomości.');
        if(trim($input['subject'])===''||trim($input['message'])===''||!filter_var($input['sender_email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Uzupełnij temat, treść i poprawny adres nadawcy.');
        if(self::PROFILES[$section]['type']==='debtors') {
            $text=str_replace(['%invoice','%number','%cid','%name','%value','%unpaid','%deadline-y','%deadline-m','%deadline-d','%bankaccount'],'',$input['subject']."\n".$input['message']);
            if(preg_match('/%[a-zA-Z_]/',$text))throw new RuntimeException('Użyj symboli bieżącej faktury podanych pod formularzem.');
        }
        $type=self::PROFILES[$section]['type'];
        $vars=[$type.'_days'=>(string)$days,$type.'_subject'=>$input['subject'],$type.'_message'=>$input['message'],
            'sender_email'=>$input['sender_email'],'mailfrom'=>$input['sender_email'],'sender_name'=>$input['sender_name']];
        if($type==='debtors'){
            $vars['lnp_scope']='current_invoice';
        }
        $vars['lnp_enabled']=empty($input['enabled'])?'0':'1';
        $this->db->BeginTrans();
        try {
            foreach($vars as $var=>$value) {
                $old=$this->db->GetAll('SELECT id FROM uiconfig WHERE section=? AND var=? AND userid IS NULL AND divisionid IS NULL AND configid IS NULL FOR UPDATE',[$section,$var])?:[];
                if(count($old)>1)throw new RuntimeException('Niejednoznaczna konfiguracja '.$section.'.'.$var);
                if($old)$this->db->Execute('UPDATE uiconfig SET value=?,disabled=0 WHERE id=?',[$value,$old[0]['id']]);
                else $this->db->Execute('INSERT INTO uiconfig(section,var,value,description,disabled) VALUES(?,?,?,?,0)',[$section,$var,$value,'LMSNotifyPanelPlugin — ustawienia powiadomień']);
            }
            $this->db->Execute('INSERT INTO lnp_history(created_at,customerid,action,result,details,userid) VALUES(?,0,?,?,?,?)',
                [time(),'invoice_settings','saved','Zmieniono szablon '.$section.'; dni: '.$days.'; wysyłka: '.($vars['lnp_enabled']==='1'?'włączona':'wyłączona').'. Godzina harmonogramu bez zmian.',$this->user]);
            $this->db->CommitTrans();
        }catch(Throwable $e){$this->db->RollbackTrans();throw $e;}
    }
}
