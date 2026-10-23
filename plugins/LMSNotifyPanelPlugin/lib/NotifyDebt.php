<?php
declare(strict_types=1);
final class NotifyDebt
{
    // LMS records many payments against the customer account rather than a document.
    // Apply those payments FIFO; document-linked credits settle their own items first.
    public static function split(array $entries,?int $now=null): array
    {
        $now=$now??time();
        $open=[];$byDoc=[];$head=0;$credit=0;$total=0;$recurring=0;$cycle='';
        foreach($entries as $index=>$e) {
            $v=(int)round((float)$e['amount']*100);$total+=$v;
            if($v<0) {
                $amount=-$v;$used=min($credit,$amount);$credit-=$used;$amount-=$used;
                $kind=$e['period']===null?'unknown':((int)$e['period']===0?'oneoff':'recurring');
                if($amount){
                    if($kind==='recurring'){if($recurring===0)$cycle=(string)($e['ledger_id']??($index+1));$recurring+=$amount;}
                    $overdueAt=null;
                    if($kind==='recurring' && isset($e['invoice_cdate'],$e['invoice_paytime']) && (int)$e['invoice_cdate']>0 && (int)$e['invoice_paytime']>=0){
                        $overdueAt=(new DateTimeImmutable('@'.(int)$e['invoice_cdate']))->setTimezone(new DateTimeZone('Europe/Warsaw'))->setTime(0,0)->modify('+'.(int)$e['invoice_paytime'].' days')->modify('+1 day')->getTimestamp();
                    }
                    $idx=count($open);$open[]=['amount'=>$amount,'kind'=>$kind,'docid'=>$e['docid'],'itemid'=>$e['itemid'],'overdue_at'=>$overdueAt];
                    if(!empty($e['docid']))$byDoc[(string)$e['docid']][]=$idx;
                }
            }elseif($v>0) {
                if(!empty($e['docid']))foreach($byDoc[(string)$e['docid']]??[] as $idx) {
                    $o=&$open[$idx];
                    if(empty($e['itemid'])||$o['itemid']===$e['itemid']) {
                        $used=min($v,$o['amount']);$v-=$used;$o['amount']-=$used;
                        if($o['kind']==='recurring')$recurring-=$used;
                    }
                }
                unset($o);
                while($v>0 && isset($open[$head])){
                    $o=&$open[$head];$used=min($v,$o['amount']);$v-=$used;$o['amount']-=$used;
                        if($o['kind']==='recurring')$recurring-=$used;
                    if(!$o['amount'])++$head;
                }
                unset($o);$credit+=$v;
                if($recurring===0)$cycle='';
            }
        }
        $out=['recurring'=>0,'oneoff'=>0,'unknown'=>0];
        foreach($open as $o)$out[$o['kind']]+=$o['amount'];
        $out['overdue_recurring']=0;$out['unknown_due']=0;
        foreach($open as $o)if($o['kind']==='recurring' && $o['amount']>0){
            if($o['overdue_at']===null)$out['unknown_due']+=$o['amount'];
            elseif($now>=$o['overdue_at'])$out['overdue_recurring']+=$o['amount'];
        }
        foreach($out as &$v)$v=(float)($v/100);unset($v);
        $out['balance']=$total/100;$out['cycle_key']=$cycle;
        return $out;
    }
}
