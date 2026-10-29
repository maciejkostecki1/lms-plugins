<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$script_name='Isolated plugin schema tests';$script_parameters=[];
require getcwd().'/bin/script-options.php';
require_once __DIR__.'/../lib/NotifyDb.php';
$db=new NotifyDb($DB);$db->BeginTrans();
try{
    $db->Execute('CREATE TEMP TABLE messageitems(id integer PRIMARY KEY)');
    $sql=file_get_contents(__DIR__.'/../sql/install.pgsql.sql');
    $sql=str_replace('CREATE TABLE ','CREATE TEMP TABLE ',$sql);$sql=str_replace('CREATE TEMP TABLE IF NOT EXISTS ','CREATE TEMP TABLE ',$sql);
    $db->Execute('CREATE TEMP TABLE lnp_runtime(name varchar(64) PRIMARY KEY,value text)');
    $sql=preg_replace('/CREATE INDEX .*?;/s','',$sql);$db->Execute($sql);
    foreach(['0.4.0','0.4.2','0.7.0','0.7.2','0.7.3','0.8.0','0.8.1','0.8.6'] as $version){
        $sql=file_get_contents(__DIR__.'/../sql/upgrade-'.$version.'.pgsql.sql');
        $sql=str_replace('CREATE TABLE IF NOT EXISTS ','CREATE TEMP TABLE IF NOT EXISTS ',$sql);
        $sql=preg_replace('/CREATE INDEX .*?;/s','',$sql);$db->Execute($sql);
    }
    $sql=file_get_contents(__DIR__.'/../sql/upgrade-0.8.6.pgsql.sql');$sql=str_replace('CREATE TABLE IF NOT EXISTS ','CREATE TEMP TABLE IF NOT EXISTS ',$sql);$sql=preg_replace('/CREATE INDEX .*?;/s','',$sql);$db->Execute($sql);
    if(!$db->GetOne('SELECT value FROM lnp_runtime WHERE name=?',['invoice_catchup_from']))throw new RuntimeException('Missing safe schedule activation');
    echo "PASS complete schema install, upgrades and repeated 0.8.6 migration; temporary tables only.\n";
}finally{$db->RollbackTrans();}
