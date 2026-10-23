<?php
require_once __DIR__.'/../lib/NotifyBlockNotice.php';
require_once __DIR__.'/../lib/NotifyPolicy.php';
function noticeCheck($ok,$label){if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
$now=1800000000;$notice=['origin'=>'automatic','cycle_key'=>'10','sent_at'=>$now-7*86400,'source_items'=>'[1,2]'];
noticeCheck(!NotifyBlockNotice::evaluate(null,'10',300,7,$now,[1=>2])['ready'],'no notice prevents block');
noticeCheck(!NotifyBlockNotice::evaluate($notice,'10',300,7,$now-1,[1=>2,2=>5])['ready'],'one second before seven days prevents block');
noticeCheck(NotifyBlockNotice::evaluate($notice,'10',300,7,$now,[1=>2,2=>5])['ready'],'exact seven full days permits block');
noticeCheck(!NotifyBlockNotice::evaluate($notice,'10',300,8,$now,[1=>2,2=>5])['ready'],'eight-day setting extends deadline');
foreach([1,3,6,7,8] as $status)noticeCheck(!NotifyBlockNotice::evaluate($notice,'10',300,7,$now,[1=>2,2=>$status])['ready'],'queued/error/cancelled/bounced status '.$status.' prevents block');
noticeCheck(!NotifyBlockNotice::evaluate($notice,'12',300,7,$now,[1=>2,2=>2])['ready'],'new debt after full repayment needs new warning');
noticeCheck(!NotifyBlockNotice::evaluate($notice,'10',0,7,$now,[1=>2,2=>2])['ready'],'paid debt prevents block');
noticeCheck(NotifyPolicy::validate([])['block_after_days']===0,'countdown disabled by default');
foreach([-1,1,6,366,'bad'] as $bad){try{NotifyPolicy::validate(['block_after_days'=>$bad]);throw new LogicException('Invalid delay accepted');}catch(InvalidArgumentException $e){noticeCheck(true,'invalid delay rejected');}}

noticeCheck(NotifyBlockNotice::evaluate($notice,'10',300,7,$now,[1=>2,2=>2])['ready'],'changing delay from eight to seven days recalculates existing warning');

noticeCheck(NotifyPolicy::validate(['block_after_days'=>0])['block_after_days']===0,'zero disables countdown');
noticeCheck(NotifyBlockNotice::evaluate(null,'',300,0,$now,[])['ready'],'disabled countdown permits threshold-only block without warning');
noticeCheck(NotifyBlockNotice::evaluate($notice,'10',300,0,$now,[1=>7])['ready'],'disabled countdown ignores warning delivery state');
noticeCheck(!NotifyBlockNotice::evaluate(null,'',0,0,$now,[])['ready'],'disabled countdown does not permit paid debt block');

$legacy=$notice;unset($legacy['origin']);noticeCheck(!NotifyBlockNotice::evaluate($legacy,'10',300,7,$now,[1=>2,2=>2])['ready'],'unclassified historical warnings do not start automatic countdown');

$c=['debt'=>1,'state'=>'ok'];$s=NotifyPolicy::defaults();
noticeCheck(!NotifyPolicy::internetBlockDue($c,$s,['ready'=>true]),'zero days still requires percentage threshold');
$s['block_after_days']=7;
noticeCheck(NotifyPolicy::internetBlockDue($c,$s,['ready'=>true]),'seven days replaces percentage threshold even for small remaining debt');
noticeCheck(!NotifyPolicy::internetBlockDue($c,$s,['ready'=>false]),'seven days cannot block before deadline');
$c['debt']=0;noticeCheck(!NotifyPolicy::internetBlockDue($c,$s,['ready'=>true]),'repayment prevents timed block');
