<?php
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../lib/NotifyPanel.php';
define('CONTACT_EMAIL',1);define('CONTACT_MOBILE',2);define('CONTACT_NOTIFICATIONS',4);define('CONTACT_DISABLED',8);
function verify($ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
foreach([[str_repeat('A',160),1,'GSM-7'],[str_repeat('A',161),2,'GSM-7'],[str_repeat('A',307),3,'GSM-7'],[str_repeat('^',80),1,'GSM-7'],[str_repeat('^',81),2,'GSM-7'],[str_repeat('ą',70),1,'Unicode'],[str_repeat('ą',71),2,'Unicode'],[str_repeat('ą',135),3,'Unicode'],[str_repeat('😀',35),1,'Unicode'],[str_repeat('😀',36),2,'Unicode']] as [$body,$parts,$encoding]){
    $m=NotifySmsCompany::measure($body);verify($m['segments']===$parts && $m['encoding']===$encoding,'encoding and segment boundary '.strlen($body));
}
$db=new class {
    public array $gateway=['enabled'=>true,'allow_manual_sms'=>true,'segment_rate'=>'0.075321'];
    public array $notices=[];public int $revision=1;
    function GetAll($sql,$args=[]){
        if(strpos($sql,'lnp_block_notices')!==false)return $this->notices;
        if(strpos($sql,'messageitems')!==false)return [['id'=>99,'status'=>2]];
        throw new LogicException('Unexpected query '.$sql);
    }
    function GetRow($sql,$args=[]){
        if(strpos($sql,'lnp_sms_settings')!==false)return ['payload'=>json_encode($this->gateway),'token'=>'fixture-only','revision'=>$this->revision];
        throw new LogicException('Unexpected query '.$sql);
    }
    function GetCol($sql,$args=[]){
        if(strpos($sql,'SELECT DISTINCT customerid')!==false)return [2];
        if(strpos($sql,'SELECT DISTINCT contact')!==false){
            if($args[0]===6)return ['invalid'];
            return $args[0]===1?['600000000','+48 600 000 000','601000000']:['600000000'];
        }
        throw new LogicException('Unexpected query '.$sql);
    }
    function GetOne($sql,$args=[]){
        if(strpos($sql,'lnp_history')!==false)return $args[0]===5?1:null;
        if(strpos($sql,'divisionid')!==false)return $args[0]===7?999:1;
        throw new LogicException('Unexpected query '.$sql);
    }
};
$np=new NotifyPanel($db,new stdClass());$s=NotifyPolicy::defaults();$s['revision']=1;
$rows=[];
for($id=1;$id<=66;++$id)$rows[]=['id'=>$id,'customername'=>'Fixture '.$id,'debt'=>$id===66?0:200,
 'balance'=>-200,'base'=>100,'percent'=>200,'state'=>$id===4?'review':($id===1?'block':'ok'),
 'excluded'=>$id===3,'smsnotice'=>$id===8?0:1,'debt_cycle'=>'cycle'];
$stats=$np->customerStatistics($rows,$s,$db->gateway);
verify($stats['debtors']===65 && $stats['block']===1 && $stats['remaining']===64,'global debt counts across multiple pages, including exclusions and review');
$thresholdRows=$rows;$thresholdRows[0]['percent']=300;$thresholdRows[2]['percent']=450;$thresholdRows[3]['percent']=700;
$thresholdStats=$np->customerStatistics($thresholdRows,$s,$db->gateway);
verify($thresholdStats['block_threshold']===3 && $thresholdStats['threshold_held']===2 && $thresholdStats['below_block_threshold']===62,'threshold count separate from warning readiness, including excluded and review flags');
verify($stats['sms']['customers']===58 && $stats['sms']['numbers']===59 && $stats['sms']['segments']===59,'SMS eligibility: email, exception, review, cooldown, invalid number, unsupported company, consent, normalized duplicates and multiple numbers');
verify(abs($stats['sms']['cost']-59*0.075321)<0.0000001,'configured rate, total rounding and recipient multiplication');
verify($stats['invalid_messages']===1,'invalid message excluded with visible count');
$preview=$np->manualPreview($s,true,$rows);
verify($np->smsEstimate($preview['rows'],$db->gateway)===$stats['sms'],'statistics and fresh preview use identical recipient rules');
$noRate=$db->gateway;unset($noRate['segment_rate']);
verify($np->smsEstimate($preview['rows'],$noRate)['cost']===null,'missing rate never assumes a price');
$estimate=$np->smsEstimate([['sms_body'=>str_repeat('ą',135),'sms_recipients'=>['a','b']]],$db->gateway);
verify($estimate['segments']===6,'multipart cost multiplies segments by phone count');
$off=$db->gateway;$off['enabled']=false;
verify($np->customerStatistics($rows,$s,$off)['sms']['customers']===0,'disabled gateway has no eligible manual recipients');
$s['block_after_days']=7;
$db->notices=[['customerid'=>1,'origin'=>'manual','source_items'=>'[99]','cycle_key'=>'cycle','sent_at'=>time()-7*86400-10]];
verify($np->customerStatistics($rows,$s,$db->gateway)['block']===1,'delivered manual countdown qualifies with automatic blocking disabled');
$db->notices[0]['sent_at']=time()-6*86400;
verify($np->customerStatistics($rows,$s,$db->gateway)['block']===0,'countdown blocks percentage-based qualification before due date');
foreach(['0','0,123456','9999.999999',''] as $rate){$v=NotifySmsSettings::validate(['sender'=>'Test','segment_rate'=>$rate]);verify($rate!=='' || $v['segment_rate']===null,'valid or missing rate '.$rate);}
foreach(['-1','NaN','1e2','0.1234567','10000'] as $rate){try{NotifySmsSettings::validate(['sender'=>'Test','segment_rate'=>$rate]);throw new LogicException('Invalid rate accepted');}catch(InvalidArgumentException $e){}}
echo 'PASS invalid rates rejected'.PHP_EOL;
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$smsEstimate=$stats['sms'];
ob_start();require __DIR__.'/../views/sms-estimate.php';$html=ob_get_clean();
verify(strpos($html,'59 segmentów')!==false && strpos($html,'4,44 zł')!==false,'cost displayed with two decimal places');

$hash=$np->smsPreviewHash($rows[0],$s);$db->gateway['segment_rate']='0.09';++$db->revision;
verify(!hash_equals($hash,$np->smsPreviewHash($rows[0],$s)),'saved rate change invalidates prior SMS confirmation');

$below=$rows;$below[0]['debt']=199.99;$below[0]['percent']=199.99;
$limited=$np->manualPreview($s,true,$below);verify(!in_array(1,array_column($limited['rows'],'id'),true),'bulk preview omits SMS customer below configured threshold');

$notifyRows=$rows;
$notifyRows[0]['debt']=199.99;$notifyRows[0]['percent']=200;
$notifyRows[1]['debt']=200;$notifyRows[1]['percent']=200;
$notifyRows[2]['debt']=199.99;$notifyRows[2]['percent']=200;
$notifyStats=$np->customerStatistics($notifyRows,NotifyPolicy::defaults(),['enabled'=>false]);
verify($notifyStats['notify_threshold']===63 && $notifyStats['debtors']===65,'notification counter uses exact threshold while unfiltered debt count stays unchanged');
