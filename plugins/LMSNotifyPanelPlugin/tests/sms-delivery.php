<?php
if(PHP_SAPI!=='cli')exit(1);
$script_name='Isolated SMS delivery tests';$script_parameters=[];
require getcwd().'/bin/script-options.php';
require_once __DIR__.'/../lib/NotifySmsDelivery.php';
function deliveryCheck($ok,$label){if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
// Each receive() owns its transaction; session-local tables isolate all test data.
try{
    $DB->Execute('CREATE TEMP TABLE messageitems(id integer PRIMARY KEY,customerid integer,status integer,lastdate bigint,error text,externalmsgid text)');
    $DB->Execute('CREATE TEMP TABLE lnp_history(id bigint PRIMARY KEY)');
    $schema=file_get_contents(__DIR__.'/../sql/upgrade-0.8.1.pgsql.sql');
    $schema=str_replace('CREATE TABLE IF NOT EXISTS ','CREATE TEMP TABLE ',$schema);
    $schema=preg_replace('/CREATE INDEX .*?;/s','',$schema);$DB->Execute($schema);
    $DB->Execute('INSERT INTO messageitems(id,customerid,status) VALUES(1,2256,?),(2,2256,?)',[MSG_NEW,MSG_NEW]);
    $delivery=new NotifySmsDelivery($DB);$idx=$delivery->begin(1,'600000000');
    try{$delivery->begin(1,'48600000000');throw new LogicException('Duplicate accepted');}catch(RuntimeException $e){}
    $delivery->accepted(1,['id'=>'testgatewayid','gateway_status'=>'QUEUE']);
    $r=['id'=>'testgatewayid','idx'=>$idx,'number'=>'48600000000','state'=>'DELIVERED','date'=>time()];
    $bad=$r;$bad['idx']=str_repeat('0',64);
    try{$delivery->receive([$bad]);throw new LogicException('Forged report accepted');}catch(RuntimeException $e){}
    deliveryCheck((int)$DB->GetOne('SELECT status FROM messageitems WHERE id=1')===MSG_NEW,'unknown callback cannot change message');
    $delivery->receive([$r]);$delivery->receive([$r]);
    deliveryCheck((int)$DB->GetOne('SELECT status FROM messageitems WHERE id=1')===MSG_DELIVERED,'delivery and duplicate callback are idempotent');
    $late=$r;$late['state']='QUEUE';$delivery->receive([$late]);
    deliveryCheck($DB->GetOne('SELECT gateway_status FROM lnp_sms_delivery WHERE itemid=1')==='DELIVERED','late queue cannot overwrite delivery');
    $idx2=$delivery->begin(2,'48600000001');$delivery->accepted(2,['id'=>'testfailureid','gateway_status'=>'SENT']);
    $failure=['id'=>'testfailureid','idx'=>$idx2,'number'=>'48600000001','state'=>'UNDELIVERED','date'=>time()];
    $delivery->receive([$failure]);
    deliveryCheck((int)$DB->GetOne('SELECT status FROM messageitems WHERE id=2')===MSG_ERROR,'failed delivery updates LMS status');
    $input=['MsgId'=>'testgatewayid,testfailureid','idx'=>$idx.','.$idx2,'status'=>'404,405','to'=>'48600000000,48600000001','donedate'=>time().','.time()];
    deliveryCheck(count(NotifySmsDelivery::reports($input))===2,'provider batch report parsed');
    $input['status']='404';
    try{NotifySmsDelivery::reports($input);throw new LogicException('Malformed batch accepted');}catch(InvalidArgumentException $e){}
    $DB->Execute('CREATE TEMP TABLE lnp_block_notices(customerid integer,cycle_key varchar(64),sent_at bigint,source_historyid bigint,source_items text,origin varchar(16),PRIMARY KEY(customerid,origin))');
    $DB->Execute('INSERT INTO lnp_history VALUES(1),(2)');
    $DB->Execute('INSERT INTO messageitems(id,customerid,status) VALUES(3,2256,?),(4,2256,?),(5,2256,?)',[MSG_NEW,MSG_NEW,MSG_NEW]);
    $panel=new class {
        public float $debt=100;
        public function settings(){return ['block_after_days'=>7];}
        public function inspect($id,$s){return ['id'=>$id,'debt'=>$this->debt,'debt_cycle'=>'test-cycle','state'=>'notify','excluded'=>false];}
    };
    $delivery->pendingNotice($panel->inspect(2256,[]),1,[3,4],'manual');
    $keys=[];foreach([3,4,5] as $item){$keys[$item]=$delivery->begin($item,'48600000000');$delivery->accepted($item,['id'=>'queued'.$item,'gateway_status'=>'QUEUE']);}
    $report=static fn($id)=>['id'=>'queued'.$id,'idx'=>$keys[$id],'number'=>'48600000000','state'=>'DELIVERED','date'=>time()];
    $delivery->receive([$report(3)],$panel);
    deliveryCheck($DB->GetOne('SELECT origin FROM lnp_block_notices')==='manual','one delivered recipient starts countdown while second is queued');
    $first=$DB->GetOne('SELECT sent_at FROM lnp_block_notices');
    $delivery->receive([$report(4)],$panel);
    deliveryCheck($DB->GetOne('SELECT origin FROM lnp_block_notices')==='manual','second delivery preserves the requested manual countdown');
    deliveryCheck($DB->GetOne('SELECT sent_at FROM lnp_block_notices')===$first,'second number does not reset countdown');$delivery->receive([$report(4)],$panel);
    deliveryCheck($DB->GetOne('SELECT sent_at FROM lnp_block_notices')===$first,'duplicate delivery report preserves first countdown');
    $DB->Execute('DELETE FROM lnp_block_notices');
    $delivery->pendingNotice($panel->inspect(2256,[]),2,[5],'manual');$panel->debt=0;
    $delivery->receive([$report(5)],$panel);
    deliveryCheck(!$DB->GetOne('SELECT 1 FROM lnp_block_notices'),'payment before delivery confirmation cancels countdown intent');
    $DB->Execute('DELETE FROM messageitems WHERE id=1');
    deliveryCheck(!$DB->GetOne('SELECT 1 FROM lnp_sms_delivery WHERE itemid=1'),'LMS message cleanup remains supported');
    echo 'No external requests, temporary tables only.'.PHP_EOL;
}finally{}
