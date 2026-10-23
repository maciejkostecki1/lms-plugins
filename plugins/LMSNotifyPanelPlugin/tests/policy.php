<?php
if(PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../lib/NotifyPolicy.php';
require_once __DIR__.'/../lib/NotifyDb.php';
$n=0;
function check($ok,$label) { global $n; if(!$ok) throw new RuntimeException('FAIL '.$label); ++$n; echo 'PASS '.$label.PHP_EOL; }
$s=NotifyPolicy::defaults();
$monthly=[['currency'=>'PLN','sum'=>100]];
foreach([[-199.99,'ok'],[-200,'notify'],[-299.99,'notify'],[-300,'block'],[0,'ok'],[100,'ok']] as [$balance,$state]) {
    check(NotifyPolicy::decision($balance,$monthly,$s)['state']===$state,'granica salda '.$balance);
}
check(NotifyPolicy::decision(-150,[['currency'=>'PLN','sum'=>50]],$s)['state']==='block','300% indywidualnego abonamentu');
check(NotifyPolicy::decision(-400,[['currency'=>'PLN','sum'=>50],['currency'=>'PLN','sum'=>150]],$s)['state']==='notify','suma miesięcznych opłat');
check(NotifyPolicy::decision(-1000,[],$s)['state']==='review','brak opłat');
check(NotifyPolicy::decision(-1000,[['currency'=>'PLN','sum'=>0]],$s)['state']==='review','zerowe opłaty');
check(NotifyPolicy::decision(-1000,[['currency'=>'PLN','sum'=>-5]],$s)['state']==='review','ujemne opłaty');
check(NotifyPolicy::decision(-1000,[['currency'=>'EUR','sum'=>100]],$s)['state']==='review','obca waluta');
check(NotifyPolicy::decision(-1000,[['currency'=>'PLN','sum'=>100],['currency'=>'EUR','sum'=>10]],$s)['state']==='review','mieszane waluty');
check(NotifyPolicy::decision(-59.97,[['currency'=>'PLN','sum'=>19.99]],$s)['state']==='block','dokładność groszy');
check(!$s['auto_notify'] && !$s['auto_block'] && !$s['auto_unblock'],'automatyka domyślnie wyłączona');
foreach([['block_percent'=>200],['notify_percent'=>0],['repeat_days'=>0],['channels'=>['shell']],['sender_email'=>'bad'],['subject'=>"x\r\nBcc: bad@example.org"],['body'=>'']] as $bad) {
    try { NotifyPolicy::validate($bad); throw new LogicException('Akceptacja błędnych ustawień'); }
    catch(InvalidArgumentException $e) { check(true,'odrzucono '.key($bad)); }
}
$driver=new class {
    private $errors=[];
    public function GetErrors(){return $this->errors;}
    public function Execute($sql){$this->errors[]=['error'=>'simulated'];return false;}
};
$guard=new NotifyDb($driver);
try {$guard->Execute('bad sql');throw new LogicException('Silent SQL error accepted');}
catch(RuntimeException $e) {check(true,'błąd bazy przerywa działanie');}
echo $n.' testów zaliczonych.'.PHP_EOL;

foreach([[399.99,false],[400,true],[400.01,true]] as [$debt,$due])check(NotifyPolicy::suspendDue(['base'=>100,'debt'=>$debt],$s)===$due,'próg naliczania '.$debt);
try{NotifyPolicy::validate(['suspend_percent'=>299]);throw new LogicException('Lower suspension threshold allowed');}catch(InvalidArgumentException $e){check(true,'próg naliczania nie niższy od blokady');}
check(NotifyPolicy::validate(['block_percent'=>500])['suspend_percent']===500,'stara konfiguracja z wysokim progiem blokady');

check(NotifyPolicy::validate(['block_after_days'=>7,'block_percent'=>500,'suspend_percent'=>300])['suspend_percent']===300,'timed internet block permits independent 300 percent billing threshold');
check(!NotifyPolicy::suspendDue(['base'=>100,'debt'=>400,'overdue_debt'=>0],$s),'billing threshold excludes amounts before invoice deadline');
