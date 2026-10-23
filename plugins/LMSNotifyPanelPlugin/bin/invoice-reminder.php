#!/usr/bin/env php
<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$script_name='Przełącznik przypomnienia przed terminem';$script_parameters=['run'=>null];
$script_help="Bez --run: tylko sprawdzenie stanu, bez wysyłki.\n";
$root=dirname(__DIR__,3);
require $root.'/bin/script-options.php';
require_once __DIR__.'/../lib/NotifyReminderRunner.php';
require_once __DIR__.'/../lib/NotifyDb.php';
require_once __DIR__.'/../lib/NotifyInvoiceSchedule.php';
try{
    $schedule=new NotifyInvoiceSchedule($DB);
    if(isset($options['run']) && !$schedule->due(time()))exit(0);
    // Read the global panel override directly and reject ambiguous configuration.
    $db=new NotifyDb($DB);
    $rows=$db->GetAll('SELECT value FROM uiconfig WHERE section=? AND var=? AND userid IS NULL AND divisionid IS NULL AND configid IS NULL AND disabled=0',['notify-reminder','lnp_enabled'])?:[];
    if(count($rows)>1)throw new RuntimeException('Niejednoznaczny przełącznik przypomnienia.');
    $enabled=$rows?(string)$rows[0]['value']==='1':ConfigHelper::checkConfig('notify-reminder.lnp_enabled',true);
    exit(NotifyReminderRunner::dispatch($enabled,isset($options['run']),static function()use($root,$options,$schedule){
        return $schedule->reminderOnce(time(),static function()use($root,$options){
        // Delegate to the existing LMS implementation, without copying its delivery logic.
        $process=proc_open([PHP_BINARY,'-d','error_reporting=E_ERROR','-d','display_errors=0',
            $root.'/bin/lms-notify.php','-C',$options['config-file']??'/etc/lms/lms.ini',
            '-s','notify-reminder','-t','reminder'],[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes,$root);
        if(!is_resource($process))throw new RuntimeException('Nie uruchomiono przypomnienia LMS.');
        return proc_close($process);
        });
    }));
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
