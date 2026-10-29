<?php
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../lib/NotifySmsRelayClient.php';
// Test the hosting implementation in a separate directory with a throwaway SQLite DB.
$dir=sys_get_temp_dir().'/lnp-relay-test-'.bin2hex(random_bytes(8));mkdir($dir,0700);
copy(__DIR__.'/../relay/RelayQueue.php',$dir.'/RelayQueue.php');copy(__DIR__.'/../lib/NotifySmsRelayProtocol.php',$dir.'/NotifySmsRelayProtocol.php');
require $dir.'/RelayQueue.php';
function relayCheck($ok,$label){if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
try{
    $queue=new RelayQueue($dir.'/test.sqlite');$secret=str_repeat('1',64);
    $r=['id'=>'fixtureid','idx'=>str_repeat('a',64),'number'=>'48600000000','state'=>'DELIVERED','date'=>time()];
    $queue->store([$r,$r]);$events=$queue->pull();relayCheck(count($events)===1,'provider retries deduplicated in durable queue');
    $body='{"op":"pull"}';$stamp=(string)time();$nonce=bin2hex(random_bytes(16));$sig=NotifySmsRelayProtocol::signature('pull',$stamp,$nonce,$body,$secret);
    relayCheck(!$queue->authorize('pull',$stamp,$nonce,$body,str_repeat('0',64),$secret),'bad request signature rejected');
    relayCheck($queue->authorize('pull',$stamp,$nonce,$body,$sig,$secret),'valid signed request accepted');
    relayCheck(!$queue->authorize('pull',$stamp,$nonce,$body,$sig,$secret),'replayed request rejected');
    $transport=static function($body,$headers,$nonce)use($queue,$secret){$response=json_encode(['events'=>$queue->pull()]);return ['http'=>200,'body'=>$response,'signature'=>hash_hmac('sha256',$nonce."\n".$response,$secret)];};
    $client=new NotifySmsRelayClient(['url'=>'https://example.invalid/exchange.php','secret'=>$secret],$transport);
    relayCheck(count($client->request('pull')['events'])===1,'client validates signed response');
    $bad=new NotifySmsRelayClient(['url'=>'https://example.invalid/exchange.php','secret'=>$secret],static fn()=>['http'=>200,'body'=>'{}','signature'=>str_repeat('0',64)]);
    try{$bad->request('pull');throw new LogicException('Unsigned response accepted');}catch(RuntimeException $e){relayCheck(strpos($e->getMessage(),$secret)===false,'invalid reply rejected without exposing secret');}
    relayCheck($queue->ack([$events[0]['key']])===1 && !$queue->pull(),'report removed from pending queue only after acknowledgement');
    $queue->store([$r]);relayCheck(!$queue->pull(),'callback retry after acknowledgement stays deduplicated');
    $parsed=NotifySmsRelayProtocol::callback(['MsgId'=>'fixtureid','status'=>'404','idx'=>str_repeat('a',64),'to'=>'+48 600 000 000','donedate'=>(string)$r['date']]);
    relayCheck($parsed===[$r],'SMSAPI payload normalized and validated');
    echo 'No external requests or subscriber messages.'.PHP_EOL;
}finally{foreach(glob($dir.'/*')?:[] as $file)unlink($file);rmdir($dir);}
