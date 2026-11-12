<?php
require_once __DIR__.'/../lib/KsefErsteExport.php';
$userid=(int)Auth::GetCurrentUser();
$divisions=$DB->GetAll('SELECT d.id,d.name FROM divisions d JOIN userdivisions ud ON ud.divisionid=d.id WHERE ud.userid=? ORDER BY d.id',[$userid])?:[];
$allowed=array_map('intval',array_column($divisions,'id'));
$divisionId=(int)($_REQUEST['division']??($allowed[0]??0));
if(!$userid || !in_array($divisionId,$allowed,true)){http_response_code(403);exit('Brak dostępu do podmiotu.');}
$dateFrom=str_replace('/','-',trim((string)($_REQUEST['date_from']??date('Y-m-01',strtotime('first day of last month')))));
$dateTo=str_replace('/','-',trim((string)($_REQUEST['date_to']??date('Y-m-t',strtotime('first day of last month')))));
$from=DateTimeImmutable::createFromFormat('!Y-m-d',$dateFrom);$to=DateTimeImmutable::createFromFormat('!Y-m-d',$dateTo);
if(!$from || !$to || $from->format('Y-m-d')!==$dateFrom || $to->format('Y-m-d')!==$dateTo || $from>$to){http_response_code(400);exit('Nieprawidłowy zakres dat.');}
$start=$from->getTimestamp();$end=$to->modify('+1 day')->getTimestamp();$page=max(1,min(10000,(int)($_REQUEST['page']??1)));
$csrf=$SESSION->get('ksef_payment_csrf');
if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$SESSION->save('ksef_payment_csrf',$csrf);}
$views=$SESSION->get('kpe_views');if(!is_array($views))$views=[];
foreach($views as $key=>$view)if(($view['expires']??0)<time())unset($views[$key]);
$drafts=$SESSION->get('kpe_drafts');if(!is_array($drafts))$drafts=[];
foreach($drafts as $key=>$draft)if(($draft['expires']??0)<time())unset($drafts[$key]);
$exports=$SESSION->get('kpe_exports');if(!is_array($exports))$exports=[];
$error='';$preview=null;$selected=[];$addresses=[];$mpp=[];
$sender=kpe_nrb(ConfigHelper::getConfig('ksef_erste.sender_nrb_'.$divisionId,''))?:'';
$statusReady=(int)$DB->GetOne("SELECT CASE WHEN to_regclass('net24_ksef_erste_export_status') IS NULL THEN 0 ELSE 1 END")===1;
$fields='i.id,i.division_id,i.invoice_number,i.seller_name,i.gross_amount,i.vat_amount,i.currency,i.bank_account,i.issue_date,i.seller_ten,i.settled,i.pay_type,i.ksef_payment_date,'
    .($statusReady?'es.last_exported_at':'NULL AS last_exported_at');
$scope=' FROM ksefinvoices i JOIN userdivisions ud ON ud.divisionid=i.division_id'
    .($statusReady?' LEFT JOIN net24_ksef_erste_export_status es ON es.division_id=i.division_id AND es.invoice_id=i.id':'')
    .' WHERE i.division_id=? AND ud.userid=? AND i.issue_date>=? AND i.issue_date<?';
$lastExport=static fn(array $row): int => max((int)($row['last_exported_at']??0),(int)($exports[$divisionId.':'.$row['id']]??0));
$load=function(array $ids)use($DB,$fields,$scope,$divisionId,$userid,$start,$end){
    $rows=$DB->GetAll('SELECT '.$fields.$scope.' AND i.id IN ? ORDER BY i.id',[$divisionId,$userid,$start,$end,$ids])?:[];
    $out=[];foreach($rows as $row)$out[(int)$row['id']]=$row;
    if(count($out)!==count($ids))throw new RuntimeException('Wybrane faktury zmieniły zakres lub uprawnienia. Odśwież listę.');
    return $out;
};
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{
        if(!is_string($_POST['csrf_token']??null)||!hash_equals($csrf,$_POST['csrf_token']))throw new RuntimeException('Niepoprawny token CSRF. Odśwież stronę.');
        if(!$statusReady)throw new RuntimeException('Zainstaluj migrację rejestru eksportu zgodnie z README.');
        if($sender==='')throw new RuntimeException('Brak poprawnego NRB nadawcy dla wybranego podmiotu.');
        $action=$_POST['action']??'';
        if($action==='preview'){
            $selected=kpe_ids($_POST['invoice_ids']??null);$view=$views[(string)($_POST['view_token']??'')]??null;
            if(!$view||$view['division']!==$divisionId||$view['from']!==$dateFrom||$view['to']!==$dateTo)throw new RuntimeException('Lista wygasła. Odśwież ją i wybierz faktury ponownie.');
            $rawMpp=$_POST['mpp_ids']??[];if(!is_array($rawMpp))throw new RuntimeException('Niepoprawny wybór MPP.');
            $mpp=$rawMpp?kpe_ids($rawMpp):[];if(array_diff($mpp,$selected))throw new RuntimeException('MPP wybrano poza eksportem.');
            if(!is_array($_POST['recipient_address']??null))throw new RuntimeException('Brak adresów odbiorców.');
            $rows=$load($selected);$hashes=[];$lines=[];$total=0;$items=[];$warnings=false;$exportTimes=[];
            foreach($selected as $id){
                $invoice=$rows[$id];$hash=kpe_fingerprint($invoice,$sender);
                if(kpe_is_paid($invoice))throw new RuntimeException('Faktura jest już oznaczona jako zapłacona. Eksport zablokowany.');
                if(!isset($view['hashes'][$id])||!hash_equals($view['hashes'][$id],$hash))throw new RuntimeException('Dane faktury lub rachunek nadawcy zmieniły się od wyświetlenia listy. Odśwież listę.');
                $value=$_POST['recipient_address'][$id]??'';if(!is_string($value))throw new RuntimeException('Niepoprawny adres odbiorcy.');
                $addresses[$id]=trim($value);$isMpp=in_array($id,$mpp,true);
                $lines[]=kpe_export_line($sender,$invoice,$addresses[$id],$isMpp);$hashes[$id]=$hash;$total+=kpe_cents((string)$invoice['gross_amount']);
                $invoice['export_address']=$addresses[$id]===''?'.':$addresses[$id];$invoice['mpp']=$isMpp;$invoice['export_account']=kpe_nrb($invoice['bank_account']);
                $exportTimes[$id]=$lastExport($invoice);$invoice['previous_export']=$exportTimes[$id]>0;
                $warnings=$warnings||$invoice['previous_export'];$items[]=$invoice;
            }
            kpe_bytes($lines);$token=bin2hex(random_bytes(32));
            $drafts[$token]=['ids'=>$selected,'hashes'=>$hashes,'addresses'=>$addresses,'mpp'=>$mpp,'division'=>$divisionId,'from'=>$dateFrom,'to'=>$dateTo,'expires'=>time()+600,'warnings'=>$warnings,'export_times'=>$exportTimes];
            if(count($drafts)>5)$drafts=array_slice($drafts,-5,null,true);
            $SESSION->save('kpe_drafts',$drafts);
            $preview=['token'=>$token,'items'=>$items,'sender'=>$sender,'total'=>number_format($total/100,2,',',' '),'warnings'=>$warnings];
        }elseif($action==='export'){
            $token=$_POST['draft_token']??null;$draft=is_string($token)?($drafts[$token]??null):null;
            if(!$draft||!isset($draft['export_times'])||$draft['division']!==$divisionId||$draft['from']!==$dateFrom||$draft['to']!==$dateTo)throw new RuntimeException('Podgląd wygasł lub został już wykorzystany. Przygotuj go ponownie.');
            if($draft['warnings']&&($_POST['allow_repeat']??'')!=='1')throw new RuntimeException('Potwierdź świadomy ponowny eksport faktur.');
            $rows=$load($draft['ids']);$lines=[];
            foreach($draft['ids'] as $id){
                if(kpe_is_paid($rows[$id]))throw new RuntimeException('Faktura jest już oznaczona jako zapłacona. Eksport zablokowany.');
                if($lastExport($rows[$id])!==$draft['export_times'][$id])throw new RuntimeException('Od podglądu zarejestrowano inny eksport. Przygotuj nowy podgląd.');
                if(!hash_equals($draft['hashes'][$id],kpe_fingerprint($rows[$id],$sender)))throw new RuntimeException('Dane faktury zmieniły się po podglądzie. Nie wygenerowano pliku.');
                $lines[]=kpe_export_line($sender,$rows[$id],$draft['addresses'][$id],in_array($id,$draft['mpp'],true));
            }
            $bytes=kpe_bytes($lines);
            $filename='ERSTE_'.$divisionId.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.txt';
            if(headers_sent())throw new RuntimeException('Nie można rozpocząć pobierania pliku TXT.');
            $params=[];$exportedAt=time();
            foreach($draft['ids'] as $id)array_push($params,$divisionId,$id,$exportedAt);
            if($DB->Execute(
                'INSERT INTO net24_ksef_erste_export_status (division_id, invoice_id, last_exported_at) VALUES '
                .implode(', ',array_fill(0,count($draft['ids']),'(?, ?, ?)'))
                .' ON CONFLICT (division_id, invoice_id) DO UPDATE SET last_exported_at = '
                .'GREATEST(net24_ksef_erste_export_status.last_exported_at, EXCLUDED.last_exported_at)', $params
            )===false)throw new RuntimeException('Nie zapisano statusu eksportu. Plik nie został wysłany.');
            unset($drafts[$token]);$SESSION->save('kpe_drafts',$drafts);$SESSION->close();
            header('Content-Type: text/plain; charset=windows-1250');header('Content-Disposition: attachment; filename="'.$filename.'"');
            header('Content-Length: '.strlen($bytes));header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');echo $bytes;exit;
        }else throw new RuntimeException('Nieznana operacja.');
    }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'Nie udało się przygotować eksportu. Odśwież listę.';}
}
// Pagination is applied to validated rows, not to unvalidated SQL candidates.
$invoices=[];$offset=0;$eligible=0;$skipped=0;$hasNext=false;$skip=($page-1)*200;
do{
    $chunk=$DB->GetAll('SELECT '.$fields.$scope.' AND i.gross_amount>0 AND UPPER(TRIM(i.currency))=? ORDER BY i.issue_date DESC,i.id DESC LIMIT 250 OFFSET '.(int)$offset,[$divisionId,$userid,$start,$end,'PLN'])?:[];
    foreach($chunk as $invoice){
        if(!kpe_eligible($invoice)){++$skipped;continue;}
        if($eligible++<$skip)continue;
        if(count($invoices)===200){$hasNext=true;break 2;}
        $invoice['normalized_account']=kpe_nrb($invoice['bank_account']);
        $invoice['kpe_paid']=kpe_is_paid($invoice);
        $invoice['kpe_payment_method']=kpe_payment_method($invoice);
        $invoice['kpe_payment_status']=kpe_payment_status($invoice);
        $exportedAt=$lastExport($invoice);
        $invoice['kpe_exported_at']=$exportedAt>0?date('d.m.Y H:i:s',$exportedAt):'';
        $invoices[]=$invoice;
    }
    $offset+=count($chunk);
}while(count($chunk)===250);
$viewToken=bin2hex(random_bytes(24));$hashes=[];foreach($invoices as $invoice)$hashes[(int)$invoice['id']]=kpe_fingerprint($invoice,$sender);
$views[$viewToken]=['division'=>$divisionId,'from'=>$dateFrom,'to'=>$dateTo,'expires'=>time()+900,'hashes'=>$hashes];if(count($views)>5)$views=array_slice($views,-5,null,true);$SESSION->save('kpe_views',$views);
foreach(['divisions'=>$divisions,'division_id'=>$divisionId,'date_from'=>$dateFrom,'date_to'=>$dateTo,'invoices'=>$invoices,'csrf'=>$csrf,'error'=>$error,'preview'=>$preview,'view_token'=>$viewToken,'page'=>$page,'has_next'=>$hasNext,'skipped'=>$skipped,'selected'=>$selected,'addresses'=>$addresses,'mpp'=>$mpp] as $key=>$value)$SMARTY->assign('ksef_payment_'.$key,$value);
$SMARTY->assign('ksef_payment_status_ready', $statusReady);
$SMARTY->display('ksef/ksefpaymentexport.html');
