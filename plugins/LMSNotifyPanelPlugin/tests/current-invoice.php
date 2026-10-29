<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}

require_once __DIR__.'/../lib/NotifyCurrentInvoice.php';
date_default_timezone_set('Europe/Warsaw');
$start=strtotime('2026-10-01');$now=strtotime('2026-10-22 12:00');
$i=['docid'=>10,'cdate'=>strtotime('2026-10-01'),'paytime'=>10,'amount'=>100,'closed'=>0];
$old=['time'=>$start-100,'amount'=>-500,'docid'=>1];
$pay=static fn($amount,$doc=null,$time=null)=>['time'=>$time??$start+100,'amount'=>$amount,'docid'=>$doc];
$cases=[
    'old debt but current paid'=>[[$old,$pay(100)],0],
    'current unpaid'=>[[$old],100],
    'partial payment'=>[[$old,$pay(40)],60],
    'payment linked to old invoice'=>[[$old,$pay(100,1)],100],
    'payment linked to current invoice'=>[[$old,$pay(100,10)],0],
    'advance credit'=>[[$pay(100,null,$start-100)],0],
    'future payment ignored'=>[[$old,$pay(100,null,$now+100)],100],
];
foreach($cases as $name=>[$entries,$expected]){
    $r=NotifyCurrentInvoice::allocate([$i],$entries,$start,$now)[0];
    if($r['unpaid']!=$expected)throw new RuntimeException($name);
    echo 'PASS '.$name.PHP_EOL;
}
$r=NotifyCurrentInvoice::allocate([$i,array_merge($i,['docid'=>11])],[$pay(150)],$start,$now);
if($r[0]['unpaid']!=0 || $r[1]['unpaid']!=50)throw new RuntimeException('One payment used twice');
if(NotifyCurrentInvoice::eligible($i+['unpaid'=>100],3,strtotime('2026-10-13 23:59')))throw new RuntimeException('Too early');
if(!NotifyCurrentInvoice::eligible($i+['unpaid'=>100],3,strtotime('2026-10-14')))throw new RuntimeException('Overdue date');
$i['closed']=1;if(NotifyCurrentInvoice::eligible($i+['unpaid'=>100],3,$now))throw new RuntimeException('Closed invoice selected');
echo "PASS multiple invoices, date boundaries and closed invoice\n";
