<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Cache-Control: no-store');header('Content-Type: application/json');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
try{
    if((int)($_SERVER['CONTENT_LENGTH']??0)>8192)throw new InvalidArgumentException('Too large');
    $body=file_get_contents('php://input',false,null,0,8193);if(strlen($body)>8192)throw new InvalidArgumentException('Too large');
    $input=json_decode($body,true,512,JSON_THROW_ON_ERROR);$op=$input['op']??'';
    if(!in_array($op,['pull','ack'],true))throw new InvalidArgumentException('Invalid operation');
    require __DIR__.'/config-location.php';
    $config=json_decode(file_get_contents(LNP_RELAY_CONFIG),true,512,JSON_THROW_ON_ERROR);
    require_once __DIR__.'/RelayQueue.php';$queue=new RelayQueue($config['database']);
    $nonce=$_SERVER['HTTP_X_LNP_NONCE']??'';
    if(!$queue->authorize($op,$_SERVER['HTTP_X_LNP_TIME']??'',$nonce,$body,$_SERVER['HTTP_X_LNP_SIGNATURE']??'',$config['secret'])){http_response_code(403);exit;}
    $out=$op==='pull'?['events'=>$queue->pull()]:['acked'=>$queue->ack($input['keys']??[])];
    $reply=json_encode($out,JSON_THROW_ON_ERROR);
    header('X-LNP-Signature: '.hash_hmac('sha256',$nonce."\n".$reply,$config['secret']));echo $reply;
}catch(InvalidArgumentException $e){http_response_code(400);echo '{"error":"Invalid request"}';}
catch(Throwable $e){http_response_code(503);echo '{"error":"Unavailable"}';error_log('[SMS relay] exchange unavailable');}
