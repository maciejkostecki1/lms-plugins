<?php
declare(strict_types=1);
final class NotifyFees
{
    public static function monthly(array $assignments,array $tariffPeriods,int $now): array
    {
        $totals=[];
        foreach($assignments as $a) {
            if(empty($a['commited']) || !empty($a['suspended']) || (int)$a['datefrom']>$now
                || ((int)$a['dateto']!==0 && (int)$a['dateto']<=$now)
                || (int)$a['periodvalue']===DISPOSABLE
                || (empty($a['tariffid']) && empty($a['liabilityid']))) continue;
            $period=!empty($a['tariffid'])?($tariffPeriods[(int)$a['tariffid']]??null):null;
            $period=$period?:$a['periodvalue'];
            $months=[MONTHLY=>1,QUARTERLY=>3,HALFYEARLY=>6,YEARLY=>12];
            if(!isset($months[(int)$period]))return ['rows'=>[],'reason'=>'Nietypowy okres rozliczeniowy — ręczna weryfikacja.'];
            // This is LMS's computed gross amount AFTER discounts and quantity.
            // Never subtract discounts again or use the tariff's undiscounted value.
            if(!isset($a['discounted_value']) || !is_numeric($a['discounted_value'])) {
                return ['rows'=>[],'reason'=>'Brak potwierdzonej kwoty brutto po rabatach.'];
            }
            $currency=strtoupper((string)($a['currency']??''));
            if($currency==='')$currency=class_exists('Localisation')?strtoupper(Localisation::getCurrentCurrency()):'PLN';
            $totals[$currency]=($totals[$currency]??0)+(float)$a['discounted_value']/$months[(int)$period];
        }
        $rows=[];
        foreach($totals as $currency=>$sum)$rows[]=['currency'=>$currency,'sum'=>$sum];
        return ['rows'=>$rows,'reason'=>''];
    }
}
