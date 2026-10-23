<?php
declare(strict_types=1);
require_once __DIR__.'/NotifyDb.php';
// This allocation is only a notification rule; it never changes the accounting ledger.
final class NotifyCurrentInvoice
{
    public static function allocate(array $invoices,array $entries,int $start,int $now): array
    {
        usort($invoices,static fn($a,$b)=>[$a['cdate'],$a['docid']]<=>[$b['cdate'],$b['docid']]);
        $opening=0;$pool=0;$linked=[];
        foreach($entries as $e){
            if((int)$e['time']>$now)continue;
            $v=(int)round((float)$e['amount']*100);
            if((int)$e['time']<$start){$opening+=$v;continue;}
            if($v<=0)continue;
            if(empty($e['docid']))$pool+=$v;
            else $linked[(int)$e['docid']]=($linked[(int)$e['docid']]??0)+$v;
        }
        $pool+=max(0,$opening);$out=[];
        foreach($invoices as $i){
            $amount=(int)round((float)$i['amount']*100);
            $due=max(0,$amount-($linked[(int)$i['docid']]??0));
            $paid=min($pool,$due);$pool-=$paid;$due-=$paid;
            $i['unpaid']=$due/100;$i['paid']=($amount-$due)/100;
            $out[]=$i;
        }
        return $out;
    }
    public static function eligible(array $i,int $days,int $now): bool
    {
        $deadline=(new DateTimeImmutable('@'.(int)$i['cdate']))->setTimezone(new DateTimeZone('Europe/Warsaw'))
            ->setTime(0,0)->modify('+'.(int)$i['paytime'].' days')->modify('+'.$days.' days');
        return empty($i['closed']) && (float)$i['unpaid']>0.009 && $deadline->getTimestamp()<=$now;
    }
    public static function load($raw,int $now,?int $customer=null): array
    {
        $db=new NotifyDb($raw);
        $start=(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Europe/Warsaw'))->modify('first day of this month')->setTime(0,0)->getTimestamp();
        // Mixed documents and unclassified lines require individual review, never automatic collection.
        $rows=$db->GetAll('SELECT d.id AS docid,d.customerid,d.name,d.number,d.cdate,d.paytime,d.closed,n.template,
            c.mailingnotice,c.cutoffstop,di.account,
            -SUM(ca.value*ca.currencyvalue) AS amount
            FROM documents d JOIN customeraddressview c ON c.id=d.customerid
            LEFT JOIN divisions di ON di.id=c.divisionid LEFT JOIN numberplans n ON n.id=d.numberplanid
            JOIN cash ca ON ca.docid=d.id AND ca.value<0
            WHERE d.type=? AND d.cdate>=? AND d.cdate<=? AND c.deleted=0 AND c.status=?
            AND NOT EXISTS(SELECT 1 FROM invoicecontents ic WHERE ic.docid=d.id AND (ic.period IS NULL OR ic.period<=0))
            AND EXISTS(SELECT 1 FROM invoicecontents ic WHERE ic.docid=d.id AND ic.period>0)
            AND NOT EXISTS(SELECT 1 FROM cash cx WHERE cx.docid=d.id AND cx.currency<>?)'
            .($customer!==null?' AND d.customerid=?':'')
            .' GROUP BY d.id,d.customerid,d.name,d.number,d.cdate,d.paytime,d.closed,n.template,c.mailingnotice,c.cutoffstop,di.account ORDER BY d.customerid,d.cdate,d.id',
            array_merge([DOC_INVOICE,$start,$now,CSTATUS_CONNECTED,'PLN'],$customer!==null?[$customer]:[]))?:[];
        $groups=[];foreach($rows as $row)$groups[(int)$row['customerid']][]=$row;
        $out=[];
        foreach($groups as $id=>$invoices){
            $entries=$db->GetAll('SELECT time,value*currencyvalue AS amount,docid FROM cash WHERE customerid=? AND time<=? ORDER BY time,id',[$id,$now])?:[];
            foreach(self::allocate($invoices,$entries,$start,$now) as $i)$out[]=$i;
        }
        return $out;
    }
    public static function render(string $text,array $i): string
    {
        $number=docnumber(['number'=>$i['number'],'template'=>$i['template']?:'%N/LMS/%Y','cdate'=>$i['cdate'],'customerid'=>$i['customerid']]);
        $deadline=(new DateTimeImmutable('@'.(int)$i['cdate']))->setTimezone(new DateTimeZone('Europe/Warsaw'))->modify('+'.(int)$i['paytime'].' days');
        return strtr($text,['%invoice'=>$number,'%number'=>$number,'%cid'=>(string)$i['customerid'],'%name'=>$i['name'],
            '%value'=>number_format((float)$i['amount'],2,',',' ').' zł','%unpaid'=>number_format((float)$i['unpaid'],2,',',' ').' zł',
            '%deadline-y'=>$deadline->format('Y'),'%deadline-m'=>$deadline->format('m'),'%deadline-d'=>$deadline->format('d'),
            '%bankaccount'=>format_bankaccount(bankaccount($i['customerid'],$i['account']))]);
    }
}
