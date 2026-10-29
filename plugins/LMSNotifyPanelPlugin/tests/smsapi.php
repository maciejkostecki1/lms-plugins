<?php
if(PHP_SAPI!=='cli')exit(1);
define('MSG_ERROR',3);define('MSG_SENT',2);define('MSG_DELIVERED',5);define('MSG_NEW',1);
require_once __DIR__.'/../lib/NotifySmsApi.php';
function checkSms($ok,$label){if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
$called=0;$params=[];$options=['enabled'=>true,'token'=>'fake-test-token','sender'=>'Test'];
$transport=function($p,$token)use(&$called,&$params){++$called;$params=$p;return ['http'=>200,'body'=>json_encode(['list'=>[['id'=>'test-id','status'=>'QUEUE']]])];};
$r=NotifySmsApi::send('48500000000','Test','123',['enabled'=>false],$transport);
checkSms($called===0 && $r['status']===MSG_ERROR,'disabled integration makes no request');
$r=NotifySmsApi::send('48500000000','Test','123',['enabled'=>true],$transport);
checkSms($called===0 && $r['status']===MSG_ERROR,'missing token makes no request');
$r=NotifySmsApi::send('44500000000','Test','123',$options,$transport);
checkSms($called===0 && $r['status']===MSG_ERROR,'foreign number blocked');
$r=NotifySmsApi::send('+48 500 000 000','Test','123',$options,$transport);
checkSms($called===1 && $r['status']===MSG_NEW && $r['id']==='test-id','queued SMS stays queued, not sent or delivered');
checkSms($params['to']==='48500000000' && $params['max_parts']===1 && $params['check_idx']===1,'normalization, single-part cost cap and deduplication');
$idx=$params['idx'];NotifySmsApi::send('48500000000','Test','123',$options,$transport);
checkSms($params['idx']===$idx,'stable deduplication key for same LMS item');
$before=$called;
NotifySmsApi::send('48500000000','Test '.str_repeat('a',161),'length',$options,$transport);
NotifySmsApi::send('48abc500000000','Test','invalid',$options,$transport);
checkSms($before===$called,'oversized message and letters in number rejected before network');
checkSms(NotifySmsApi::normalizeNumber('500 000 000')==='48500000000','local Polish number normalized');
$custom=str_repeat('a',64);NotifySmsApi::send('48500000000','Test','custom',$options+['idx'=>$custom],$transport);
checkSms($params['idx']===$custom,'durable callback and deduplication key forwarded');
$url='https://net-24h.pl/smsapi-reports/receive.php';
NotifySmsApi::send('48500000000','Test','callback',$options+['notify_url'=>$url,'dry_run'=>true],$transport);
checkSms($params['notify_url']===$url && $params['test']===1,'per-message callback configured even in no-send API validation');
$before=$called;NotifySmsApi::send('48500000000','Test','callback-invalid',$options+['notify_url'=>'http://example.invalid/receiver.php'],$transport);
checkSms($called===$before,'non-HTTPS callback rejected before network');
foreach(['ACCEPTED'=>MSG_NEW,'SENT'=>MSG_SENT,'DELIVERED'=>MSG_DELIVERED,'FAILED'=>MSG_ERROR] as $state=>$status){
 $r=NotifySmsApi::send('48500000000','Test','123',$options,static fn()=>['http'=>200,'body'=>json_encode(['list'=>[['id'=>'x','status'=>$state]]])]);
 checkSms($r['status']===$status,'gateway status '.$state);
}
foreach([['http'=>401,'body'=>'{"error":101}'],['http'=>200,'body'=>'not json'],['http'=>200,'body'=>'{"list":[]}']] as $reply){
 $r=NotifySmsApi::send('48500000000','Test','123',$options,static fn()=>$reply);checkSms($r['status']===MSG_ERROR,'API failure rejected');
}
$r=NotifySmsApi::send('48500000000','Test','123',$options,static function(){throw new RuntimeException('fake-test-token secret');});
checkSms(strpos(json_encode($r),'fake-test-token')===false,'transport exceptions do not leak credentials');
require_once __DIR__.'/../handlers/NotifySmsApiHandler.php';
$d=['service'=>'smstools','number'=>'x'];checkSms((new NotifySmsApiHandler)->send($d)===$d,'other SMS services unchanged');
echo 'No external requests or SMS sent.'.PHP_EOL;
