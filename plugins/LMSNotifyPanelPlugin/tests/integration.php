<?php
// Run from the LMS root. All fixture tables are TEMPORARY and shadow production tables
// only in this database session. No messages are sent; the LMS action API is a fake.
if(PHP_SAPI!=='cli') exit(1);
$script_name='NotifyPanel isolated integration tests'; $script_parameters=[];
require getcwd().'/bin/script-options.php';
require_once __DIR__.'/../lib/NotifyPanel.php';
$DB->BeginTrans();
try {
    $schema=file_get_contents(__DIR__.'/../sql/install.pgsql.sql');
    $schema=preg_replace('/CREATE TABLE /','CREATE TEMP TABLE ',$schema);
    $schema=str_replace('CREATE TEMP TABLE IF NOT EXISTS ','CREATE TEMP TABLE ',$schema);
    $schema=preg_replace('/CREATE INDEX .*?;/s','',$schema);
    $DB->Execute($schema);
    $DB->Execute('CREATE TEMP TABLE customers(id integer PRIMARY KEY,name text,lastname text,status integer,deleted integer,cutoffstop bigint,mailingnotice integer,smsnotice integer,divisionid integer DEFAULT 0,notes text DEFAULT \'\',info text DEFAULT \'\')');
    $DB->Execute('CREATE TEMP TABLE customernotes(id serial,customerid integer,dt bigint,message text)');
    $DB->Execute('CREATE TEMP VIEW customeraddressview AS SELECT * FROM customers');
    $DB->Execute('CREATE TEMP TABLE nodes(id integer PRIMARY KEY,ownerid integer,access integer)');
    $DB->Execute('CREATE TEMP TABLE assignments(customerid integer,tariffid integer,liabilityid integer,commited integer DEFAULT 1,suspended integer DEFAULT 0,datefrom bigint DEFAULT 0,dateto bigint DEFAULT 0,period integer DEFAULT 0,id bigserial PRIMARY KEY)');
    $DB->Execute('CREATE TEMP TABLE tariffs(id integer,period integer)');
    $DB->Execute('CREATE TEMP TABLE customercontacts(customerid integer,contact text,type integer)');
    $DB->Execute('CREATE TEMP TABLE cash(id bigserial,customerid integer,value numeric,currencyvalue numeric DEFAULT 1,time bigint DEFAULT 1,docid integer,itemid integer)');
    $DB->Execute('CREATE TEMP TABLE documents(id integer,fullnumber text,cdate bigint,paytime integer)');
    $DB->Execute("INSERT INTO documents VALUES(1,'TEST',1,0)");
    $DB->Execute('CREATE TEMP TABLE invoicecontents(docid integer,itemid integer,period integer)');
    $DB->Execute('INSERT INTO invoicecontents VALUES(1,1,3)');
    ConfigHelper::getConfig('finances.suspension_percentage',0);
    $DB->Execute('CREATE TEMP TABLE uiconfig(section text,var text,value text,disabled integer,userid integer,divisionid integer)');
    $DB->Execute("INSERT INTO uiconfig VALUES('finances','suspension_percentage','0',0,NULL,NULL)");
    $DB->Execute('CREATE TEMP TABLE lnp_sms_delivery(itemid integer,gateway_status text,report_at bigint,updated_at bigint)');
    $DB->Execute('CREATE TEMP TABLE messageitems(id integer,status integer,lastdate bigint,error text,externalmsgid text)');
    $DB->Execute("INSERT INTO customers VALUES(1,'Test','Klient',3,0,0,1,1)");
    $DB->Execute('INSERT INTO nodes VALUES(11,1,1),(12,1,0)');
    $flag=CONTACT_EMAIL|CONTACT_NOTIFICATIONS;
    $DB->Execute('INSERT INTO customercontacts VALUES(?,?,?),(?,?,?)',[1,'test@example.invalid',$flag,1,'disabled@example.invalid',$flag|CONTACT_DISABLED]);
    $DB->Execute('INSERT INTO assignments(customerid,liabilityid,period) VALUES(1,1,3),(1,2,0)');
    $DB->Execute('INSERT INTO assignments(customerid,liabilityid,period,suspended) VALUES(1,3,3,1)');
    $api=new class($DB) {
        private $db; public $balance=-300; public $monthly=[['currency'=>'PLN','sum'=>100]]; public $deliveries=0; public $lastFiles=null; public $nodeFail=false;public $sendStatus=MSG_SENT;
        public function __construct($db) {$this->db=$db;}
        public function getCustomerBalance($id,$time=null,$expired=false) { if($expired!==false) throw new RuntimeException('Must use total balance'); if((float)$this->db->GetOne('SELECT COALESCE(sum(value),0) FROM cash WHERE customerid=?',[$id])!== (float)$this->balance){$this->db->Execute('DELETE FROM cash WHERE customerid=?',[$id]); $this->db->Execute('INSERT INTO cash(customerid,value,docid,itemid) VALUES(?,?,1,1)',[$id,$this->balance]);} return $this->balance; }
        public function GetCustomerAssignments($id,$expired=false,$approved=true) {
            if(!$this->monthly)return [];
            $sum=$this->monthly[0]['sum'];
            return array_map(static function($r) use($sum) {return array_merge($r,['periodvalue'=>$r['period'],'currency'=>'PLN','discounted_value'=>$r['suspended']?0:$sum]);},$this->db->GetAll('SELECT * FROM assignments WHERE customerid=?',[$id])?:[]);
        }
        public function SuspendAssignment($id,$suspend=true) {$this->db->Execute('UPDATE assignments SET suspended=? WHERE id=?',[$suspend?1:0,$id]);}
        public function NodeSet($id,$access) {if($this->nodeFail)throw new RuntimeException('Simulated node failure');$this->db->Execute('UPDATE nodes SET access=? WHERE id=?',[$access,$id]);}
        public function addMessage($p) {
            $contact=$p['recipients'][0]['email'];
            if($contact!=='test@example.invalid') throw new RuntimeException('Disabled contact selected');
            $this->db->Execute('INSERT INTO messageitems(id,status) VALUES(1,3)'); return ['id'=>99,'items'=>[1=>[$contact=>1]]];
        }
        public function SendMail($destination,$headers,$body,$files=null) {++$this->deliveries;$this->lastFiles=$files; return $this->sendStatus;}
    };
    $np=new NotifyPanel($DB,$api,1);
    function verify($ok,$what) { if(!$ok) throw new RuntimeException('FAIL '.$what); echo 'PASS '.$what.PHP_EOL; }
    $s=$np->settings(); verify($s['block_percent']===300,'defaults loaded');
    $np->save(['sender_email'=>'sender@example.invalid','suspend_percent'=>300,'block_after_days'=>7],1); $s=$np->settings();
    try{$np->save([],1);throw new LogicException('stale settings accepted');}catch(RuntimeException $ex){verify(true,'stale revision rejected');}
    $waiting=$np->act(1,'block',$s['revision']);
    verify(strpos($waiting,'Oczekiwanie:')===0 && (int)$DB->GetOne('SELECT access FROM nodes WHERE id=11')===1,'block refuses missing successful warning');
    function allowBlockTest($np,$DB,$s){
        $c=$np->inspect(1);if(!$c || empty($c['debt_cycle']))return;
        if(!$DB->GetOne('SELECT 1 FROM messageitems WHERE id=900001'))$DB->Execute('INSERT INTO messageitems(id,status) VALUES(900001,2)');
        $DB->Execute('INSERT INTO lnp_block_notices(customerid,cycle_key,sent_at,source_historyid,source_items,origin) VALUES(1,?,?,0,?,\'automatic\') ON CONFLICT(customerid,origin) DO UPDATE SET cycle_key=EXCLUDED.cycle_key,sent_at=EXCLUDED.sent_at,source_items=EXCLUDED.source_items,origin=EXCLUDED.origin',[$c['debt_cycle'],time()-8*86400,'[900001]']);
    }
    $DB->Execute("UPDATE uiconfig SET value='50' WHERE section='finances'");
    try{allowBlockTest($np,$DB,$s);$np->act(1,'block',$s['revision']);throw new LogicException('non-zero suspension fee accepted');}catch(RuntimeException $ex){verify(true,'non-zero suspension fee prevents block');}
    verify((int)$DB->GetOne('SELECT access FROM nodes WHERE id=11')===1,'fee guard preserves enabled device');
    $DB->Execute("UPDATE uiconfig SET value='0' WHERE section='finances'");
    // save() owns its transaction. Recreate the outer transaction after those intentional commits.
    $api->nodeFail=true;
    try{allowBlockTest($np,$DB,$s);$np->act(1,'block',$s['revision']);throw new LogicException('failed access committed');}catch(RuntimeException $ex){verify(true,'node failure aborts combined operation');}
    verify((int)$DB->GetOne('SELECT count(*) FROM lnp_billing')===0,'billing rollback on node failure');
    $api->nodeFail=false;
    allowBlockTest($np,$DB,$s);$np->act(1,'block',$s['revision']);
    verify((int)$DB->GetOne('SELECT count(*) FROM lnp_billing')===1,'billing suspension owned alongside node block');
    verify((int)$DB->GetOne('SELECT suspended FROM assignments WHERE liabilityid=1')===1,'recurring charges suspended');
    verify((int)$DB->GetOne('SELECT suspended FROM assignments WHERE liabilityid=2')===0,'one-off charges remain active');
    verify((int)$DB->GetOne('SELECT count(*) FROM lnp_blocks')===1,'only previously enabled node owned');
    verify((int)$DB->GetOne('SELECT access FROM nodes WHERE id=11')===0,'block applied');
    allowBlockTest($np,$DB,$s);$np->act(1,'block',$s['revision']); verify((int)$DB->GetOne('SELECT count(*) FROM lnp_blocks')===1,'repeat block idempotent');
    try{$np->act(1,'unblock',$s['revision']);throw new LogicException('debt unblock');}catch(RuntimeException $ex){verify(true,'unblock refused with debt');}
    $api->balance=0; $np->act(1,'unblock',$s['revision']);
    verify((int)$DB->GetOne('SELECT count(*) FROM lnp_billing')===0,'billing ledger cleared after repayment');
    verify((int)$DB->GetOne('SELECT suspended FROM assignments WHERE liabilityid=1')===0,'owned recurring charges resumed');
    verify((int)$DB->GetOne('SELECT suspended FROM assignments WHERE liabilityid=3')===1,'prior manual suspension preserved');
    verify((int)$DB->GetOne('SELECT access FROM nodes WHERE id=11')===1,'owned node restored after payment');
    verify((int)$DB->GetOne('SELECT access FROM nodes WHERE id=12')===0,'preexisting manual block preserved');
    verify((int)$DB->GetOne('SELECT count(*) FROM lnp_blocks')===0,'ledger cleared');
    // Separate thresholds: internet at 300%, billing at 400%, including an already blocked device.
    $np->save(array_merge($s,['suspend_percent'=>400]),$s['revision']);$s=$np->settings();
    $api->balance=-300;allowBlockTest($np,$DB,$s);$np->act(1,'block',$s['revision']);
    verify((int)$DB->GetOne('SELECT access FROM nodes WHERE id=11')===0 && (int)$DB->GetOne('SELECT suspended FROM assignments WHERE liabilityid=1')===0,'300 percent blocks internet but keeps billing active');
    $api->balance=-399.99;allowBlockTest($np,$DB,$s);$np->act(1,'block',$s['revision']);
    verify((int)$DB->GetOne('SELECT count(*) FROM lnp_billing')===0,'below 400 percent does not suspend billing');
    $api->balance=-400;allowBlockTest($np,$DB,$s);$np->act(1,'block',$s['revision']);
    verify((int)$DB->GetOne('SELECT suspended FROM assignments WHERE liabilityid=1')===1 && (int)$DB->GetOne('SELECT count(*) FROM lnp_billing')===1,'400 percent suspends billing after internet was already blocked');
    $api->balance=-350;allowBlockTest($np,$DB,$s);$np->act(1,'block',$s['revision']);
    verify((int)$DB->GetOne('SELECT suspended FROM assignments WHERE liabilityid=1')===1,'partial payment does not prematurely resume billing');
    $api->balance=0;$np->act(1,'unblock',$s['revision']);
    verify((int)$DB->GetOne('SELECT access FROM nodes WHERE id=11')===1 && (int)$DB->GetOne('SELECT suspended FROM assignments WHERE liabilityid=1')===0,'repayment restores both stages');
    $np->save(array_merge($s,['suspend_percent'=>300]),$s['revision']);$s=$np->settings();
    $api->balance=-200;
    $np->act(1,'notify',$s['revision'],$np->mailPreviewHash($np->inspect(1),$s)); $np->act(1,'notify',$s['revision'],$np->mailPreviewHash($np->inspect(1),$s));
    verify($api->deliveries===1,'cooldown avoids duplicate mail and disabled contact excluded');
    $np->exclude(1,30,'Test exception');
    try{$np->act(1,'notify',$s['revision'],$np->mailPreviewHash($np->inspect(1),$s));throw new LogicException('exception ignored');}catch(RuntimeException $ex){verify(true,'exception prevents actions');}
    $np->exclude(1,0,'');
    $api->balance=-300; $api->monthly=[];
    try{allowBlockTest($np,$DB,$s);$np->act(1,'block',$s['revision']);throw new LogicException('zero base accepted');}catch(RuntimeException $ex){verify(true,'no base prevents block');}
    $api->monthly=[['currency'=>'PLN','sum'=>100]];
    $DB->Execute('UPDATE customers SET cutoffstop=? WHERE id=1',[time()+86400]);
    try{allowBlockTest($np,$DB,$s);$np->act(1,'block',$s['revision']);throw new LogicException('cutoffstop ignored');}catch(RuntimeException $ex){verify(true,'LMS cutoffstop prevents block');}
    $DB->Execute('UPDATE customers SET cutoffstop=0,status=1 WHERE id=1');
    verify($np->inspect(1)===null,'inactive customer excluded');
    $DB->Execute('UPDATE customers SET status=3 WHERE id=1');
    $DB->Execute('INSERT INTO assignments VALUES(1,NULL,1,1,0,0,0,?)',[WEEKLY]);
    verify($np->inspect(1)['state']==='review','weekly billing requires review');
    $DB->Execute('DELETE FROM assignments WHERE period=2');
    // A manual native suspension is never claimed or ended by this plugin.
    $api->balance=-300;
    $DB->Execute('INSERT INTO assignments(customerid,datefrom) VALUES(?,?)',[1,time()-100]);
    allowBlockTest($np,$DB,$s);$np->act(1,'block',$s['revision']);
    verify((int)$DB->GetOne('SELECT count(*) FROM assignments WHERE tariffid IS NULL AND liabilityid IS NULL')===1,'manual global suspension preserved');
    $api->balance=0;
    try{$np->act(1,'unblock',$s['revision']);throw new LogicException('manual suspension ignored');}catch(RuntimeException $ex){verify(true,'manual suspension prevents automatic restore');}
    verify((int)$DB->GetOne('SELECT access FROM nodes WHERE id=11')===0,'failed billing restore leaves device blocked');
    // All manual channels obey the same notification threshold as automatic sending.
    $DB->Execute('DELETE FROM lnp_history WHERE customerid=1 AND action IN (?,?)',['notify_mail','notify_sms']);
    $api->balance=-199.99;$before=$api->deliveries;
    try{$np->act(1,'notify',$s['revision'],$np->mailPreviewHash($np->inspect(1),$s));throw new LogicException('Automatic threshold bypassed');}catch(RuntimeException $e){verify(true,'automatic notification still requires percentage threshold');}
    foreach(['notify_manual','notify_history','notify_sms','notify_sms_no_mail'] as $action){
        try{$np->act(1,$action,$s['revision']);throw new LogicException('Below-threshold manual action accepted');}catch(RuntimeException $e){verify($api->deliveries===$before,'below threshold rejected before sender: '.$action);}
    }
    $api->balance=-200;
    $np->act(1,'notify_manual',$s['revision'],$np->mailPreviewHash($np->inspect(1),$s));verify($api->deliveries===$before+1,'manual notification accepted exactly at 200 percent');
    $np->act(1,'notify_manual',$s['revision'],$np->mailPreviewHash($np->inspect(1),$s));verify($api->deliveries===$before+1,'manual cooldown prevents duplicate');
    $api->balance=0;
    try{$np->act(1,'notify_manual',$s['revision'],$np->mailPreviewHash($np->inspect(1),$s));throw new LogicException('Paid customer notified');}catch(RuntimeException $e){verify(true,'manual notification refuses paid customer');}
    // Payment history uses temporary ledger tables and a fake sender only.
    $DB->Execute("ALTER TABLE cash ADD COLUMN currency text DEFAULT 'PLN', ADD COLUMN comment text DEFAULT 'Test ledger', ADD COLUMN type integer DEFAULT 0");

    $DB->Execute('CREATE TEMP TABLE vcustomerassignments(customerid integer,customergroupid integer)');
    $DB->Execute('CREATE TEMP TABLE excludedgroups(userid integer,customergroupid integer)');
    $DB->Execute('DELETE FROM lnp_history WHERE customerid=1 AND action=?',['notify_mail']);
    $api->balance=-200;$c=$np->inspect(1);$attachment=NotifyPaymentHistory::build($DB,$c);$before=$api->deliveries;
    try{$np->act(1,'notify_history',$s['revision'],'invalid');throw new LogicException('Stale attachment sent');}catch(RuntimeException $e){verify($api->deliveries===$before,'changed attachment refuses delivery');}
    $noticesBeforeHistory=$DB->GetAll('SELECT * FROM lnp_block_notices');
    $np->act(1,'notify_history',$s['revision'],$np->mailPreviewHash($np->inspect(1),$s,$attachment));
    verify($api->deliveries===$before+1 && count($api->lastFiles)===1,'history notification sends exactly one attachment via fake mail API');
    verify($DB->GetAll('SELECT * FROM lnp_block_notices')===$noticesBeforeHistory,'manual history notification does not start countdown');
    verify($api->lastFiles[0]['data']===$attachment['data'] && $api->lastFiles[0]['content_type']==='text/html; charset=UTF-8','sent HTML equals preview bytes');
    $np->act(1,'notify_history',$s['revision'],$np->mailPreviewHash($np->inspect(1),$s,$attachment));
    verify($api->deliveries===$before+1,'history notification shares notification cooldown');
    $DB->Execute('DELETE FROM lnp_history WHERE customerid=1 AND action=?',['notify_mail']);
    $DB->Execute('UPDATE customers SET mailingnotice=0 WHERE id=1');
    $np->act(1,'notify_history',$s['revision'],$np->mailPreviewHash($np->inspect(1),$s,$attachment));
    verify($api->deliveries===$before+1,'history notification respects mail consent');
    $DB->Execute('INSERT INTO lnp_history(created_at,customerid,action,result,details,userid) VALUES(?,1,?,?,?,0)',[time()+2,'notify_mail','sent','Załącznik: historia-platnosci-1.html; fixture only']);
    $details=$np->customerListDetails([$np->inspect(1)])[0];
    verify($details['notification_status']==='Wysłano mail z historią','list shows latest history notification from temporary ledger');
    $DB->Execute('INSERT INTO lnp_history(created_at,customerid,action,result,details,userid) VALUES(?,1,?,?,?,0)',[time()+3,'notify_mail','pending','fixture only']);
    $details=$np->customerListDetails([$np->inspect(1)])[0];
    verify($details['notification_status']==='Wysyłka powiadomienia — wynik do sprawdzenia','newer uncertain attempt is not shown as sent');
    // Exercise the real warning registration and block gate using the fake sender only.
    $DB->Execute('DELETE FROM assignments WHERE tariffid IS NULL AND liabilityid IS NULL');
    $DB->Execute('UPDATE assignments SET suspended=0 WHERE liabilityid=1');
    $DB->Execute('UPDATE nodes SET access=1 WHERE id=11');
    $DB->Execute('DELETE FROM lnp_blocks');$DB->Execute('DELETE FROM lnp_billing');
    $DB->Execute('DELETE FROM lnp_history');$DB->Execute('DELETE FROM lnp_block_notices');
    $DB->Execute('DELETE FROM messageitems WHERE id=1');$DB->Execute('UPDATE customers SET mailingnotice=1 WHERE id=1');
    $api->balance=-400;$api->sendStatus=MSG_SENT;
    $np->act(1,'notify_manual',$s['revision'],$np->mailPreviewHash($np->inspect(1),$s));
    verify((int)$DB->GetOne('SELECT count(*) FROM lnp_block_notices WHERE customerid=1')===0,'manual notification does not start countdown');
    $DB->Execute('DELETE FROM lnp_history');$DB->Execute('DELETE FROM messageitems WHERE id=1');
    $np->act(1,'notify_manual',$s['revision'],$np->mailPreviewHash($np->inspect(1),$s),false,true);
    verify($DB->GetOne('SELECT origin FROM lnp_block_notices WHERE customerid=1')==='manual','explicit manual option starts countdown');
    $DB->Execute('DELETE FROM lnp_block_notices');$DB->Execute('DELETE FROM lnp_history');$DB->Execute('DELETE FROM messageitems WHERE id=1');
    $np->act(1,'notify',$s['revision'],null,true);
    verify((int)$DB->GetOne('SELECT count(*) FROM lnp_block_notices WHERE customerid=1')===1,'automatic warning starts durable countdown');
    $waiting=$np->act(1,'block',$s['revision']);
    verify(strpos($waiting,'Oczekiwanie:')===0 && (int)$DB->GetOne('SELECT access FROM nodes WHERE id=11')===1,'real block action waits after newly sent warning');
    $planSettings=$s;$planSettings['auto_block']=false;$planSettings['auto_notify']=false;$planSettings['auto_unblock']=true;
    verify($np->scheduledActions($np->inspect(1),$planSettings)===[],'automatic warning alone cannot block when auto block off');
    $np->act(1,'block',$s['revision'],null,true);
    verify((int)$DB->GetOne('SELECT access FROM nodes WHERE id=11')===1,'fresh scheduler guard refuses unauthorized block');
    $DB->Execute('DELETE FROM lnp_history');$DB->Execute('DELETE FROM messageitems WHERE id=1');
    $np->act(1,'notify_manual',$s['revision'],$np->mailPreviewHash($np->inspect(1),$s),false,true);
    verify($np->scheduledActions($np->inspect(1),$planSettings)===[],'new manual countdown waits even with auto block off');
    $DB->Execute("UPDATE lnp_block_notices SET sent_at=? WHERE origin='manual'",[time()-7*86400]);
    verify($np->scheduledActions($np->inspect(1),$planSettings)===['block'],'expired manual countdown independently schedules block');
    $api->balance=-50;$low=$np->inspect(1);$DB->Execute('UPDATE lnp_block_notices SET cycle_key=?',[$low['debt_cycle']]);
    verify($np->scheduledActions($np->inspect(1),$planSettings)===['block'],'expired countdown blocks below notification and block percentage thresholds');
    $api->balance=0;verify($np->scheduledActions($np->inspect(1),$planSettings)===[],'paid debt cancels countdown block');
    $api->balance=-400;
    $np->act(1,'notify_manual',$s['revision'],$np->mailPreviewHash($np->inspect(1),$s),false,true);
    $DB->Execute("UPDATE lnp_block_notices SET cycle_key=?,sent_at=? WHERE origin='manual'",[$np->inspect(1)['debt_cycle'],time()-7*86400]);
    $DB->Execute('UPDATE lnp_block_notices SET sent_at=? WHERE customerid=1',[time()-7*86400]);
    $np->act(1,'block',$s['revision']);
    verify((int)$DB->GetOne('SELECT access FROM nodes WHERE id=11')===0,'real block action works after seven days');
    $api->balance=0;$np->act(1,'unblock',$s['revision']);$api->balance=-400;
    verify(!$np->blockNoticeStatus($np->inspect(1),$s)['ready'],'repaid and new debt cannot reuse old warning');
    foreach([MSG_ERROR,MSG_NEW] as $status){
        $DB->Execute('DELETE FROM lnp_history');$DB->Execute('DELETE FROM lnp_block_notices');$DB->Execute('DELETE FROM messageitems WHERE id=1');
        $api->sendStatus=$status;$np->act(1,'notify',$s['revision'],null,true);
        verify((int)$DB->GetOne('SELECT count(*) FROM lnp_block_notices')===0,'failed or queued mail does not start countdown: '.$status);
    }
    $DB->Execute('UPDATE documents SET cdate=?,paytime=7 WHERE id=1',[time()]);
    $future=$np->inspect(1);
    verify($future['debt']===400.0 && $future['overdue_debt']===0.0 && $future['percent']===0.0,'not-yet-due invoice remains in balance but not in debt percentage');
    verify(!NotifyPolicy::suspendDue($future,$s),'not-yet-due invoice cannot suspend billing');
    $DB->Execute('UPDATE documents SET cdate=1,paytime=0 WHERE id=1');
    verify($np->inspect(1)['percent']===400.0,'overdue invoice contributes to percentage');
    $np->save(array_merge($s,['block_after_days'=>0]),$s['revision']);$s=$np->settings();
    $DB->Execute('DELETE FROM lnp_block_notices');
    $np->act(1,'block',$s['revision']);
    verify((int)$DB->GetOne('SELECT access FROM nodes WHERE id=11')===0,'zero countdown blocks by threshold without warning');
    verify((int)$DB->GetOne('SELECT suspended FROM assignments WHERE liabilityid=1')===1,'zero countdown preserves billing suspension threshold');
    $api->balance=0;$np->act(1,'unblock',$s['revision']);
    verify((int)$DB->GetOne('SELECT access FROM nodes WHERE id=11')===1,'zero countdown restores device after repayment');
    $api->balance=-300;$api->sendStatus=MSG_SENT;
    $DB->Execute('DELETE FROM lnp_history');$DB->Execute('DELETE FROM messageitems');
    $s=$np->settings();$s['sender_email']='sender@example.invalid';$s['channels']=['mail'];$np->save($s,$s['revision']);$s=$np->settings();
    $DB->Execute('DELETE FROM customercontacts');$DB->Execute('INSERT INTO customercontacts VALUES(?,?,?)',[1,'test@example.invalid',CONTACT_EMAIL|CONTACT_NOTIFICATIONS]);
    $attachment=NotifyPaymentHistory::build($DB,$np->inspect(1));
    $hash=$np->mailPreviewHash($np->inspect(1),$s,$attachment);$before=$api->deliveries;
    $DB->Execute('UPDATE customercontacts SET contact=?',['changed@example.invalid']);
    try{$np->act(1,'notify_history',$s['revision'],$hash);throw new LogicException('Changed history recipient accepted');}catch(RuntimeException $e){verify($api->deliveries===$before,'history recipient changed after preview refuses delivery');}
    $DB->Execute('UPDATE customercontacts SET contact=?',['test@example.invalid']);
    $hash=$np->mailPreviewHash($np->inspect(1),$s);$DB->Execute('UPDATE customercontacts SET contact=?',['changed@example.invalid']);
    try{$np->act(1,'notify_manual',$s['revision'],$hash);throw new LogicException('Changed plain recipient accepted');}catch(RuntimeException $e){verify($api->deliveries===$before,'plain email recipient changed after preview refuses delivery');}
    $DB->Execute('UPDATE customercontacts SET contact=?',['test@example.invalid']);
    $hash=$np->mailPreviewHash($np->inspect(1),$s);$c=$np->inspect(1);$c['customername']='Changed fixture name';
    verify(!hash_equals($hash,$np->mailPreviewHash($c,$s+[])) || strpos($s['body'],'%name')===false,'preview fingerprint covers rendered message');
    echo "ALL ISOLATED INTEGRATION TESTS PASSED; no external deliveries.\n";
} finally {
    $DB->RollbackTrans();
    // All objects are session-local TEMP tables, including after tested commits.
}

