<?php
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../lib/NotifyList.php';
function ok($test,$label){if(!$test)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
$rows=[];
for($id=1;$id<=120;++$id)$rows[]=['id'=>$id,'customername'=>'Klient '.$id,'balance'=>-$id,'debt'=>$id,'base'=>10,'percent'=>$id*10,'state'=>'notify'];
$r=NotifyList::page($rows,'debt','desc',1);
ok($r['rows'][0]['id']===120 && end($r['rows'])['id']===71,'global sort before pagination');
$r=NotifyList::page($rows,'debt','desc',2);
ok($r['rows'][0]['id']===70 && end($r['rows'])['id']===21,'page 2 keeps sort');
$r=NotifyList::page($rows,'balance','asc',1);ok($r['rows'][0]['id']===120,'negative balances sort numerically');
$r=NotifyList::page($rows,'balance','desc',1);ok($r['rows'][0]['id']===1,'reverse direction');
$r=NotifyList::page($rows,'injected','desc',999);ok($r['sort']==='id' && $r['page']===3,'invalid sort and excess page bounded');
$rows[]=['id'=>121,'percent'=>null];
foreach(['asc','desc'] as $dir) {$r=NotifyList::page($rows,'percent',$dir,1,200);ok(end($r['rows'])['id']===121,'unknown percent last '.$dir);}
$r=NotifyList::page([['id'=>2,'debt'=>100],['id'=>1,'debt'=>100]],'debt','desc',1);
ok($r['rows'][0]['id']===1,'stable id tie break');
ok(NotifyList::page([],'id','asc',1)['pages']===1,'empty list');
