<?php
if(PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../lib/NotifyPolicy.php';
date_default_timezone_set('Europe/Warsaw');
$settings=NotifyPolicy::defaults(); $settings['revision']=1;
$error='';$notice='Podgląd demonstracyjny — fikcyjne dane. Przyciski nie wykonują operacji.';$preview=null;$search=0;$history=[];
$_SESSION=['lnp_csrf'=>'demo'];
$statistics=['debtors'=>57,'block'=>15,'remaining'=>42,'invalid_messages'=>0,'sms'=>['customers'=>20,'numbers'=>21,'segments'=>21,'rate'=>'0.08','cost'=>1.68]];
$rows=[];
foreach([[1001,'Anna Kowalska',100,-200],[1002,'Firma Przykładowa',150,-450],[1003,'Jan Nowak',80,0],[1004,'Klient bez abonamentu',0,-100]] as [$id,$name,$base,$balance]) {
    $c=['id'=>$id,'customername'=>$name,'balance'=>$balance,'excluded'=>false,'exception'=>'','owned_blocks'=>0,'mail_ready'=>true,'no_email'=>false,'sms_ready'=>false];
    $rows[]=array_merge($c,NotifyPolicy::decision($balance,[['currency'=>'PLN','sum'=>$base]],$settings));
}
$e=static function($v) {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');};
ob_start(); require __DIR__.'/../views/panel.php'; $html=ob_get_clean();
if(strpos($html,'200%')===false || strpos($html,'300%')===false || strpos($html,'Do blokady')===false) throw new RuntimeException('Missing UI content');
if(strpos($html,'Zadłużeni abonamentowo')===false || strpos($html,'1,68 zł')===false || strpos($html,'lnp-statistics')>strpos($html,'name="op" value="bulk_preview"'))throw new RuntimeException('Missing global statistics above send buttons');
preg_match('/<style>(.*?)<\/style>/s',$html,$panelStyle);
if(preg_match('/#[0-9a-fA-F]{3,8}\b/',$panelStyle[1]??'')) throw new RuntimeException('Panel must inherit LMS colours');
if(preg_match('/<button(?![^>]*class="lms-ui-button)/',$html)) throw new RuntimeException('Non-native button style');
if(in_array('--write-preview',$argv,true)) {
    echo '<!doctype html><html lang="pl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="lms-style.css"><title>Płatności i blokady — podgląd</title><body style="margin:1em">';
    // Static downloadable demo: no forms can submit against any service.
    echo preg_replace('/<form\b[^>]*>/','<form onsubmit="return false">',$html);
    echo '</body></html>';
    exit;
}
$rows[0]['customername']='<script>alert("test")</script>';
ob_start(); require __DIR__.'/../views/panel.php'; $escaped=ob_get_clean();
if(strpos($escaped,'<script>alert')!==false || strpos($escaped,'&lt;script&gt;')===false) throw new RuntimeException('HTML escape failure');
echo "PASS rendered dashboard, thresholds, action buttons and HTML escaping.\n";
foreach(['settings'=>'name="notify_percent"','invoices'=>'Powiadomienia o fakturach','exceptions'=>'name="reason"','history'=>'Historia operacji'] as $tab=>$expected) {
    ob_start();require __DIR__.'/../views/panel.php';$tabHtml=ob_get_clean();
    if(strpos($tabHtml,$expected)===false || strpos($tabHtml,'CAD-DRUK')!==false)throw new RuntimeException('Tab render failure: '.$tab);
    if(strpos($tabHtml,'<h3 class="lms-ui-box-header">Klienci</h3>')!==false)throw new RuntimeException('Customer table leaked into tab');
    echo 'PASS tab '.$tab.PHP_EOL;
}
$tab='customers';$bulkPreview=['rows'=>[array_merge($rows[0],['customername'=>'<script>bad</script>','channels'=>['mail']])],
    'skipped'=>['excluded'=>1,'review'=>2,'contact'=>3],'token'=>'test','message'=>['subject'=>'Saldo','body'=>'<script>bad</script>']];
ob_start();require __DIR__.'/../views/panel.php';$bulkHtml=ob_get_clean();
if(strpos($bulkHtml,'Potwierdź wysyłkę e-mail do 1 klientów')===false || strpos($bulkHtml,'<script>bad</script>')!==false || strpos($bulkHtml,'Wyślij powiadomienie do wszystkich zadłużonych')===false)throw new RuntimeException('Bulk preview rendering failed');
echo "PASS bulk preview requires explicit confirmation and escapes recipient/message content.\n";

require_once __DIR__.'/../lib/NotifyPaymentHistory.php';
$bulkPreview=null;$preview=['customer'=>$rows[0],'action'=>'notify_history','token'=>'demo','subject'=>'Saldo','body'=>'Historia w załączniku','attachment'=>NotifyPaymentHistory::render($rows[0],[]),'recipients'=>['test@example.invalid']];
ob_start();require __DIR__.'/../views/panel.php';$historyHtml=ob_get_clean();
if(strpos($historyHtml,'Powiadom z historią')===false || strpos($historyHtml,'Potwierdź wysyłkę z historią płatności')===false || strpos($historyHtml,'sandbox=""')===false || strpos($historyHtml,'test@example.invalid')===false)throw new RuntimeException('History preview/confirmation failed');
echo "PASS history button, isolated attachment preview, recipient list and explicit confirmation.\n";

if(strpos($historyHtml,'name="action" value="notify_history"')===false || strpos($historyHtml,'name="action"')===false || strpos($historyHtml,'class="lms-ui-button" name="action"')!==false)throw new RuntimeException('Operation must survive programmatic form submit without submitter');
$preview=null;$bulkPreview=['mode'=>'history','rows'=>[array_merge($rows[0],['channels'=>['mail'],'attachment_name'=>'historia-platnosci-1001.html','attachment_count'=>5])],'skipped'=>['excluded'=>0,'review'=>0,'contact'=>0],'token'=>'test'];
ob_start();require __DIR__.'/../views/panel.php';$bulkHistoryHtml=ob_get_clean();
foreach(['Powiadom wszystkich zadłużonych z historią płatności','Podgląd historii klienta #1001','Potwierdź wysyłkę z osobnymi historiami płatności','historia-platnosci-1001.html','Automatyka progów i blokad'] as $text)if(strpos($bulkHistoryHtml,$text)===false)throw new RuntimeException('Missing bulk history UI: '.$text);
echo "PASS form action as hidden field, bulk history preview per customer, separate confirmation and clear automation label.\n";

$tab='exceptions';$customerSelector='<div class="lms-ui-customer-select-container" data-version="2"><input name="customerid" form="lnp-exception-form"><input placeholder="Szukaj klienta LMS"></div>';
ob_start();require __DIR__.'/../views/panel.php';$selectorHtml=ob_get_clean();
if(strpos($selectorHtml,'id="lnp-exception-form"')===false || strpos($selectorHtml,'lms-ui-customer-select-container')===false || strpos($selectorHtml,'Wyszukaj klienta LMS')===false)throw new RuntimeException('Customer selector not embedded in exception form');
echo "PASS native customer selector slot and explicit exception form owner.\n";
$tab='customers';$preview=null;$bulkPreview=null;
$rows[0]['customer_notes']="Deklaracja wpłaty 25.10\n<script>alert('notes')</script>";
$rows[0]['customer_info']='SMS: czas na wpłatę do 17.06 <script>bad</script>';
$rows[0]['notification_status']='Wysłano powiadomienie z historią';$rows[0]['notification_time']=time();
$rows[0]['last_note']='Telefon: potwierdzono termin';$rows[0]['last_note_time']=time();$rows[0]['email_status']='Brak e-maila';
ob_start();require __DIR__.'/../views/panel.php';$detailsHtml=ob_get_clean();
if(strpos($detailsHtml,'Deklaracja wpłaty 25.10')===false || strpos($detailsHtml,'SMS: czas na wpłatę do 17.06')===false || strpos($detailsHtml,'<script>bad</script>')!==false || strpos($detailsHtml,'Brak e-maila')===false || strpos($detailsHtml,'Telefon: potwierdzono termin')===false || strpos($detailsHtml,"<script>alert('notes')</script>")!==false)throw new RuntimeException('Customer notes/email rendering failed');
echo "PASS notes, latest dated note and missing-email label render safely.\n";
$rows[0]['excluded']=true;$rows[0]['panel_exception']=true;$rows[0]['lms_protected']=false;$rows[0]['exception']='Deklaracja wpłaty — wyjątek testowy';
ob_start();require __DIR__.'/../views/panel.php';$exceptionHtml=ob_get_clean();
if(strpos($exceptionHtml,'Usuń wyjątek')===false || strpos($exceptionHtml,'Zmień wyjątek')===false || strpos($exceptionHtml,'name="return_tab" value="customers"')===false)throw new RuntimeException('Exception action buttons missing');
preg_match('/<tr><td>'.(int)$rows[0]['id'].'<\/td>(.*?)<\/tr>/s',$exceptionHtml,$exceptionRow);
preg_match_all('/<td>(.*?)<\/td>/s',$exceptionRow[1]??'',$exceptionCells);
$cells=$exceptionCells[1]??[];
if(!$cells || strpos($cells[0],'Deklaracja wpłaty — wyjątek testowy')!==false || strpos(end($cells),'Deklaracja wpłaty — wyjątek testowy')===false)throw new RuntimeException('Exception description must be in action column');
$rows[0]['panel_exception']=false;$rows[0]['lms_protected']=true;
ob_start();require __DIR__.'/../views/panel.php';$protectedHtml=ob_get_clean();
if(strpos($protectedHtml,'Usuń wyjątek')!==false)throw new RuntimeException('LMS protection must not offer plugin deletion');
echo "PASS own exception description in actions, edit/delete buttons, return-to-list fields and no delete for LMS-only protection.\n";

$tab='settings';ob_start();require __DIR__.'/../views/panel.php';$noticeSettings=ob_get_clean();
if(strpos($noticeSettings,'name="block_after_days"')===false || strpos($noticeSettings,'Zablokuj po … dniach')===false)throw new RuntimeException('Missing warning countdown setting');
echo "PASS configurable warning-to-block delay field.\n";

$settings['block_after_days']=0;ob_start();require __DIR__.'/../views/panel.php';$disabledHtml=ob_get_clean();
if(!preg_match('/name="block_after_days"[^>]*min="0"/',$disabledHtml))throw new RuntimeException('Disabled countdown UI missing');
echo "PASS disabled countdown UI.\n";

$tab='customers';$settings['block_after_days']=0;ob_start();require __DIR__.'/../views/panel.php';$disabledHtml=ob_get_clean();
if(substr_count($disabledHtml,'name="start_countdown"')!==3 || substr_count($disabledHtml,'value="1" disabled')<3)throw new RuntimeException('Manual countdown controls must be separate and disabled at zero days');
$settings['block_after_days']=7;ob_start();require __DIR__.'/../views/panel.php';$enabledHtml=ob_get_clean();
if(preg_match('/name="start_countdown"[^>]*(checked|disabled)/',$enabledHtml))throw new RuntimeException('Manual countdown must be enabled but unchecked at seven days');
echo "PASS independent manual countdown controls default off and respect zero days.\n";

$preview=null;$bulkPreview=null;$tab='customers';$rows=[$rows[0]];$rows[0]['excluded']=false;$rows[0]['state']='notify';$rows[0]['debt']=200;
foreach([[true,false,true,'notify_sms_no_mail'],[false,true,false,'notify_manual'],[false,false,false,'none']] as [$noEmail,$mailReady,$smsReady,$wanted]){
 $rows[0]['no_email']=$noEmail;$rows[0]['mail_ready']=$mailReady;$rows[0]['sms_ready']=$smsReady;
 ob_start();require __DIR__.'/../views/panel.php';$html=ob_get_clean();
 foreach(['notify_sms_no_mail','notify_manual','notify_history'] as $action){
  $expected=$wanted==='notify_manual'?in_array($action,['notify_manual','notify_history']):$action===$wanted;
  if((strpos($html,'name="action" value="'.$action.'"')!==false)!==$expected)throw new RuntimeException('Wrong customer action: '.$action);
 }
}
echo 'PASS SMS without email, email/history only with valid permissions; no email actions without consent'.PHP_EOL;

$tab='settings';ob_start();require __DIR__.'/../views/panel.php';$allSettings=ob_get_clean();
foreach(['Ustawienia wtyczki','Bramka SMSAPI','name="token" value=""','Test połączenia — bez wysyłki SMS','name="sms_body"'] as $needle)if(strpos($allSettings,$needle)===false)throw new RuntimeException('Missing unified setting '.$needle);
echo 'PASS unified plugin settings, blank token field and no-send connection test'.PHP_EOL;

if(strpos($allSettings,'name="op" value="invoice_settings"')!==false || strpos($allSettings,'name="send_time"')!==false)throw new RuntimeException('Invoice settings leaked into debt settings');
$invoiceProfiles=[['label'=>'Faktura przed terminem','schedule'=>'Wyłączone','section'=>'notify-reminder','enabled'=>false,'type'=>'reminder','days'=>2,'sender_email'=>'test@example.invalid','sender_name'=>'Test','subject'=>'Faktura %invoice','message'=>'Kwota %value']];
$currentInvoices=[];$invoiceSendTime='09:30';$tab='invoices';
ob_start();require __DIR__.'/../views/panel.php';$invoiceHtml=ob_get_clean();
foreach(['name="op" value="invoice_settings"','name="op" value="invoice_schedule"','value="09:30"','Faktura %invoice','Bieżące faktury — podgląd bez wysyłki'] as $needle)if(strpos($invoiceHtml,$needle)===false)throw new RuntimeException('Missing invoice setting '.$needle);
foreach(['class="lmsbox lnp-summary"','name="block_percent"','name="sms_body"','name="token"'] as $needle)if(strpos($invoiceHtml,$needle)!==false)throw new RuntimeException('Debt setting leaked into invoices '.$needle);
echo 'PASS separate invoice tab contains templates, schedule and invoice list; debt settings remain separate'.PHP_EOL;
