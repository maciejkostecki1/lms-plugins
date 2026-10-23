<?php
declare(strict_types=1);
require_once __DIR__.'/NotifyDb.php';
final class NotifyPaymentHistory
{
    public static function build($database,array $customer): array
    {
        $db=new NotifyDb($database);$id=(int)$customer['id'];
        if($id<1)throw new RuntimeException('Nieprawidłowy klient historii.');
        // Same cash ledger and currency conversion as the native customer balance report.
        $rows=$db->GetAll('SELECT c.id,c.time,c.type,c.value,c.currency,c.currencyvalue,c.comment,d.fullnumber
            FROM cash c LEFT JOIN documents d ON d.id=c.docid WHERE c.customerid=?
            AND NOT EXISTS (SELECT 1 FROM vcustomerassignments a JOIN excludedgroups e ON a.customergroupid=e.customergroupid
                WHERE e.userid=lms_current_user() AND a.customerid=?) ORDER BY c.time,c.id',[$id,$id])?:[];
        return self::render($customer,$rows);
    }
    public static function render(array $customer,array $rows): array
    {
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $money=static fn($v)=>number_format((float)$v,2,',',' ');
        $zone=new DateTimeZone('Europe/Warsaw');$balance=0;$table='';
        foreach($rows as $r){
            $value=(float)$r['value'];$balance+=$value*(float)$r['currencyvalue'];
            $date=(new DateTimeImmutable('@'.(int)$r['time']))->setTimezone($zone)->format('Y-m-d H:i');
            $table.='<tr><td>'.$e($date).'</td><td>'.$e($r['fullnumber']??'').'</td><td>'.$e($r['comment']).'</td><td>'
                .(!$r['type']?$money(-$value):'—').'</td><td>'.($r['type'] && $value>0?$money($value):'—').'</td><td>'
                .($r['type'] && $value<0?$money(-$value):'—').'</td><td>'.$e($r['currency']).'</td><td>'.$money($balance).'</td></tr>';
        }
        $html='<!doctype html><html lang="pl"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Historia płatności</title>'
            .'<style>body{font:14px Arial,sans-serif;margin:24px;color:#222}table{border-collapse:collapse;width:100%}th,td{border:1px solid #bbb;padding:7px;text-align:left}th{background:#eee}td:nth-child(n+4){text-align:right}</style>'
            .'<h1>Historia płatności</h1><p>Klient #'.(int)$customer['id'].' — '.$e($customer['customername']).'</p><p>Pełna historia rozliczeń z LMS: obciążenia, wpłaty i saldo. Saldo po przeliczeniu walut: PLN.</p>'
            .'<table><thead><tr><th>Data</th><th>Dokument</th><th>Opis</th><th>Zobowiązanie</th><th>Wpłata</th><th>Wydatek</th><th>Waluta</th><th>Saldo PLN</th></tr></thead><tbody>'.$table
            .'</tbody></table><p><b>Saldo końcowe: '.$money($balance).' PLN</b></p></html>';
        return ['filename'=>'historia-platnosci-'.(int)$customer['id'].'.html','content_type'=>'text/html; charset=UTF-8','data'=>$html,'hash'=>hash('sha256',$html),'count'=>count($rows)];
    }
}
