<?php
$script_name='Isolated invoice clock tests';$script_parameters=[];
require getcwd().'/bin/script-options.php';
require_once __DIR__.'/../lib/NotifyInvoiceSchedule.php';
foreach(['00:00','08:00','23:59'] as $t)if(!NotifyInvoiceSchedule::valid($t))throw new RuntimeException('Valid time rejected');
foreach(['24:00','8:00','08:60','08:00:00',"08:00\n",''] as $t)if(NotifyInvoiceSchedule::valid($t))throw new RuntimeException('Invalid time accepted');
$tz=new DateTimeZone('Europe/Warsaw');
foreach(['2026-07-15 08:00','2026-12-15 08:00'] as $t){
    $now=(new DateTimeImmutable($t,$tz))->getTimestamp();
    if(!NotifyInvoiceSchedule::atTime('08:00',$now)||NotifyInvoiceSchedule::atTime('09:00',$now))throw new RuntimeException('Polish time mismatch');
}
$db=new NotifyDb($DB);
$db->Execute('CREATE TEMP TABLE uiconfig(id serial,section text,var text,value text,description text,disabled integer,userid integer,divisionid integer,configid integer)');
$db->Execute('CREATE TEMP TABLE lnp_history(id serial,created_at bigint,customerid integer,action varchar(32),result varchar(32),details text,userid integer NOT NULL DEFAULT 0)');
$s=new NotifyInvoiceSchedule($DB);
if($s->time()!=='08:00')throw new RuntimeException('Wrong default');
$s->save('10:45',1);if($s->time()!=='10:45')throw new RuntimeException('Save failed');
$s->save('08:00',1);if($s->time()!=='08:00')throw new RuntimeException('Update failed');
$calls=0;$fake=static function()use(&$calls){++$calls;return 0;};
$now=(new DateTimeImmutable('2026-10-22 08:00',$tz))->getTimestamp();
$s->reminderOnce($now,$fake);$s->reminderOnce($now,$fake);$s->reminderOnce($now+3600,$fake);
if($calls!==1)throw new RuntimeException('Repeated daily delivery');
$s->reminderOnce($now+86400,$fake);if($calls!==2)throw new RuntimeException('Next day not allowed');
$failed=$now+172800;
try{$s->reminderOnce($failed,static function(){throw new RuntimeException('Simulated interrupted sender');});}catch(RuntimeException $e){if($e->getMessage()!=='Simulated interrupted sender')throw $e;}
$s->reminderOnce($failed,$fake);if($calls!==2)throw new RuntimeException('Interrupted day repeated');
echo "PASS clock validation, summer/winter Polish time, save/update, one reminder run per day and interrupted-run protection. TEMP tables and fake sender only.\n";
