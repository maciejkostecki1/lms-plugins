<?php
declare(strict_types=1);
final class NotifyBilling
{
    private $db;private $lms;
    public function __construct($db,$lms){$this->db=$db;$this->lms=$lms;}
    public function assertZeroRate(int $division): void
    {
        $rate=ConfigHelper::getConfig('finances.suspension_percentage',0);
        if(!is_numeric($rate)||(float)$rate!==0.0)throw new RuntimeException('Blokada przerwana: opłaty przy zawieszeniu w LMS muszą wynosić 0%.');
        $rates=$this->db->GetCol('SELECT value FROM uiconfig WHERE section=? AND var=? AND disabled=0 AND userid IS NULL AND (divisionid IS NULL OR divisionid=?)',['finances','suspension_percentage',$division])?:[];
        foreach($rates as $v)if(!is_numeric($v)||(float)$v!==0.0)throw new RuntimeException('Blokada przerwana: konfiguracja zawieszenia nie zapewnia 0% opłat.');
    }
    public function pause(int $id,int $division): string
    {
        $this->assertZeroRate($division);$now=time();$count=0;
        $rows=$this->db->GetAll('SELECT id,tariffid,liabilityid,suspended FROM assignments WHERE customerid=? AND commited=1 AND period<>? AND (tariffid IS NOT NULL OR liabilityid IS NOT NULL) AND datefrom<=? AND (dateto=0 OR dateto>?) ORDER BY id FOR UPDATE',[$id,DISPOSABLE,$now,$now])?:[];
        $all=$this->db->checked(function()use($id){return $this->lms->GetCustomerAssignments($id,false,true);})?:[];
        $byId=[];foreach($all as $a)$byId[(int)$a['id']]=$a;
        $periods=[];foreach($this->db->GetAll('SELECT t.id,t.period FROM assignments a JOIN tariffs t ON t.id=a.tariffid WHERE a.customerid=?',[$id])?:[] as $p)$periods[(int)$p['id']]=$p['period'];
        foreach($rows as $row){
            if((int)$row['suspended']!==0)continue;
            $a=$byId[(int)$row['id']]??null;
            if(!$a)throw new RuntimeException('Brak kwoty zobowiązania do zawieszenia.');
            $fee=NotifyFees::monthly([$a],$periods,$now);
            if($fee['reason']||count($fee['rows'])!==1||$fee['rows'][0]['currency']!=='PLN')throw new RuntimeException('Nie można potwierdzić kwoty cyklicznego zobowiązania.');
            if($this->db->GetOne('SELECT 1 FROM lnp_billing WHERE assignmentid=?',[$row['id']]))throw new RuntimeException('Stan wcześniej zawieszonego zobowiązania zmienił się. Wymagana weryfikacja.');
            $this->db->checked(function()use($row){return $this->lms->SuspendAssignment((int)$row['id'],true);});
            if((int)$this->db->GetOne('SELECT suspended FROM assignments WHERE id=?',[$row['id']])!==1)throw new RuntimeException('Nie udało się zatrzymać naliczania.');
            $this->db->Execute('INSERT INTO lnp_billing(assignmentid,customerid,created_at,monthly_base,source_tariffid,source_liabilityid) VALUES(?,?,?,?,?,?)',[$row['id'],$id,$now,$fee['rows'][0]['sum'],$row['tariffid'],$row['liabilityid']]);++$count;
        }
        return 'Wstrzymano naliczanie cykliczne (0%): '.$count.' zobowiązań. Opłaty jednorazowe bez zmian.';
    }
    public function resume(int $id): string
    {
        if($this->db->GetOne('SELECT 1 FROM assignments WHERE customerid=? AND tariffid IS NULL AND liabilityid IS NULL AND datefrom<=? AND (dateto=0 OR dateto>?) LIMIT 1',[$id,time(),time()]))throw new RuntimeException('Klient ma osobne zawieszenie wszystkich zobowiązań. Nie odblokowano urządzeń.');
        $owned=$this->db->GetAll('SELECT * FROM lnp_billing WHERE customerid=? ORDER BY assignmentid FOR UPDATE',[$id])?:[];$count=0;
        foreach($owned as $o){
            $a=$this->db->GetRow('SELECT customerid,tariffid,liabilityid,suspended FROM assignments WHERE id=? FOR UPDATE',[$o['assignmentid']]);
            if(!$a||(int)$a['customerid']!==$id||$a['tariffid']!==$o['source_tariffid']||$a['liabilityid']!==$o['source_liabilityid'])throw new RuntimeException('Zobowiązanie zmieniono poza wtyczką. Wymagana weryfikacja.');
            if((int)$a['suspended']===1){
                $this->db->checked(function()use($o){return $this->lms->SuspendAssignment((int)$o['assignmentid'],false);});
                if((int)$this->db->GetOne('SELECT suspended FROM assignments WHERE id=?',[$o['assignmentid']])!==0)throw new RuntimeException('Nie udało się wznowić naliczania.');
                ++$count;
            }
            $this->db->Execute('DELETE FROM lnp_billing WHERE assignmentid=?',[$o['assignmentid']]);
        }
        return 'Wznowiono naliczanie zobowiązań zawieszonych przez wtyczkę: '.$count.'.';
    }
}
