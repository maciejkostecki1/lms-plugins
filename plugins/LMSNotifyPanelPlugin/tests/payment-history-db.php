<?php
$script_name='Isolated payment history separation';$script_parameters=[];
require getcwd().'/bin/script-options.php';
require_once __DIR__.'/../lib/NotifyPaymentHistory.php';
$db=new NotifyDb($DB);$db->BeginTrans();
try{
    $db->Execute('CREATE TEMP TABLE cash(id integer,customerid integer,time bigint,type integer,value numeric,currency text,currencyvalue numeric,comment text,docid integer)');
    $db->Execute('CREATE TEMP TABLE documents(id integer,fullnumber text)');
    $db->Execute('CREATE TEMP TABLE vcustomerassignments(customerid integer,customergroupid integer)');
    $db->Execute('CREATE TEMP TABLE excludedgroups(userid integer,customergroupid integer)');
    $db->Execute("INSERT INTO cash VALUES(1,11,1,0,-100,'PLN',1,'ONLY_CLIENT_11',1),(2,12,1,0,-200,'PLN',1,'ONLY_CLIENT_12',2)");
    $a=NotifyPaymentHistory::build($DB,['id'=>11,'customername'=>'Test 11']);
    $b=NotifyPaymentHistory::build($DB,['id'=>12,'customername'=>'Test 12']);
    if($a['count']!==1 || $b['count']!==1 || strpos($a['data'],'ONLY_CLIENT_11')===false || strpos($a['data'],'ONLY_CLIENT_12')!==false || strpos($b['data'],'ONLY_CLIENT_11')!==false || strpos($b['data'],'ONLY_CLIENT_12')===false || $a['filename']===$b['filename'])throw new RuntimeException('Customer ledger separation failed');
    echo "PASS separate HTML filename and ledger for each customer; no cross-customer entries. No deliveries.\n";
}finally{$db->RollbackTrans();}
