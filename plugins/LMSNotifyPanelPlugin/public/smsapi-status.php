<?php
declare(strict_types=1);
// No session or SMSAPI credential is exposed by this endpoint. Do not trust proxy headers.
ini_set('display_errors','0');
header('Cache-Control: no-store');header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__.'/../lib/NotifySmsRelayProtocol.php';
$ip=str_replace('::ffff:','',$_SERVER['REMOTE_ADDR']??'');
if(!in_array($ip,NotifySmsRelayProtocol::SOURCES,true)){http_response_code(403);exit;}
// Provider validates a callback address before enabling delivery reports.
if(empty($_GET) && empty($_POST)){echo 'OK';exit;}
try{
    define('CONFIG_FILE','/etc/lms/lms.ini');
    $CONFIG=parse_ini_file(CONFIG_FILE,true,INI_SCANNER_RAW);
    if(!$CONFIG)throw new RuntimeException('Configuration unavailable');
    define('SYS_DIR',$CONFIG['directories']['sys_dir']??dirname(__DIR__,3));
    define('LIB_DIR',$CONFIG['directories']['lib_dir']??SYS_DIR.'/lib');
    require_once SYS_DIR.'/vendor/autoload.php';
    $DB=LMSDB::getInstance();
    require_once LIB_DIR.'/common.php';
    require_once LIB_DIR.'/language.php';
    require_once LIB_DIR.'/definitions.php';
    require_once __DIR__.'/../lib/NotifySmsDelivery.php';
    require_once __DIR__.'/../lib/NotifyPanel.php';
    $AUTH=null;$SYSLOG=null;$LMS=new LMS($DB,$AUTH,$SYSLOG);
    $reports=NotifySmsDelivery::reports($_SERVER['REQUEST_METHOD']==='POST'?$_POST:$_GET);
    (new NotifySmsDelivery($DB))->receive($reports,new NotifyPanel($DB,$LMS,0));
    echo 'OK';
}catch(InvalidArgumentException $e){http_response_code(400);echo 'Invalid report';}
catch(Throwable $e){http_response_code(503);echo 'Retry later';error_log('[NotifySmsDelivery] report processing failed');}
