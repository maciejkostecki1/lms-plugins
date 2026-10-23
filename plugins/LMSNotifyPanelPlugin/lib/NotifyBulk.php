<?php
declare(strict_types=1);
require_once __DIR__.'/NotifyDb.php';
final class NotifyBulk
{
    private NotifyDb $db;private $panel;private int $user;
    public function __construct($db,$panel,int $user){$this->db=new NotifyDb($db);$this->panel=$panel;$this->user=$user;}
    public function start(array $ids,int $revision,string $mode='plain',array $hashes=[],bool $startCountdown=false): int
    {
        if($this->panel->settings()['revision']!==$revision)throw new RuntimeException('Ustawienia zmieniły się. Przygotuj nowy podgląd.');
        $ids=array_values(array_unique(array_map('intval',$ids)));
        if(!$ids || count($ids)>100000 || min($ids)<1)throw new RuntimeException('Brak poprawnych odbiorców.');
        if(!in_array($mode,['plain','history'],true))throw new RuntimeException('Nieznany rodzaj wysyłki.');
        if($mode==='history')foreach($ids as $customer){
            if(!is_string($hashes[$customer]??null) || !preg_match('/^[a-f0-9]{64}$/D',$hashes[$customer]))throw new RuntimeException('Brak podglądu historii jednego z klientów.');
        }
        $this->db->BeginTrans();
        try{
            $id=(int)$this->db->GetOne('INSERT INTO lnp_bulk_jobs(created_at,userid,revision,status,total,mode,start_countdown) VALUES(?,?,?,?,?,?,?) RETURNING id',[time(),$this->user,$revision,'running',count($ids),$mode,$startCountdown?1:0]);
            foreach($ids as $customer)$this->db->Execute('INSERT INTO lnp_bulk_items(jobid,customerid,status,history_hash) VALUES(?,?,?,?)',[$id,$customer,'queued',$mode==='history'?$hashes[$customer]:null]);
            $this->db->Execute('INSERT INTO lnp_history(created_at,customerid,action,result,details,userid) VALUES(?,0,?,?,?,?)',[time(),'bulk_notify','confirmed','Potwierdzono ręczną wysyłkę'.($mode==='history'?' z osobnymi historiami płatności':'').' #'.$id.' do '.count($ids).' klientów.',$this->user]);
            $this->db->CommitTrans();return $id;
        }catch(Throwable $e){$this->db->RollbackTrans();throw $e;}
    }
    public function status(int $id): array
    {
        $job=$this->db->GetRow('SELECT * FROM lnp_bulk_jobs WHERE id=? AND userid=?',[$id,$this->user]);
        if(!$job)throw new RuntimeException('Brak tej wysyłki dla bieżącego operatora.');
        $counts=['queued'=>0,'working'=>0,'done'=>0,'skipped'=>0,'error'=>0];
        foreach($this->db->GetAll('SELECT status,count(*) AS total FROM lnp_bulk_items WHERE jobid=? GROUP BY status',[$id])?:[] as $r)$counts[$r['status']]=(int)$r['total'];
        $job['counts']=$counts;
        $job['problems']=$this->db->GetAll('SELECT customerid,status,details FROM lnp_bulk_items WHERE jobid=? AND status IN (?,?,?) ORDER BY customerid LIMIT 50',[$id,'working','skipped','error'])?:[];
        return $job;
    }
    public function recent(): array
    {
        return $this->db->GetAll('SELECT id,created_at,status,total FROM lnp_bulk_jobs WHERE userid=? ORDER BY id DESC LIMIT 5',[$this->user])?:[];
    }
    public function process(int $id,bool $cancel=false): array
    {
        $this->status($id);
        $lock=$this->db->GetOne('SELECT pg_try_advisory_lock(762320,?)',[$id]);
        if(!in_array($lock,[true,1,'1','t'],true))throw new RuntimeException('Ta wysyłka jest już przetwarzana.');
        try{
            $job=$this->status($id);
            if($job['status']!=='running')return $job;
            if($cancel || $this->panel->settings()['revision']!==(int)$job['revision']){
                $this->db->Execute('UPDATE lnp_bulk_items SET status=?,details=? WHERE jobid=? AND status=?',['skipped',$cancel?'Anulowano przez operatora.':'Ustawienia zmieniły się — wymagany nowy podgląd.',$id,'queued']);
                $this->db->Execute('UPDATE lnp_bulk_jobs SET status=? WHERE id=?',[$cancel?'cancelled':'stopped',$id]);
                return $this->status($id);
            }
            $rows=$this->db->GetAll('SELECT customerid,history_hash FROM lnp_bulk_items WHERE jobid=? AND status=? ORDER BY customerid LIMIT 3',[$id,'queued'])?:[];
            foreach($rows as $r){
                $customer=(int)$r['customerid'];
                // Persist before calling the sender; interrupted items are never retried automatically.
                $this->db->Execute('UPDATE lnp_bulk_items SET status=?,details=? WHERE jobid=? AND customerid=?',['working','Wynik nieznany — w razie przerwania sprawdź historię wiadomości LMS.',$id,$customer]);
                try{
                    $result=$this->panel->act($customer,$job['mode']==='history'?'notify_history':'notify_manual',(int)$job['revision'],$r['history_hash'],false,(bool)$job['start_countdown']);
                    $state=strpos($result,'Kanały przyjęte do wysyłki: ')===0?'done':(strpos($result,'Częściowy lub nieznany')===0?'error':'skipped');
                }catch(Throwable $e){$state='error';$result=$e->getMessage();}
                $this->db->Execute('UPDATE lnp_bulk_items SET status=?,details=? WHERE jobid=? AND customerid=?',[$state,$result,$id,$customer]);
            }
            if(!$this->db->GetOne('SELECT 1 FROM lnp_bulk_items WHERE jobid=? AND status=? LIMIT 1',[$id,'queued'])){
                $this->db->Execute('UPDATE lnp_bulk_jobs SET status=? WHERE id=?',['completed',$id]);
                $this->db->Execute('INSERT INTO lnp_history(created_at,customerid,action,result,details,userid) VALUES(?,0,?,?,?,?)',[time(),'bulk_notify','completed','Zakończono przebieg ręcznej wysyłki #'.$id.'. Wyniki w podglądzie wysyłki i historii wiadomości.',$this->user]);
            }
            return $this->status($id);
        }finally{$this->db->GetOne('SELECT pg_advisory_unlock(762320,?)',[$id]);}
    }
}
