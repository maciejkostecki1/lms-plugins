<?php
declare(strict_types=1);
require_once __DIR__ . '/NotifyPolicy.php';
require_once __DIR__ . '/NotifyDb.php';
require_once __DIR__ . '/NotifyFees.php';
require_once __DIR__ . '/NotifyBilling.php';
require_once __DIR__ . '/NotifyDebt.php';
require_once __DIR__ . '/NotifyCustomerDetails.php';
require_once __DIR__ . '/NotifySnapshot.php';
require_once __DIR__.'/NotifyPaymentHistory.php';
require_once __DIR__.'/NotifyBlockNotice.php';
final class NotifyPanel
{
    private $db;
    private $lms;
    private int $user;
    public function __construct($db, $lms, int $user = 0) { $this->db=new NotifyDb($db); $this->lms=$lms; $this->user=$user; }
    public function settings(): array
    {
        $row=$this->db->GetRow('SELECT payload, revision FROM lnp_settings WHERE id=1');
        if (!$row) throw new RuntimeException('Najpierw zainstaluj tabele wtyczki.');
        $s=NotifyPolicy::validate(json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR));
        $s['revision']=(int)$row['revision'];
        return $s;
    }
    public function save(array $input, int $revision): void
    {
        $s=NotifyPolicy::validate($input);
        NotifyPolicy::manualTokens($s['subject']."\n".$s['body']);
        if ($s['auto_notify'] && in_array('mail',$s['channels']) && $s['sender_email']==='') {
            throw new RuntimeException('Przed włączeniem wysyłki ustaw adres nadawcy.');
        }
        $this->db->BeginTrans();
        try {
            $old=$this->db->GetRow('SELECT revision FROM lnp_settings WHERE id=1 FOR UPDATE');
            if ((int)$old['revision']!==$revision) throw new RuntimeException('Ustawienia zmienił inny operator. Odśwież panel.');
            $this->db->Execute('UPDATE lnp_settings SET payload=?, revision=revision+1 WHERE id=1', [json_encode($s,JSON_THROW_ON_ERROR)]);
            $this->history(0,'settings','saved','Zmieniono ustawienia; progi '.$s['notify_percent'].'% / '.$s['block_percent'].'% / '.$s['suspend_percent'].'%.');
            $this->db->CommitTrans();
        } catch (Throwable $e) { $this->db->RollbackTrans(); throw $e; }
    }
    private function history(int $id,string $action,string $result,string $details): int
    {
        $this->db->Execute('INSERT INTO lnp_history(created_at,customerid,action,result,details,userid) VALUES(?,?,?,?,?,?)',
            [time(),$id,$action,$result,$details,$this->user]);
        return (int)$this->db->GetLastInsertID('lnp_history');
    }
    public function inspect(int $id, ?array $settings=null, ?array $listData=null): ?array
    {
        $s=$settings??$this->settings();
        // Consent fields are exposed by this LMS view, not by the customers table.
        $c=$listData!==null?$listData['customer']:$this->db->GetRow('SELECT id,name,lastname,status,deleted,cutoffstop,mailingnotice,smsnotice FROM customeraddressview WHERE id=?',[$id]);
        if (!$c || $c['deleted'] || (int)$c['status']!==CSTATUS_CONNECTED) return null;
        $c['customername']=trim($c['lastname'].' '.$c['name']);
        $c['balance']=$listData!==null?(float)$listData['balance']:(float)$this->db->checked(function() use($id) {return $this->lms->getCustomerBalance($id,null,false);});
        $entries=$listData!==null?$listData['entries']:($this->db->GetAll('SELECT ca.id AS ledger_id,ca.value*ca.currencyvalue AS amount,ca.docid,ca.itemid,ic.period,d.cdate AS invoice_cdate,d.paytime AS invoice_paytime
            FROM cash ca LEFT JOIN invoicecontents ic ON ic.docid=ca.docid AND ic.itemid=ca.itemid LEFT JOIN documents d ON d.id=ca.docid
            WHERE ca.customerid=? AND ca.time<? ORDER BY ca.time,ca.id',[$id,time()])?:[]);
        $debt=NotifyDebt::split($entries);
        $assignments=$listData!==null?$listData['assignments']:($this->db->checked(function() use($id) {return $this->lms->GetCustomerAssignments($id,false,true);})?:[]);
        $periods=$listData['periods']??[];
        if($listData===null){$periodRows=$this->db->GetAll('SELECT DISTINCT t.id,t.period FROM assignments a JOIN tariffs t ON t.id=a.tariffid WHERE a.customerid=?',[$id])?:[];foreach($periodRows as $p)$periods[(int)$p['id']]=$p['period'];}
        $fees=NotifyFees::monthly($assignments,$periods,time());
        $preserved=$listData!==null?$listData['preserved']:$this->db->GetOne('SELECT SUM(b.monthly_base) FROM lnp_billing b JOIN assignments a ON a.id=b.assignmentid
            WHERE b.customerid=? AND a.customerid=b.customerid AND a.suspended=1 AND a.commited=1
            AND a.tariffid IS NOT DISTINCT FROM b.source_tariffid AND a.liabilityid IS NOT DISTINCT FROM b.source_liabilityid
            AND a.datefrom<=? AND (a.dateto=0 OR a.dateto>?)',[$id,time(),time()]);
        if($preserved!==null)$fees['rows'][]=['currency'=>'PLN','sum'=>(float)$preserved];
        $c=array_merge($c,NotifyPolicy::decision(-$debt['overdue_recurring'],$fees['rows'],$s));
        $c['debt']=$debt['recurring'];$c['overdue_debt']=$debt['overdue_recurring'];
        $c['debt_cycle']=$debt['cycle_key'];
        $c['oneoff_debt']=$debt['oneoff'];$c['unknown_debt']=$debt['unknown'];
        if(abs($debt['balance']-$c['balance'])>0.01 || $debt['unknown']>0) {
            $c['state']='review';$c['reason']='Nierozpoznane obciążenia lub niezgodność salda — ręczna weryfikacja.';
        }
        if($debt['unknown_due']>0){$c['state']='review';$c['reason']='Brak rozpoznanego terminu płatności opłat abonamentowych — ręczna weryfikacja.';}
        if($fees['reason']) { $c['state']='review'; $c['reason']=$fees['reason']; }
        $ex=$listData!==null?$listData['exception']:$this->db->GetRow('SELECT until_time,reason FROM lnp_exclusions WHERE customerid=? AND until_time>?',[$id,time()]);
        $c['excluded']=!empty($ex) || (int)$c['cutoffstop']>=time();
        $c['panel_exception']=!empty($ex);
        $c['lms_protected']=(int)$c['cutoffstop']>=time();
        $c['exception']=$ex ? $ex['reason'] : ((int)$c['cutoffstop']>=time()?'Ochrona cutoffstop w LMS':'');
        $c['owned_blocks']=$listData!==null?(int)$listData['owned_blocks']:(int)$this->db->GetOne('SELECT count(*) FROM lnp_blocks WHERE customerid=?',[$id]);
        $c['owned_billing']=$listData!==null?(int)$listData['owned_billing']:(int)$this->db->GetOne('SELECT count(*) FROM lnp_billing WHERE customerid=?',[$id]);
        return $c;
    }
    public function customerDetails(array $c): array
    {
        $id=(int)$c['id'];
        $details=$this->db->GetRow('SELECT notes,info FROM customers WHERE id=?',[$id])?:[];
        $c['customer_info']=NotifyCustomerDetails::noteText($details['info']??'');
        $c['customer_notes']=NotifyCustomerDetails::noteText($details['notes']??'');
        $note=$this->db->GetRow('SELECT dt,message FROM customernotes WHERE customerid=? ORDER BY dt DESC,id DESC LIMIT 1',[$id]);
        $c['last_note']=$note?NotifyCustomerDetails::noteText($note['message']):'';
        $c['last_note_time']=$note?(int)$note['dt']:0;
        $emailContacts=$this->db->GetAll('SELECT contact,type FROM customercontacts WHERE customerid=? AND (type & ?)=?',[$id,CONTACT_EMAIL,CONTACT_EMAIL])?:[];
        $c['email_status']=NotifyCustomerDetails::emailStatus($emailContacts,!empty($c['mailingnotice']));
        return $c;
    }
    public function customerListDetails(array $rows): array
    {
        if(!$rows)return [];
        $ids=array_map('intval',array_column($rows,'id'));$events=[];
        $blockStatuses=(new NotifyBlockNotice($this->db))->statuses($rows,$this->settings());
        foreach($this->db->GetAll('SELECT DISTINCT ON (customerid) customerid,created_at,action,result,details FROM lnp_history
            WHERE customerid IN ? AND (action IN (?,?) OR action LIKE ?) AND result IN (?,?,?)
            ORDER BY customerid,created_at DESC,id DESC',[$ids,'notify_mail','notify_sms','invoice_notify-%','sent','pending','error'])?:[] as $event)$events[(int)$event['customerid']]=$event;
        foreach($rows as &$c){
            $c['block_notice']=$blockStatuses[(int)$c['id']];
            $c=$this->customerDetails($c);$event=$events[(int)$c['id']]??[];
            $c['notification_status']=NotifyCustomerDetails::notificationStatus($event);
            $c['notification_time']=(int)($event['created_at']??0);
            $c['notification_result']=$event['result']??'';
        }unset($c);
        return $rows;
    }
    public function customers(int $after=0,int $limit=50): array
    {
        $s=$this->settings();
        $ids=$this->db->GetCol('SELECT c.id FROM customers c WHERE c.id>? AND c.deleted=0 AND c.status=?
            AND (EXISTS(SELECT 1 FROM cash WHERE customerid=c.id) OR EXISTS(SELECT 1 FROM lnp_blocks WHERE customerid=c.id))
            ORDER BY c.id LIMIT '.max(1,min(200,$limit)),[$after,CSTATUS_CONNECTED])?:[];
        $rows=[];
        foreach($ids as $id) { $c=$this->inspect((int)$id,$s); if($c) $rows[]=$c; }
        return $rows;
    }
    public function scheduledActions(array $c,array $s): array
    {
        if($c['excluded'] || $c['state']==='review')return [];
        $actions=[];
        if($s['auto_notify'] && in_array($c['state'],['notify','block'],true))$actions[]='notify';
        $gate=$this->blockNoticeStatus($c,$s);
        $blockEnabled=$s['auto_block'] || !empty($gate['manual_ready']);
        if($blockEnabled && (NotifyPolicy::internetBlockDue($c,$s,$gate) || ($c['owned_blocks'] && $c['debt']>0)))$actions[]='block';
        if($s['auto_unblock'] && $c['debt']<=0 && $c['owned_blocks'])$actions[]='unblock';
        return $actions;
    }
    public function historyList(): array
    {
        return $this->db->GetAll('SELECT * FROM lnp_history ORDER BY id DESC LIMIT 100')?:[];
    }
    public function customerSnapshot(): array
    {
        $s=$this->settings();
        return (new NotifySnapshot($this->db))->build($this,$s);
    }
    public function exceptionList(): array
    {
        return $this->db->GetAll('SELECT e.*,c.lastname,c.name FROM lnp_exclusions e
            LEFT JOIN customers c ON c.id=e.customerid WHERE e.until_time>? ORDER BY e.until_time,e.customerid',[time()])?:[];
    }
    public function exclude(int $id,int $days,string $reason): void
    {
        if ($id<1 || $days<0 || $days>365 || strlen($reason)>500 || ($days && trim($reason)==='')) throw new RuntimeException('Podaj ID, powód i okres 1–365 dni (0 usuwa wyjątek).');
        if (!$this->db->GetOne('SELECT 1 FROM customers WHERE id=? AND deleted=0',[$id])) throw new RuntimeException('Nie ma takiego klienta.');
        $lock=$this->db->GetOne('SELECT pg_try_advisory_lock(762319, ?)',[$id]);
        if (!in_array($lock,[true,1,'1','t'],true)) throw new RuntimeException('Operacja dla klienta już trwa.');
        $this->db->BeginTrans();
        try {
            $this->db->Execute('DELETE FROM lnp_exclusions WHERE customerid=?',[$id]);
            if($days) $this->db->Execute('INSERT INTO lnp_exclusions(customerid,until_time,reason) VALUES(?,?,?)',[$id,time()+$days*86400,$reason]);
            $this->history($id,'exception','saved',$days.' dni: '.$reason);
            $this->db->CommitTrans();
        } catch(Throwable $e) { $this->db->RollbackTrans(); throw $e; }
        finally { $this->db->GetOne('SELECT pg_advisory_unlock(762319, ?)',[$id]); }
    }
    public function act(int $id,string $action,int $revision,?string $historyHash=null,bool $automatic=false,bool $manualCountdown=false): string
    {
        if (!in_array($action,['notify','notify_manual','notify_history','block','unblock'],true) || $id<1) throw new RuntimeException('Nieprawidłowa operacja.');
        // One sender/blocking operator per customer, shared by cron and UI.
        $lock=$this->db->GetOne('SELECT pg_try_advisory_lock(762319, ?)',[$id]);
        if (!in_array($lock,[true,1,'1','t'],true)) throw new RuntimeException('Operacja dla klienta już trwa.');
        try {
            $s=$this->settings();
            if ($s['revision']!==$revision) throw new RuntimeException('Ustawienia się zmieniły. Odśwież podgląd.');
            $c=$this->inspect($id,$s);
            if (!$c || $c['excluded'] || $c['state']==='review') throw new RuntimeException('Klient wyłączony z działań lub wymaga weryfikacji.');
            if (in_array($action,['notify','notify_manual','notify_history'],true)) {
                if(in_array($action,['notify_manual','notify_history'],true) && (float)$c['debt']<=0.009)throw new RuntimeException('Brak długu abonamentowego.');
                if ($action==='notify' && !in_array($c['state'],['notify','block'],true)) throw new RuntimeException('Saldo jest poniżej progu powiadomienia.');
                if($action==='notify_history'){
                    $s['channels']=['mail'];$attachment=NotifyPaymentHistory::build($this->db,$c);
                    if(!$historyHash || !hash_equals($historyHash,$attachment['hash']))throw new RuntimeException('Historia płatności zmieniła się. Przygotuj nowy podgląd.');
                    return $this->notify($c,$s,$attachment,$manualCountdown?'manual':null);
                }
                return $this->notify($c,$s,null,$automatic && $action==='notify'?'automatic':($manualCountdown?'manual':null));
            }
            if($automatic && $action==='block' && !$s['auto_block'] && empty($this->blockNoticeStatus($c,$s)['manual_ready']))return 'Brak aktywnego ręcznego zlecenia blokady.';
            return $this->nodes($c,$action);
        } finally { $this->db->GetOne('SELECT pg_advisory_unlock(762319, ?)',[$id]); }
    }
    public function blockNoticeStatus(array $customer,?array $settings=null): array
    {
        return (new NotifyBlockNotice($this->db))->status($customer,$settings??$this->settings());
    }
    private function nodes(array $c,string $action): string
    {
        $id=(int)$c['id'];
        if ($action==='block' && !$c['owned_blocks'] && !NotifyPolicy::internetBlockDue($c,$this->settings(),$this->blockNoticeStatus($c))) {
            if((int)$this->settings()['block_after_days']===0)throw new RuntimeException('Dług abonamentowy poniżej progu blokady.');
            if($c['debt']<=0.009)throw new RuntimeException('Brak długu abonamentowego.');
        }
        if ($action==='unblock' && $c['debt']>0) throw new RuntimeException('Odblokowanie dostępne po spłacie długu abonamentowego.');
        $this->db->BeginTrans();
        try {
            $customer=$this->db->GetRow('SELECT status,deleted,cutoffstop,divisionid FROM customers WHERE id=? FOR UPDATE',[$id]);
            if (!$customer || $customer['deleted'] || (int)$customer['status']!==CSTATUS_CONNECTED || (int)$customer['cutoffstop']>=time()) throw new RuntimeException('Status lub ochrona klienta zmieniły się.');
            $fresh=$this->inspect($id);
            if (!$fresh || $fresh['excluded'] || $fresh['state']==='review'
                || ($action==='block' && !$fresh['owned_blocks'] && ((int)$this->settings()['block_after_days']===0 ? $fresh['state']!=='block' : $fresh['debt']<=0.009))
                || ($action==='unblock' && $fresh['debt']>0)) throw new RuntimeException('Saldo lub wyjątki zmieniły się. Odśwież podgląd.');
            $c=$fresh;
            $rows=$this->db->GetAll('SELECT n.id,n.access FROM nodes n WHERE n.ownerid=?'
                .($action==='unblock'?' AND EXISTS(SELECT 1 FROM lnp_blocks b WHERE b.nodeid=n.id AND b.customerid=n.ownerid)':'')
                .' ORDER BY n.id FOR UPDATE',[$id])?:[];
            if($action==='block'){
                $hasActive=false;foreach($rows as $node)if((int)$node['access']===1)$hasActive=true;
                if($hasActive){$gate=$this->blockNoticeStatus($fresh);if(!$gate['ready']){
                    $this->db->RollbackTrans();
                    return 'Oczekiwanie: '.$gate['reason'].($gate['due_at']?' Najwcześniej: '.date('Y-m-d H:i:s',$gate['due_at']):'');
                }}
            }
            $billing=new NotifyBilling($this->db,$this->lms);$billingMessage='Bez zmiany naliczania — osobny próg '.$this->settings()['suspend_percent'].'%.';
            // Nothing is committed unless access AND billing changes both succeed.
            if($action==='unblock')$billingMessage=$billing->resume($id);
            elseif($rows && NotifyPolicy::suspendDue($fresh,$this->settings())) {
                $hasEnabled=false;foreach($rows as $n)if((int)$n['access']===1)$hasEnabled=true;
                if($hasEnabled || $fresh['owned_blocks']>0)$billingMessage=$billing->pause($id,(int)$customer['divisionid']);
            }
            $count=0;
            foreach($rows as $node) {
                $target=$action==='block'?0:1;
                if((int)$node['access']!==$target) {
                    $this->db->checked(function() use($node,$target) {return $this->lms->NodeSet((int)$node['id'],$target);});
                    if((int)$this->db->GetOne('SELECT access FROM nodes WHERE id=?',[$node['id']])!==$target) throw new RuntimeException('Nie udało się zmienić dostępu urządzenia.');
                    if($action==='block') $this->db->Execute('INSERT INTO lnp_blocks(nodeid,customerid,created_at) VALUES(?,?,?) ON CONFLICT(nodeid) DO NOTHING',[$node['id'],$id,time()]);
                    ++$count;
                }
                if($action==='unblock') $this->db->Execute('DELETE FROM lnp_blocks WHERE nodeid=? AND customerid=?',[$node['id'],$id]);
            }
            $this->history($id,$action,'done','Zmieniono urządzenia: '.$count.'. '.$billingMessage.' Saldo: '.$c['balance'].'; miesięcznie: '.$c['base']);
            $this->db->CommitTrans();
            return 'Zmieniono urządzenia: '.$count.'. '.$billingMessage;
        } catch(Throwable $e) { $this->db->RollbackTrans(); throw $e; }
    }
    public function notificationTargets(array $c,array $s): array
    {
        $id=(int)$c['id'];$targets=[];
        foreach($s['channels'] as $channel) {
            $action='notify_'.$channel;
            if($this->db->GetOne('SELECT 1 FROM lnp_history WHERE customerid=? AND action=? AND result IN (?,?) AND created_at>? LIMIT 1',
                [$id,$action,'pending','sent',time()-$s['repeat_days']*86400])) continue;
            if(empty($c[$channel==='mail'?'mailingnotice':'smsnotice'])) continue;
            $flag=($channel==='mail'?CONTACT_EMAIL:CONTACT_MOBILE)|CONTACT_NOTIFICATIONS;
            $contacts=$this->db->GetCol('SELECT DISTINCT contact FROM customercontacts WHERE customerid=? AND (type & ?)=? ORDER BY contact',
                [$id,$flag|CONTACT_DISABLED,$flag])?:[];
            $contacts=array_values(array_filter($contacts,static function($v) use($channel) {
                return $channel==='mail'?filter_var($v,FILTER_VALIDATE_EMAIL)!==false:(bool)preg_match('/^\+?[0-9 ()-]{7,25}$/D',$v);
            }));
            if(!$contacts) continue;
            if($channel==='mail' && !filter_var($s['sender_email'],FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Ustaw adres nadawcy e-mail.');
            $targets[$channel]=$contacts;
        }
        return $targets;
    }
    public function manualPreview(?array $settings=null): array
    {
        $s=$settings??$this->settings();$rows=[];$skipped=['excluded'=>0,'review'=>0,'contact'=>0];
        foreach($this->customerSnapshot() as $c){
            if((float)$c['debt']<=0.009)continue;
            if($c['excluded']){++$skipped['excluded'];continue;}
            if($c['state']==='review'){++$skipped['review'];continue;}
            $targets=$this->notificationTargets($c,$s);
            if(!$targets){++$skipped['contact'];continue;}
            $c['channels']=array_keys($targets);$rows[]=$c;
        }
        usort($rows,static fn($a,$b)=>$b['debt']<=>$a['debt']);
        return ['rows'=>$rows,'skipped'=>$skipped,'revision'=>$s['revision']];
    }
    public function messagePreview(array $c,array $s): array
    {
        if(strpos($s['subject']."\n".$s['body'],'%bankaccount')!==false){
            $account=$this->db->GetOne('SELECT d.account FROM customers c LEFT JOIN divisions d ON d.id=c.divisionid WHERE c.id=?',[$c['id']]);
            $c['bankaccount']=format_bankaccount(bankaccount($c['id'],$account));
        }
        return ['subject'=>NotifyPolicy::render($s['subject'],$c),'body'=>NotifyPolicy::render($s['body'],$c)];
    }
    private function notify(array $c,array $s,?array $attachment=null,?string $countdownOrigin=null): string
    {
        $id=(int)$c['id']; $sent=0;$uncertain=false;
        foreach($this->notificationTargets($c,$s) as $channel=>$contacts){
            $action='notify_'.$channel;
            $message=$this->messagePreview($c,$s);$body=$message['body'];$subject=$message['subject'];
            if($attachment)$body.="\n\nW załączniku przesyłamy historię płatności i rozliczeń Państwa konta.";
            // A durable intent is recorded before the external API. An interrupted send is not retried automatically.
            $attachmentNote=$attachment?'Załącznik: '.$attachment['filename'].'; SHA256: '.$attachment['hash'].'. ':'';
            $event=$this->history($id,$action,'pending',$attachmentNote.'Rozpoczęto wysyłkę. W razie przerwania sprawdź historię wiadomości LMS.');
            $allOk=true;$confirmed=true; $messageIds=[];$messageItems=[];
            try {
                foreach($contacts as $destination) {
                    $recipient=['id'=>$id,'status'=>MSG_ERROR,'error'=>'Wysyłka w toku — sprawdź wynik w panelu.'];
                    $recipient[$channel==='mail'?'email':'phone']=$destination;
                    $m=$this->lms->addMessage(['type'=>$channel==='mail'?MSG_MAIL:MSG_SMS,'subject'=>$subject,
                        'body'=>$body,'userid'=>$this->user?:null,'sender'=>['name'=>$s['sender_name'],'mail'=>$s['sender_email']],
                        'recipients'=>[$recipient]]);
                    $messageIds[]=$m['id']; $item=$m['items'][$id][$destination];$messageItems[]=(int)$item;
                    if($channel==='mail') {
                        $result=$this->lms->SendMail($destination,['From'=>$s['sender_email'],'To'=>$destination,
                            'Subject'=>$subject,'Reply-To'=>$s['sender_email']],$body,$attachment?[$attachment]:null);
                    } else $result=$this->lms->SendSMS(preg_replace('/[ ()-]/','',$destination),$body,$item);
                    $status=is_int($result)?$result:(is_array($result)?(int)($result['status']??MSG_ERROR):MSG_ERROR);
                    $error=is_string($result)?$result:(is_array($result)?implode(', ',$result['errors']??[]):'');
                    $external=is_array($result)?($result['id']??null):null;
                    $this->db->Execute('UPDATE messageitems SET status=?,lastdate=?NOW?,error=?,externalmsgid=? WHERE id=?',[$status,$error?:null,$external,$item]);
                    if($status===MSG_ERROR) $allOk=false;
                    if(!in_array($status,[MSG_SENT,MSG_DELIVERED],true))$confirmed=false;
                }
                // Partial delivery is not retried blindly, to avoid duplicating successful recipients.
                $this->db->Execute('UPDATE lnp_history SET result=?,details=? WHERE id=?',
                    [$allOk?'sent':'pending',$attachmentNote.'Wiadomości LMS: '.implode(', ',$messageIds).($allOk?' — przyjęte do wysyłki.':' — częściowy błąd, sprawdź LMS.'),$event]);
                if($countdownOrigin!==null && $allOk && $confirmed)(new NotifyBlockNotice($this->db))->record($c,$s,$event,$messageItems,time(),$countdownOrigin);
                if($allOk) ++$sent;
                else $uncertain=true;
            } catch(Throwable $e) {
                $this->db->Execute('UPDATE lnp_history SET details=? WHERE id=?',[$attachmentNote.'Przerwany lub nieznany wynik. Wiadomości LMS: '.implode(', ',$messageIds).'. Zweryfikuj przed ponowieniem.',$event]);
                throw $e;
            }
        }
        if($uncertain)return 'Częściowy lub nieznany wynik wysyłki — sprawdź historię wiadomości LMS; bez automatycznego ponawiania.';
        return $sent?'Kanały przyjęte do wysyłki: '.$sent:'Bez wysyłki: odstęp między przypomnieniami, brak zgody lub właściwego kontaktu.';
    }
}
