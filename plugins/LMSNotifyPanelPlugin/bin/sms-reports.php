#!/usr/bin/env php
<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$script_name='LMS SMSAPI reports collector';$script_parameters=['run'=>null,'relay-config:'=>null];
require dirname(__DIR__,3).'/bin/script-options.php';
require_once __DIR__.'/../lib/NotifySmsRelayClient.php';
require_once __DIR__.'/../lib/NotifyPanel.php';
try{
    $config=json_decode(file_get_contents($options['relay-config']??'/etc/lms/smsapi-relay.json'),true,512,JSON_THROW_ON_ERROR);
    $client=new NotifySmsRelayClient($config);$db=new NotifyDb($DB);
    $lock=$db->GetOne('SELECT pg_try_advisory_lock(762321,1)');
    if(!in_array($lock,[true,1,'1','t'],true))throw new RuntimeException('Pobieranie raportów już trwa.');
    $a=null;$s=null;$lms=new LMS($DB,$a,$s);$panel=new NotifyPanel($DB,$lms,0);$delivery=new NotifySmsDelivery($DB);
    $events=$client->request('pull')['events']??null;if(!is_array($events) || count($events)>25)throw new RuntimeException('Nieprawidłowa kolejka raportów.');
    $keys=[];$processed=0;$waiting=0;$ignored=0;
    foreach($events as $event){
        if(!is_array($event) || !is_string($event['key']??null) || !preg_match('/^[a-f0-9]{64}$/D',$event['key']))throw new RuntimeException('Nieprawidłowy raport.');
        $report=NotifySmsRelayProtocol::record($event['report']);
        if(!isset($options['run']))continue;
        $known=$db->GetOne('SELECT 1 FROM lnp_sms_delivery WHERE externalid=? AND idx=? AND number=?',[$report['id'],$report['idx'],$report['number']]);
        if(!$known){
            // Allow an in-flight send to persist its external ID. Old, unrelated reports are not applied.
            if((int)($event['received_at']??time())>time()-3600){++$waiting;continue;}
            ++$ignored;$keys[]=$event['key'];continue;
        }
        try{$delivery->receive([$report],$panel);$keys[]=$event['key'];++$processed;}
        catch(Throwable $e){++$waiting;}
    }
    if($keys)$client->request('ack',$keys);
    echo json_encode(['mode'=>isset($options['run'])?'APPLY REPORTS':'READ ONLY','available'=>count($events),'processed'=>$processed,'waiting'=>$waiting,'unrelated'=>$ignored,'sms_sent'=>0],JSON_THROW_ON_ERROR).PHP_EOL;
    if($waiting)exit(2);
}catch(Throwable $e){fwrite(STDERR,"Pobieranie raportów SMSAPI nie powiodło się. Sprawdź połączenie i prywatną konfigurację.\n");exit(1);}
