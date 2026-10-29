<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}

$script_name='Isolated manual bulk tests';$script_parameters=[];
require getcwd().'/bin/script-options.php';
require_once __DIR__.'/../lib/NotifyBulk.php';
$db=new NotifyDb($DB);
$schema=file_get_contents(__DIR__.'/../sql/upgrade-0.4.0.pgsql.sql');
$db->Execute(str_replace('CREATE TABLE IF NOT EXISTS ','CREATE TEMP TABLE ',$schema));
$db->Execute(file_get_contents(__DIR__.'/../sql/upgrade-0.4.2.pgsql.sql'));
$db->Execute('ALTER TABLE lnp_bulk_jobs ADD COLUMN start_countdown integer NOT NULL DEFAULT 0');
$db->Execute('CREATE TEMP TABLE lnp_history(id bigserial,created_at bigint,customerid integer,action text,result text,details text,userid integer)');
$fake=new class {
    public int $revision=1;public array $calls=[];public array $historyCalls=[];public array $countdownCalls=[];
    public function settings():array{return ['revision'=>$this->revision];}
    public function act($id,$action,$revision,$hash=null,$automatic=false,$countdown=false):string{
        $this->countdownCalls[$id]=$countdown;
        if($action==='notify_history'){$this->historyCalls[$id]=$hash;return 'Kanały przyjęte do wysyłki: 1';}
        if($action==='notify_sms_no_mail'){$this->calls[]=$id;return 'Kanały przyjęte do wysyłki: 1';}
        if($action!=='notify_manual')throw new LogicException('Bulk must use manual mode');
        $this->calls[]=$id;
        if($id===2)throw new RuntimeException('Customer paid since preview');
        if($id===3)return 'Bez wysyłki: brak zgody.';
        if($id===4)throw new RuntimeException('Simulated uncertain delivery');
        return 'Kanały przyjęte do wysyłki: 1';
    }
};
function fakeBulkHashes(array $ids): array{return array_fill_keys($ids,hash('sha256','approved-fixture'));}
$bulk=new NotifyBulk($DB,$fake,1);$id=$bulk->start([1,2,3,4,1],1,'plain',fakeBulkHashes([1,2,3,4,1]));
if($bulk->status($id)['total']!=4)throw new RuntimeException('Duplicate recipient');
try{(new NotifyBulk($DB,$fake,2))->status($id);throw new LogicException('Other user read job');}catch(RuntimeException $e){}
$r=$bulk->process($id);if(count($fake->calls)!==3 || $r['counts']['queued']!==1)throw new RuntimeException('Batch size wrong');
$r=$bulk->process($id);if($r['status']!=='completed' || $r['counts']['done']!==1 || $r['counts']['skipped']!==1 || $r['counts']['error']!==2)throw new RuntimeException('Results wrong');
$bulk->process($id);if(count($fake->calls)!==4)throw new RuntimeException('Repeated job sent twice');
$id=$bulk->start([5,6],1,'plain',fakeBulkHashes([5,6]));$bulk->process($id,true);if(count($fake->calls)!==4)throw new RuntimeException('Cancel sent');
$id=$bulk->start([7],1,'plain',fakeBulkHashes([7]));$fake->revision=2;$r=$bulk->process($id);if($r['status']!=='stopped'||count($fake->calls)!==4)throw new RuntimeException('Stale settings sent');
$id=$bulk->start([8],2,'plain',fakeBulkHashes([8]));$db->Execute('UPDATE lnp_bulk_items SET status=? WHERE jobid=?',['working',$id]);$r=$bulk->process($id);
if(count($fake->calls)!==4 || $r['counts']['working']!==1)throw new RuntimeException('Interrupted item retried');
echo "PASS fixed recipient list, ownership, batches, paid/consent changes, uncertain delivery, cancellation, stale revision and no duplicate or interrupted retries. No external deliveries.\n";

$hashes=[11=>hash('sha256','client11'),12=>hash('sha256','client12')];
$id=$bulk->start([11,12],2,'history',$hashes,true);
$r=$bulk->process($id);
if($r['mode']!=='history' || $r['counts']['done']!==2 || $fake->historyCalls!==$hashes)throw new RuntimeException('Per-customer history hash/action mismatch');
$bulk->process($id);if(count($fake->historyCalls)!==2)throw new RuntimeException('History job retried');
try{$bulk->start([11,13],2,'history',$hashes);throw new LogicException('Missing preview accepted');}catch(RuntimeException $e){}
echo "PASS individual history hashes survive queued processing; history actions never use another customer attachment; missing previews rejected.\n";

if($fake->countdownCalls[1]!==false || $fake->countdownCalls[11]!==true || $fake->countdownCalls[12]!==true)throw new RuntimeException('Manual countdown selection lost during bulk processing');
echo "PASS manual countdown defaults off and survives confirmed history job processing.\n";

$id=$bulk->start([15],2,'plain',fakeBulkHashes([15]),true);$bulk->process($id);if($fake->countdownCalls[15]!==true)throw new RuntimeException('Plain bulk countdown selection lost');
echo "PASS explicit countdown for plain bulk job.\n";

$id=$bulk->start([21],2,'sms_no_mail',[21=>hash('sha256','sms-preview')]);$bulk->process($id);if($bulk->status($id)['mode']!=='sms_no_mail' || !in_array(21,$fake->calls,true))throw new RuntimeException('SMS mode lost');echo 'PASS SMS mode survives confirmed queue processing'.PHP_EOL;
try{$bulk->start([22],2,'sms_no_mail');throw new LogicException('SMS without preview accepted');}catch(RuntimeException $e){}
