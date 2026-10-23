<?php
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../lib/NotifyDebt.php';
function entry($amount,$period=3,$doc=1,$item=1){return ['amount'=>$amount,'period'=>$period,'docid'=>$doc,'itemid'=>$item];}
function debtCheck($ok,$label){if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
$r=NotifyDebt::split([entry(-100),entry(-500,0,2)]);debtCheck($r['recurring']==100.0&&$r['oneoff']==500.0,'one-off excluded from subscription debt');
$r=NotifyDebt::split([entry(-100),entry(-500,0,2),entry(100,null,null)]);debtCheck($r['recurring']==0.0&&$r['oneoff']==500.0,'unallocated payment settles oldest charge');
$r=NotifyDebt::split([entry(-100),entry(-500,0,2),entry(500,null,2)]);debtCheck($r['recurring']==100.0&&$r['oneoff']==0.0,'document-linked payment settles own invoice');
$r=NotifyDebt::split([entry(-500,0,2),entry(500,null,null),entry(-300)]);debtCheck($r['recurring']==300.0&&$r['oneoff']==0.0,'historic paid one-off does not reduce future subscription debt');
$r=NotifyDebt::split([entry(100,null,null),entry(-50)]);debtCheck($r['recurring']==0.0&&$r['balance']==50.0,'prepayment');
$r=NotifyDebt::split([entry(-100,null)]);debtCheck($r['unknown']==100.0,'unclassified charge requires review');


$first=array_merge(entry(-100),['ledger_id'=>10]);$pay=array_merge(entry(100,null,null),['ledger_id'=>11]);$next=array_merge(entry(-200),['ledger_id'=>12]);
$r=NotifyDebt::split([$first,$pay,$next]);debtCheck($r['cycle_key']==='12','full repayment starts a new warning cycle even between worker runs');
$r=NotifyDebt::split([$first,array_merge(entry(20,null,null),['ledger_id'=>11])]);debtCheck($r['cycle_key']==='10','partial repayment preserves warning cycle');

$tz=new DateTimeZone('Europe/Warsaw');$issued=(new DateTimeImmutable('2026-10-01 13:00:00',$tz))->getTimestamp();
$deadline=(new DateTimeImmutable('2026-10-08 00:00:00',$tz))->getTimestamp();
$charge=array_merge(entry(-100),['invoice_cdate'=>$issued,'invoice_paytime'=>6]);
$r=NotifyDebt::split([$charge],$deadline-1);debtCheck($r['overdue_recurring']===0.0,'invoice excluded through end of payment deadline day');
$r=NotifyDebt::split([$charge],$deadline);debtCheck($r['overdue_recurring']===100.0,'invoice included from midnight after payment deadline');
$r=NotifyDebt::split([$charge,entry(40,null,null)],$deadline);debtCheck($r['overdue_recurring']===60.0,'partial payment reduces overdue numerator');
$future=array_merge(entry(-200,3,2),['invoice_cdate'=>$issued,'invoice_paytime'=>20]);
$r=NotifyDebt::split([$charge,$future,entry(50,null,null)],$deadline);debtCheck($r['overdue_recurring']===50.0 && $r['recurring']===250.0,'FIFO separates overdue from not-yet-due recurring debt');
$r=NotifyDebt::split([$charge,entry(100,null,1)],$deadline);debtCheck($r['overdue_recurring']===0.0,'payment before deadline prevents overdue debt');
