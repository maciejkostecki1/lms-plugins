<?php
declare(strict_types=1);
require_once __DIR__.'/NotifyDb.php';
final class NotifyInvoiceSchedule
{
    private NotifyDb $db;
    public function __construct($db){$this->db=new NotifyDb($db);}
    public static function valid(string $value): bool{return (bool)preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$value);}
    public function time(): string
    {
        $rows=$this->db->GetAll('SELECT value FROM uiconfig WHERE section=? AND var=? AND userid IS NULL AND divisionid IS NULL AND configid IS NULL AND disabled=0',['notify-panel','invoice_time'])?:[];
        if(count($rows)>1)throw new RuntimeException('Niejednoznaczna godzina wysyłki.');
        $value=$rows?(string)$rows[0]['value']:'08:00';
        if(!self::valid($value))throw new RuntimeException('Nieprawidłowa godzina wysyłki.');
        return $value;
    }
    public static function atTime(string $value,int $now): bool
    {
        return self::valid($value) && (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('H:i')===$value;
    }
    public function due(int $now): bool{return self::atTime($this->time(),$now);}
    public function save(string $value,int $user): void
    {
        if(!self::valid($value))throw new RuntimeException('Podaj godzinę w formacie GG:MM.');
        $this->db->BeginTrans();
        try{
            $rows=$this->db->GetAll('SELECT id FROM uiconfig WHERE section=? AND var=? AND userid IS NULL AND divisionid IS NULL AND configid IS NULL FOR UPDATE',['notify-panel','invoice_time'])?:[];
            if(count($rows)>1)throw new RuntimeException('Niejednoznaczna godzina wysyłki.');
            if($rows)$this->db->Execute('UPDATE uiconfig SET value=?,disabled=0 WHERE id=?',[$value,$rows[0]['id']]);
            else $this->db->Execute('INSERT INTO uiconfig(section,var,value,description,disabled) VALUES(?,?,?,?,0)',['notify-panel','invoice_time',$value,'LMSNotifyPanelPlugin — wspólna godzina powiadomień']);
            $this->db->Execute('INSERT INTO lnp_history(created_at,customerid,action,result,details,userid) VALUES(?,0,?,?,?,?)',[time(),'invoice_schedule','saved','Godzina wszystkich powiadomień o fakturach: '.$value.' (Europe/Warsaw).',$user]);
            $this->db->CommitTrans();
        }catch(Throwable $e){$this->db->RollbackTrans();throw $e;}
    }
    public function reminderOnce(int $now,callable $send): int
    {
        $locked=$this->db->GetOne('SELECT pg_try_advisory_lock(762319,0)');
        if(!in_array($locked,[true,1,'1','t'],true))return 0;
        try{
            $day=(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('Ymd');
            $action='reminder_day_'.$day;
            if($this->db->GetOne('SELECT 1 FROM lnp_history WHERE customerid=0 AND action=?',[$action]))return 0;
            // Durable claim precedes delivery. A failed or interrupted run needs manual review.
            $event=$this->db->GetOne('INSERT INTO lnp_history(created_at,customerid,action,result,details,userid) VALUES(?,0,?,?,?,0) RETURNING id',[$now,$action,'pending','Rozpoczęto dzienny przebieg przypomnienia przed terminem.']);
            $result=$send();
            $this->db->Execute('UPDATE lnp_history SET result=?,details=? WHERE id=?',[$result===0?'sent':'error','Przebieg lms-notify zakończony, kod: '.$result.'. Szczegóły w logu przypomnień.',$event]);
            return $result;
        }finally{$this->db->GetOne('SELECT pg_advisory_unlock(762319,0)');}
    }
}
