<?php
require_once __DIR__ . '/../lib/NotifyPanel.php';
require_once __DIR__ . '/../lib/NotifyList.php';
require_once __DIR__ . '/../lib/NotifyInvoiceSettings.php';
require_once __DIR__ . '/../lib/NotifyCurrentInvoice.php';
require_once __DIR__ . '/../lib/NotifyInvoiceSchedule.php';
require_once __DIR__ . '/../lib/NotifyBulk.php';
require_once __DIR__.'/../lib/NotifySmsSettings.php';
$layout['pagetitle']='Płatności i blokady';
if (!ConfigHelper::checkPrivilege('full_access') || (int)Auth::GetCurrentUser()<1) {
    http_response_code(403); die('Panel wymaga uprawnień administratora LMS.');
}
if (session_status()===PHP_SESSION_NONE) session_start();
if (session_status()!==PHP_SESSION_ACTIVE) throw new RuntimeException('Brak bezpiecznej sesji.');
if (empty($_SESSION['lnp_csrf'])) $_SESSION['lnp_csrf']=bin2hex(random_bytes(32));
$np=new NotifyPanel($DB,$LMS,(int)Auth::GetCurrentUser());
$bulk=new NotifyBulk($DB,$np,(int)Auth::GetCurrentUser());$bulkPreview=null;$bulkJob=null;$bulkAuto=false;
$error=''; $notice=$_SESSION['lnp_flash']??''; unset($_SESSION['lnp_flash']); $preview=null;
$e=static function($v) { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); };
$tab=in_array($_GET['tab']??'', ['customers','settings','invoices','exceptions','history'],true)?$_GET['tab']:'customers';
$search=max(0,(int)($_GET['customerid']??0));
$sort=in_array($_GET['sort']??'',NotifyList::COLUMNS,true)?$_GET['sort']:'percent';
$direction=($_GET['direction']??'desc')==='desc'?'desc':'asc';
$page=max(1,(int)($_GET['page']??1));
$rows=[];$history=[];$exceptions=[];$pagination=null;$snapshotTime=null;
$invoiceProfiles=[];
try {
    $settings=$np->settings();$smsSettings=(new NotifySmsSettings($DB))->settings();
    if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        if (!is_string($_POST['csrf']??null) || !hash_equals($_SESSION['lnp_csrf'],$_POST['csrf'])) throw new RuntimeException('Sesja wygasła. Odśwież panel.');
        $op=$_POST['op']??'';
        $tab=in_array($op,['invoice_settings','invoice_schedule','smsapi_settings','smsapi_test'],true)?'settings':($op==='settings'?'settings':($op==='exception'?'exceptions':'customers'));
        if($op==='bulk_process'){
            $result=$bulk->process((int)($_POST['jobid']??0));
            header('Content-Type: application/json; charset=UTF-8');echo json_encode($result,JSON_THROW_ON_ERROR);exit;
        } elseif($op==='bulk_cancel'){
            $jobid=(int)($_POST['jobid']??0);$bulk->process($jobid,true);
            $_SESSION['lnp_flash']='Przerwano pozostałą część wysyłki.';header('Location: ?m=notifypanel&bulkjob='.$jobid);exit;
        } elseif($op==='bulk_attachment_preview'){
            $draft=$_SESSION['lnp_bulk_confirm']??null;$customer=(int)($_POST['customerid']??0);
            if(!$draft || ($draft['mode']??'plain')!=='history' || !is_string($_POST['token']??null) || !hash_equals($draft['token'],$_POST['token']) || $draft['expires']<time() || !in_array($customer,$draft['ids'],true))throw new RuntimeException('Podgląd załącznika wygasł lub klient nie należy do tej wysyłki.');
            $c=$np->inspect($customer);if(!$c)throw new RuntimeException('Brak aktywnego klienta.');
            $attachment=NotifyPaymentHistory::build($DB,$c);
            if(!hash_equals($draft['hashes'][$customer],$attachment['hash']))throw new RuntimeException('Historia klienta zmieniła się. Przygotuj nowy podgląd zbiorczy.');
            header('Content-Type: text/html; charset=UTF-8');header('Cache-Control: no-store');
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
            echo $attachment['data'];exit;
        } elseif(in_array($op,['bulk_preview','bulk_history_preview','bulk_sms_preview'],true)){
            $historyMode=$op==='bulk_history_preview';$smsMode=$op==='bulk_sms_preview';$previewSettings=$settings;
            $previewSettings['channels']=$smsMode?['sms']:['mail'];
            if($historyMode)$previewSettings['channels']=['mail'];
            $bulkPreview=$np->manualPreview($previewSettings,$smsMode);$token=bin2hex(random_bytes(24));$hashes=[];
            $bulkPreview['mode']=$historyMode?'history':($smsMode?'sms_no_mail':'plain');
            $bulkPreview['start_countdown']=!empty($_POST['start_countdown']) && (int)$settings['block_after_days']>0;
            if($historyMode)foreach($bulkPreview['rows'] as &$row){
                $attachment=NotifyPaymentHistory::build($DB,$row);
                $hashes[(int)$row['id']]=$attachment['hash'];
                $row['attachment_name']=$attachment['filename'];$row['attachment_count']=$attachment['count'];
            }
            unset($row);
            $_SESSION['lnp_bulk_confirm']=['token'=>$token,'ids'=>array_map('intval',array_column($bulkPreview['rows'],'id')),'revision'=>$settings['revision'],'expires'=>time()+600,'mode'=>$bulkPreview['mode'],'hashes'=>$hashes,'start_countdown'=>$bulkPreview['start_countdown']];
            $bulkPreview['token']=$token;
            if($bulkPreview['rows'])$bulkPreview['message']=$np->messagePreview($bulkPreview['rows'][0],$previewSettings);
            if($historyMode && isset($bulkPreview['message']))$bulkPreview['message']['body'].="\n\nW załączniku przesyłamy historię płatności i rozliczeń Państwa konta.";
        } elseif($op==='bulk_start'){
            $draft=$_SESSION['lnp_bulk_confirm']??null;unset($_SESSION['lnp_bulk_confirm']);
            if(!$draft || !is_string($_POST['token']??null) || !hash_equals($draft['token'],$_POST['token']) || $draft['expires']<time())throw new RuntimeException('Podgląd zbiorczy wygasł lub został już wykorzystany.');
            $jobid=$bulk->start($draft['ids'],$draft['revision'],$draft['mode']??'plain',$draft['hashes']??[],!empty($draft['start_countdown']));$_SESSION['lnp_bulk_auto']=$jobid;
            header('Location: ?m=notifypanel&bulkjob='.$jobid);exit;
        } elseif($op==='smsapi_settings'){
            (new NotifySmsSettings($DB))->save($_POST,(int)Auth::GetCurrentUser());$notice='Zapisano ustawienia SMSAPI. Automatyczne SMS-y są wyłączone.';
        } elseif($op==='smsapi_test'){
            $notice=(new NotifySmsSettings($DB))->test();
        } elseif ($op==='settings') {
            $input=[];
            foreach(NotifyPolicy::defaults() as $key=>$value) {
                $input[$key]=strpos($key,'auto_')===0?isset($_POST[$key]):($_POST[$key]??($key==='channels'?[]:$value));
            }
            $np->save($input,(int)($_POST['revision']??0)); $notice='Zapisano ustawienia.';
        } elseif($op==='invoice_schedule') {
            (new NotifyInvoiceSchedule($DB))->save((string)($_POST['send_time']??''),(int)Auth::GetCurrentUser());
            $notice='Zapisano wspólną godzinę powiadomień. Stany włączenia profili bez zmian.';
        } elseif($op==='invoice_settings') {
            (new NotifyInvoiceSettings($DB,(int)Auth::GetCurrentUser()))->save((string)($_POST['section']??''),$_POST);
            $notice='Zapisano szablon powiadomienia. Harmonogram bez zmian.';
        } elseif($op==='exception') {
            $np->exclude((int)($_POST['customerid']??0),(int)($_POST['days']??0),(string)($_POST['reason']??''));
            $notice='Zapisano wyjątek.';
            if((int)($_POST['days']??0)===0)$notice='Usunięto wyjątek wtyczki.';
            if(($_POST['return_tab']??'')==='customers')$tab='customers';
        } elseif($op==='preview') {
            $action=(string)($_POST['action']??'');
            if (!in_array($action,['notify','notify_manual','notify_history','notify_sms_no_mail','block','unblock'],true)) throw new RuntimeException('Nie rozpoznano działania przycisku. Odśwież listę i spróbuj ponownie.');
            $c=$np->inspect((int)($_POST['customerid']??0));
            if (!$c) throw new RuntimeException('Brak aktywnego klienta.');
            $token=bin2hex(random_bytes(24));
            $_SESSION['lnp_confirm']=array('token'=>$token,'id'=>(int)$c['id'],'action'=>$action,
                'revision'=>$settings['revision'],'expires'=>time()+600);
            $preview=['customer'=>$c,'action'=>$action,'token'=>$token];
            if($action==='block')$preview['block_notice']=$np->blockNoticeStatus($c,$settings);
            if(in_array($action,['notify','notify_manual','notify_history','notify_sms_no_mail'],true)){
                $messageSettings=$settings;$messageSettings['channels']=$action==='notify_sms_no_mail'?['sms']:['mail'];
                if($action==='notify_sms_no_mail' && $np->hasEmailContact((int)$c['id']))throw new RuntimeException('Klient ma wpisany adres e-mail.');
                if(!$np->notificationTargets($c,$messageSettings))throw new RuntimeException('Brak właściwego kontaktu, zgody lub wymagany odstęp od ostatniej wysyłki.');
                $preview=array_merge($preview,$np->messagePreview($c,$messageSettings));
            }
            if($action==='notify_history'){
                $preview['attachment']=NotifyPaymentHistory::build($DB,$c);
                $_SESSION['lnp_confirm']['history_hash']=$preview['attachment']['hash'];
                $mailSettings=$settings;$mailSettings['channels']=['mail'];
                $preview['recipients']=$np->notificationTargets($c,$mailSettings)['mail']??[];
                $preview['body'].="\n\nW załączniku przesyłamy historię płatności i rozliczeń Państwa konta.";
            }
        } elseif($op==='execute') {
            $draft=$_SESSION['lnp_confirm']??null;
            unset($_SESSION['lnp_confirm']);
            if (!$draft || !is_string($_POST['token']??null) || !hash_equals($draft['token'],$_POST['token']) || $draft['expires']<time()) throw new RuntimeException('Podgląd wygasł lub został już wykorzystany.');
            $notice=$np->act($draft['id'],$draft['action'],$draft['revision'],$draft['history_hash']??null);
        } else throw new RuntimeException('Nieznana operacja.');
        if(!in_array($op,['preview','bulk_preview','bulk_history_preview'],true)) {
            unset($_SESSION['lnp_list']);
            $_SESSION['lnp_flash']=$notice;
            $redirect=['m'=>'notifypanel','tab'=>$tab];
            if($op==='exception' && $tab==='customers'){
                $redirect['sort']=in_array($_POST['return_sort']??'',NotifyList::COLUMNS,true)?$_POST['return_sort']:'percent';
                $redirect['direction']=($_POST['return_direction']??'')==='asc'?'asc':'desc';
                $redirect['page']=max(1,(int)($_POST['return_page']??1));
                if((int)($_POST['return_customerid']??0)>0)$redirect['customerid']=(int)$_POST['return_customerid'];
            }
            header('Location: ?'.http_build_query($redirect));exit;
        }
    }
    $settings=$np->settings();$smsSettings=(new NotifySmsSettings($DB))->settings();
    if(!empty($_GET['bulkjob'])){
        $bulkJob=$bulk->status((int)$_GET['bulkjob']);
        $bulkAuto=($_SESSION['lnp_bulk_auto']??0)===(int)$bulkJob['id'];unset($_SESSION['lnp_bulk_auto']);
    }
    if($tab==='customers') {
        $bulkJobs=$bulk->recent();
        if($search)$allRows=array_values(array_filter([$np->inspect($search)]));
        else {
            $cached=$_SESSION['lnp_list']??null;
            if(!$cached || ($cached['fees_version']??0)!==7 || $cached['revision']!==$settings['revision'] || $cached['time']<time()-60 || isset($_GET['refresh'])) {
                $cached=['fees_version'=>7,'revision'=>$settings['revision'],'time'=>time(),'rows'=>$np->customerSnapshot()];
                $_SESSION['lnp_list']=$cached;
            }
            $allRows=$cached['rows'];$snapshotTime=$cached['time'];
        }
        $pagination=NotifyList::page($allRows,$sort,$direction,$page);
        $rows=$np->customerListDetails($pagination['rows']);
    } elseif($tab==='history')$history=$np->historyList();
    elseif($tab==='exceptions')$exceptions=$np->exceptionList();
    elseif(in_array($tab,['invoices','settings'],true)){
        $invoiceProfiles=(new NotifyInvoiceSettings($DB,(int)Auth::GetCurrentUser()))->profiles();
        $invoiceSendTime=(new NotifyInvoiceSchedule($DB))->time();
        if($tab==='invoices')$currentInvoices=array_values(array_filter(NotifyCurrentInvoice::load($DB,time()),static fn($i)=>(float)$i['unpaid']>0.009 && empty($i['closed'])));
    }
} catch(Throwable $ex) {
    if(($_POST['op']??'')==='bulk_process'){
        http_response_code(400);header('Content-Type: application/json; charset=UTF-8');echo json_encode(['error'=>$ex->getMessage()]);exit;
    }
    $error=$ex instanceof PDOException?'Nie udało się odczytać danych wtyczki. Sprawdź instalację i dziennik serwera.':$ex->getMessage();
    error_log('[NotifyPanel] '.get_class($ex).': '.$ex->getMessage());
}
$customerSelector='';
if(in_array($tab,['customers','exceptions'],true)){
    $SMARTY->assign('lnp_selector_form',$tab==='exceptions'?'lnp-exception-form':'lnp-customer-filter');
    $SMARTY->assign('lnp_selector_input',$tab==='exceptions'?'lnp-exception-customer':'lnp-filter-customer');
    $SMARTY->assign('lnp_selector_selected',(string)($tab==='exceptions'?max(0,(int)($_POST['customerid']??$_GET['customerid']??0)):$search));
    $SMARTY->assign('lnp_selector_required',$tab==='exceptions');
    $customerSelector=$SMARTY->fetch('notifypanel/customer-select.html');
}
ob_start();
require __DIR__ . '/../views/panel.php';
$SMARTY->assign('panel_html',ob_get_clean());
$SMARTY->display('notifypanel/panel.html');
