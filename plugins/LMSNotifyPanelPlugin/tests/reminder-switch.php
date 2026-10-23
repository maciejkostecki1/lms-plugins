<?php
require_once __DIR__.'/../lib/NotifyReminderRunner.php';
$calls=0;$fake=static function()use(&$calls){++$calls;return 7;};
ob_start();
if(NotifyReminderRunner::dispatch(false,true,$fake)!==0 || $calls!==0)throw new RuntimeException('Disabled reminder called sender');
if(NotifyReminderRunner::dispatch(true,false,$fake)!==0 || $calls!==0)throw new RuntimeException('Preview called sender');
if(NotifyReminderRunner::dispatch(true,true,$fake)!==7 || $calls!==1)throw new RuntimeException('Enabled reminder did not delegate exactly once');
ob_end_clean();echo "PASS disabled switch prevents delivery; preview prevents delivery; enabled switch delegates once (fake sender only).\n";
