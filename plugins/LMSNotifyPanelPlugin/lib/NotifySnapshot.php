<?php
declare(strict_types=1);
require_once __DIR__.'/NotifyDb.php';
// Display-only batch reader. Actions still use inspect() with fresh native LMS APIs.
final class NotifySnapshot
{
    private NotifyDb $db;
    public function __construct($db){$this->db=$db instanceof NotifyDb?$db:new NotifyDb($db);}
    public static function gross(array $a): float
    {
        // Same order of unit/quantity/VAT rounding as LMS GetCustomerAssignments.
        $value=(float)($a['netflag']?$a['unitary_netvalue']:$a['unitary_value']);
        $unit=f_round(($value-$value*(float)$a['pdiscount']/100)-(float)$a['unitary_vdiscount']);
        $total=f_round($unit*(float)$a['count']);
        if(!$a['netflag'])return (float)$total;
        $tax=!empty($a['tax_value'])?(float)$a['tax_value']:(!empty($a['taxl_value'])?(float)$a['taxl_value']:0);
        return (float)f_round($total+f_round($total*$tax/100));
    }
    public function build($panel,array $settings): array
    {
        $now=time();$midnight=mktime(0,0,0,(int)date('n',$now),(int)date('j',$now),(int)date('Y',$now));
        $customers=$this->db->GetAll('SELECT c.id,c.name,c.lastname,c.status,c.deleted,c.cutoffstop,c.mailingnotice,c.smsnotice FROM customeraddressview c
            WHERE c.deleted=0 AND c.status=? AND (EXISTS(SELECT 1 FROM cash WHERE customerid=c.id) OR EXISTS(SELECT 1 FROM lnp_blocks WHERE customerid=c.id)) ORDER BY c.id',[CSTATUS_CONNECTED])?:[];
        $periods=[];foreach($this->db->GetAll('SELECT id,period FROM tariffs')?:[] as $r)$periods[(int)$r['id']]=$r['period'];
        $exceptions=[];foreach($this->db->GetAll('SELECT customerid,until_time,reason FROM lnp_exclusions WHERE until_time>?',[$now])?:[] as $r)$exceptions[(int)$r['customerid']]=$r;
        $blocks=[];foreach($this->db->GetAll('SELECT customerid,count(*) AS total FROM lnp_blocks GROUP BY customerid')?:[] as $r)$blocks[(int)$r['customerid']]=(int)$r['total'];
        $billing=[];foreach($this->db->GetAll('SELECT customerid,count(*) AS total FROM lnp_billing GROUP BY customerid')?:[] as $r)$billing[(int)$r['customerid']]=(int)$r['total'];
        $preserved=[];foreach($this->db->GetAll('SELECT b.customerid,SUM(b.monthly_base) AS total FROM lnp_billing b JOIN assignments a ON a.id=b.assignmentid
            WHERE a.customerid=b.customerid AND a.suspended=1 AND a.commited=1 AND a.tariffid IS NOT DISTINCT FROM b.source_tariffid
            AND a.liabilityid IS NOT DISTINCT FROM b.source_liabilityid AND a.datefrom<=? AND (a.dateto=0 OR a.dateto>?) GROUP BY b.customerid',[$now,$now])?:[] as $r)$preserved[(int)$r['customerid']]=$r['total'];
        $result=[];
        // Bound ledger memory while avoiding thousands of per-customer queries.
        foreach(array_chunk($customers,50) as $chunk){
            $ids=array_map('intval',array_column($chunk,'id'));$entries=[];$balances=[];$assignments=[];
            foreach($this->db->GetAll('SELECT customerid,SUM(value*currencyvalue) AS balance FROM cash WHERE customerid IN ? AND time<? GROUP BY customerid',[$ids,$now])?:[] as $r)$balances[(int)$r['customerid']]=$r['balance'];
            foreach($this->db->GetAll('SELECT ca.customerid,ca.id AS ledger_id,ca.value*ca.currencyvalue AS amount,ca.docid,ca.itemid,ic.period,d.cdate AS invoice_cdate,d.paytime AS invoice_paytime
                FROM cash ca LEFT JOIN invoicecontents ic ON ic.docid=ca.docid AND ic.itemid=ca.itemid LEFT JOIN documents d ON d.id=ca.docid
                WHERE ca.customerid IN ? AND ca.time<? ORDER BY ca.customerid,ca.time,ca.id',[$ids,$now])?:[] as $r)$entries[(int)$r['customerid']][]=$r;
            $feeRows=$this->db->GetAll('SELECT a.id,a.customerid,a.tariffid,a.liabilityid,a.commited,a.suspended,a.datefrom,a.dateto,a.period AS periodvalue,
                a.pdiscount,a.vdiscount AS unitary_vdiscount,a.count,
                CASE WHEN t.value IS NULL THEN l.value ELSE t.value END AS unitary_value,
                CASE WHEN t.netvalue IS NULL THEN l.netvalue ELSE t.netvalue END AS unitary_netvalue,
                CASE WHEN t.currency IS NULL THEN l.currency ELSE t.currency END AS currency,
                CASE WHEN t.flags IS NULL THEN CASE WHEN (l.flags & ?) > 0 THEN 1 ELSE 0 END ELSE CASE WHEN (t.flags & ?) > 0 THEN 1 ELSE 0 END END AS netflag,
                tax.value AS tax_value,taxl.value AS taxl_value
                FROM assignments a LEFT JOIN tariffs t ON t.id=a.tariffid LEFT JOIN liabilities l ON l.id=a.liabilityid
                LEFT JOIN taxes tax ON tax.id=t.taxid LEFT JOIN taxes taxl ON taxl.id=l.taxid
                WHERE a.customerid IN ? AND a.commited=1 AND a.period<>? AND (a.dateto>? OR a.dateto=0) AND (a.at>=? OR a.at<531)
                ORDER BY a.datefrom,t.name,((CASE WHEN t.value IS NULL THEN l.value ELSE t.value END)*a.count)',
                [LIABILITY_FLAG_NET_ACCOUT,TARIFF_FLAG_NET_ACCOUNT,$ids,DISPOSABLE,$midnight,$midnight])?:[];
            foreach($feeRows as $a){$a['discounted_value']=self::gross($a);$assignments[(int)$a['customerid']][]=$a;}
            foreach($chunk as $customer){
                $id=(int)$customer['id'];
                $data=['customer'=>$customer,'balance'=>$balances[$id]??0,'entries'=>$entries[$id]??[],
                    'assignments'=>$assignments[$id]??[],'periods'=>$periods,'preserved'=>$preserved[$id]??null,
                    'exception'=>$exceptions[$id]??null,'owned_blocks'=>$blocks[$id]??0,'owned_billing'=>$billing[$id]??0];
                $row=$panel->inspect($id,$settings,$data);if($row)$result[]=$row;
            }
        }
        return $result;
    }
}
