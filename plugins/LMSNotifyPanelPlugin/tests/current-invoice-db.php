<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}

// Every table below is temporary and shadows production only in this connection.
$script_name='Current invoice isolated selection';$script_parameters=[];
require getcwd().'/bin/script-options.php';
require_once __DIR__.'/../lib/NotifyCurrentInvoice.php';
date_default_timezone_set('Europe/Warsaw');
$db=new NotifyDb($DB);$db->BeginTrans();
try{
    foreach([
        'documents(id integer,customerid integer,name text,number integer,cdate bigint,paytime integer,closed integer,type integer,numberplanid integer)',
        'customeraddressview(id integer,mailingnotice integer,cutoffstop bigint,divisionid integer,deleted integer,status integer)',
        'divisions(id integer,account text)','numberplans(id integer,template text)',
        'cash(id serial,customerid integer,docid integer,value numeric,currencyvalue numeric,currency text,time bigint)',
        'invoicecontents(docid integer,itemid integer,period integer)'
    ] as $table)$db->Execute('CREATE TEMP TABLE '.$table);
    $db->Execute('INSERT INTO customeraddressview VALUES(1,1,0,1,0,?)',[CSTATUS_CONNECTED]);
    $now=strtotime('2026-10-22');$start=strtotime('2026-10-01');
    foreach([1,2,3,4,5,6] as $id){
        $db->Execute('INSERT INTO documents VALUES(?,1,?,?,?,10,0,?,NULL)',[$id,'Fixture',$id,$id===6?$start-86400:$start,DOC_INVOICE]);
        $db->Execute('INSERT INTO cash(customerid,docid,value,currencyvalue,currency,time) VALUES(1,?,-100,1,?,?)',[$id,$id===5?'EUR':'PLN',$start]);
    }
    $db->Execute('INSERT INTO invoicecontents VALUES(1,1,3),(2,1,0),(3,1,3),(3,2,0),(5,1,3),(6,1,3)');
    $rows=NotifyCurrentInvoice::load($DB,$now);
    if(count($rows)!==1 || (int)$rows[0]['docid']!==1)throw new RuntimeException('Wrong invoice scope');
    $db->Execute('INSERT INTO cash(customerid,docid,value,currencyvalue,currency,time) VALUES(1,NULL,100,1,?,?)',['PLN',$start+86400]);
    $rows=NotifyCurrentInvoice::load($DB,$now);
    if($rows[0]['unpaid']!=0)throw new RuntimeException('Old debt consumed current payment');
    echo "PASS selection excludes old, one-off, mixed, unclassified and foreign invoices; current payment covers subscription despite old debt.\n";
}finally{$db->RollbackTrans();}
