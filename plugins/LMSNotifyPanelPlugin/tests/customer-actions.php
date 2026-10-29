<?php
if(PHP_SAPI!=='cli')exit(1);
class ConfigHelper {public static function checkPrivilege($name){return true;}}
class Auth {public static function GetCurrentUser(){return 1;}}
require_once __DIR__.'/../handlers/NotifyCustomerActionsHandler.php';
$DB=new class {public function GetRow($sql,$args=[]){return strpos($sql,'lnp_history')!==false?null:['payload'=>'{}','revision'=>1,'token'=>''];}};
$LMS=new stdClass();$smarty=new class {public $filter;public function registerFilter($type,$callback,$name){$this->filter=$callback;}};
(new NotifyCustomerActionsHandler())->customer(['customerinfo'=>['id'=>2256],'smarty'=>$smarty]);
if(!is_callable($smarty->filter) || empty($_SESSION['lnp_csrf']))throw new RuntimeException('Hook did not initialize card session and filter');
$customer=2256;$csrf='demo-token';$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$settings=['block_after_days'=>7];$sms=['enabled'=>true,'allow_manual_sms'=>true];
ob_start();require __DIR__.'/../views/customer-actions.php';$panel=ob_get_clean();
foreach(['Powiadom mail','Powiadom z historią','Powiadom SMS','Odliczaj do blokady','name="csrf"','name="op" value="preview"'] as $text)if(strpos($panel,$text)===false)throw new RuntimeException('Missing customer action: '.$text);
if(substr_count($panel,'name="start_countdown"')!==1 || strpos($panel,'checked')!==false)throw new RuntimeException('Countdown must be shared and default off');
$html='<div id="customerinfo-sortable"><table>Original LMS card</table></div>';
$result=NotifyCustomerActionsHandler::inject($html,$panel);
if(strpos($result,'Original LMS card')===false || substr_count($result,'id="lnp-customer-notifications"')!==1)throw new RuntimeException('Card injection failed');
if(NotifyCustomerActionsHandler::inject($result,$panel)!==$result)throw new RuntimeException('Duplicate card panel');
$settings['block_after_days']=0;ob_start();require __DIR__.'/../views/customer-actions.php';$disabled=ob_get_clean();
if(!preg_match('/name="start_countdown"[^>]*disabled/',$disabled))throw new RuntimeException('Zero days must disable checkbox');
echo 'PASS plugin-only customer card, three preview actions, one shared unchecked countdown and idempotent insertion.'.PHP_EOL;
