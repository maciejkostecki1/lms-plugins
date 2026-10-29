#!/usr/bin/env php
<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$script_name='Bieżące faktury abonamentowe';$script_parameters=['apply'=>null,'profile:'=>'p:'];
$script_help="Bez --apply: tylko podgląd. --profile=notify-overdue lub notify-overdue-7.\n";
require dirname(__DIR__,3).'/bin/script-options.php';
require_once __DIR__.'/../lib/NotifyCurrentInvoice.php';
require_once __DIR__.'/../lib/NotifyInvoiceSettings.php';
require_once __DIR__.'/../lib/NotifyInvoiceSchedule.php';
date_default_timezone_set('Europe/Warsaw');
try{
    $section=$options['profile']??$options['p']??'notify-overdue';
    if(!in_array($section,['notify-overdue','notify-overdue-7'],true))throw new RuntimeException('Nieznany profil.');
    $profile=null;foreach((new NotifyInvoiceSettings($DB,0))->profiles() as $p)if($p['section']===$section)$profile=$p;
    if(isset($options['apply']) && empty($profile['enabled'])){echo "Automatyczna wysyłka profilu wyłączona w panelu. Nic nie wysłano.\n";exit;}
    if(isset($options['apply']) && !(new NotifyInvoiceSchedule($DB))->due(time()))exit(0);
    $db=new NotifyDb($DB);$AUTH=null;$SYSLOG=SYSLOG::getInstance();$LMS=new LMS($DB,$AUTH,$SYSLOG);
    $run=function()use($DB,$db,$LMS,$section,$profile,$options){
    $now=time();$count=0;
    foreach(NotifyCurrentInvoice::load($DB,$now) as $i){
        if(!NotifyCurrentInvoice::eligible($i,(int)$profile['days'],$now))continue;
        $id=(int)$i['customerid'];$doc=(int)$i['docid'];$action='invoice_'.$section.'_'.$doc;
        if(!isset($options['apply'])){printf("#%d faktura #%d do zapłaty %.2f PLN\n",$id,$doc,$i['unpaid']);++$count;continue;}
        // Share the customer lock with threshold notifications and billing operations.
        $lock=$db->GetOne('SELECT pg_try_advisory_lock(762319,?)',[$id]);
        if(!in_array($lock,[true,1,'1','t'],true))continue;
        try{
            // Recheck current payment state immediately before sending.
            $fresh=null;foreach(NotifyCurrentInvoice::load($DB,time(),$id) as $r)if((int)$r['docid']===$doc)$fresh=$r;
            if(!$fresh || !NotifyCurrentInvoice::eligible($fresh,(int)$profile['days'],time()) || !$fresh['mailingnotice'] || (int)$fresh['cutoffstop']>time())continue;
            if($db->GetOne('SELECT 1 FROM lnp_exclusions WHERE customerid=? AND (until_time=0 OR until_time>?)',[$id,time()]))continue;
            if($db->GetOne('SELECT 1 FROM lnp_history WHERE customerid=? AND action=? AND result IN (?,?)',[$id,$action,'pending','sent']))continue;
            $flag=CONTACT_EMAIL|CONTACT_NOTIFICATIONS|CONTACT_INVOICES;
            $contacts=$db->GetCol('SELECT DISTINCT contact FROM customercontacts WHERE customerid=? AND (type & ?)=?',[$id,$flag|CONTACT_DISABLED,$flag])?:[];
            $contacts=array_values(array_filter($contacts,static fn($v)=>filter_var($v,FILTER_VALIDATE_EMAIL)!==false));
            if(!$contacts)continue;
            if(!filter_var($profile['sender_email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Brak poprawnego nadawcy.');
            // Re-read the switch from the database, bypassing cached ConfigHelper settings.
            if($db->GetOne('SELECT value FROM uiconfig WHERE section=? AND var=? AND userid IS NULL AND divisionid IS NULL AND configid IS NULL AND disabled=0',[$section,'lnp_enabled'])!=='1')continue;
            $subject=str_replace(["\r","\n"],' ',NotifyCurrentInvoice::render($profile['subject'],$fresh));$body=NotifyCurrentInvoice::render($profile['message'],$fresh);
            $event=$db->GetOne('INSERT INTO lnp_history(created_at,customerid,action,result,details,userid) VALUES(?,?,?,?,?,0) RETURNING id',[time(),$id,$action,'pending','Rozpoczęto wysyłkę bieżącej faktury #'.$doc.'. Nie ponawiać bez sprawdzenia historii LMS.']);
            $ok=true;
            foreach($contacts as $to){
                $m=$db->checked(fn()=>$LMS->addMessage(['type'=>MSG_MAIL,'subject'=>$subject,'body'=>$body,'userid'=>null,
                    'sender'=>['name'=>$profile['sender_name'],'mail'=>$profile['sender_email']],
                    'recipients'=>[['id'=>$id,'email'=>$to,'status'=>MSG_ERROR,'error'=>'Wysyłka w toku']]]));
                $item=$m['items'][$id][$to];
                $result=$LMS->SendMail($to,['From'=>$profile['sender_email'],'To'=>$to,'Subject'=>$subject,'Reply-To'=>$profile['sender_email']],$body);
                $status=is_int($result)?$result:(is_array($result)?(int)($result['status']??MSG_ERROR):MSG_ERROR);
                $error=is_string($result)?$result:(is_array($result)?implode(', ',$result['errors']??[]):'');
                $db->Execute('UPDATE messageitems SET status=?,lastdate=?NOW?,error=? WHERE id=?',[$status,$error?:null,$item]);
                if($status===MSG_ERROR)$ok=false;
            }
            $db->Execute('UPDATE lnp_history SET result=?,details=? WHERE id=?',[$ok?'sent':'pending','Faktura #'.$doc.($ok?' — przyjęta do wysyłki.':' — sprawdź wynik w LMS.'),$event]);
            if($ok)++$count;
        }finally{$db->GetOne('SELECT pg_advisory_unlock(762319,?)',[$id]);}
    }
    echo 'Liczba faktur: '.$count.(isset($options['apply'])?' (wysyłka).':' (podgląd, nic nie wysłano).').PHP_EOL;
    return 0;
    };
    if(isset($options['apply']))exit((new NotifyInvoiceSchedule($DB))->reminderOnce(time(),$run,$section));
    $run();
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
