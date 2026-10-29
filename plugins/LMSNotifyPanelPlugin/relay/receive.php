<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Cache-Control: no-store');header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__.'/NotifySmsRelayProtocol.php';
$ip=str_replace('::ffff:','',$_SERVER['REMOTE_ADDR']??'');
if(!in_array($ip,NotifySmsRelayProtocol::SOURCES,true)){http_response_code(403);exit;}
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)){http_response_code(405);exit;}
if(empty($_GET) && empty($_POST)){echo 'OK';exit;}
try{
    if(strlen($_SERVER['QUERY_STRING']??'')>8192 || (int)($_SERVER['CONTENT_LENGTH']??0)>8192)throw new InvalidArgumentException('Too large');
    require __DIR__.'/config-location.php';
    $config=json_decode(file_get_contents(LNP_RELAY_CONFIG),true,512,JSON_THROW_ON_ERROR);
    require_once __DIR__.'/RelayQueue.php';
    $reports=NotifySmsRelayProtocol::callback($_SERVER['REQUEST_METHOD']==='POST'?$_POST:$_GET);
    (new RelayQueue($config['database']))->store($reports);
    echo 'OK';
}catch(InvalidArgumentException $e){http_response_code(400);echo 'Invalid report';}
catch(Throwable $e){http_response_code(503);echo 'Retry later';error_log('[SMS relay] storage unavailable');}
