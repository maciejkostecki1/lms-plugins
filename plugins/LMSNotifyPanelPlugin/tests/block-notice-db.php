<?php
$script_name='Isolated durable warning timer tests';$script_parameters=[];
require getcwd().'/bin/script-options.php';
require_once __DIR__.'/../lib/NotifyBlockNotice.php';
$db=new NotifyDb($DB);$db->BeginTrans();
try{
    $db->Execute(str_replace('CREATE TABLE IF NOT EXISTS ','CREATE TEMP TABLE ',file_get_contents(__DIR__.'/../sql/upgrade-0.7.0.pgsql.sql')));
    $db->Execute('CREATE TEMP TABLE lnp_bulk_jobs(id integer)');
    $db->Execute(file_get_contents(__DIR__.'/../sql/upgrade-0.7.2.pgsql.sql'));
    $db->Execute(file_get_contents(__DIR__.'/../sql/upgrade-0.7.3.pgsql.sql'));
    $db->Execute('CREATE TEMP TABLE messageitems(id integer PRIMARY KEY,status integer)');
    $db->Execute('INSERT INTO messageitems VALUES(1,2),(2,2)');
    $n=new NotifyBlockNotice($db);$c=['id'=>1,'debt_cycle'=>'10','debt'=>300];$s=['block_after_days'=>7];$now=time();
    $n->record($c,$s,1,[1],$now-8*86400);$first=$n->status($c,$s,$now);
    if(!$first['ready'])throw new RuntimeException('Expired warning not ready');
    $n->record($c,$s,2,[2],$now);
    if($n->status($c,$s,$now)['sent_at']!==$first['sent_at'])throw new RuntimeException('Reminder reset first deadline');
    $n->record($c,['block_after_days'=>0],3,[2],$now);
    if($n->status($c,$s,$now)['sent_at']!==$first['sent_at'])throw new RuntimeException('Disabled timer reset first warning');
    $n->record($c,$s,3,[2],$now,'manual');
    if($n->status($c,$s,$now)['manual_ready'])throw new RuntimeException('Old automatic warning accelerated manual countdown');
    $db->Execute("UPDATE lnp_block_notices SET sent_at=? WHERE origin='manual'",[$now-7*86400]);
    if(!$n->status($c,$s,$now)['manual_ready'])throw new RuntimeException('Expired manual warning not ready');
    $db->Execute("DELETE FROM lnp_block_notices WHERE origin='manual'");
    $db->Execute('UPDATE messageitems SET status=7 WHERE id=1');
    if($n->status($c,$s,$now)['ready'])throw new RuntimeException('Bounced warning still permits block');
    $n->record($c,$s,2,[2],$now);
    if($n->status($c,$s,$now)['ready'])throw new RuntimeException('New successful warning reused old deadline');
    $c['debt_cycle']='12';
    if($n->status($c,$s,$now+9*86400)['ready'])throw new RuntimeException('Old cycle warning reused');
    echo "PASS persistent first-warning timestamp, repeated reminders, bounced-mail invalidation and new debt cycle.\n";
}finally{$db->RollbackTrans();}
