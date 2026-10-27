<?php
if(PHP_SAPI!=='cli')exit(1);
define('MSG_ERROR',3);define('MSG_NEW',1);
require_once __DIR__.'/../lib/NotifySmsSettings.php';
$db=new class{
 public $row=null;public $history=[];
 function BeginTrans(){}function CommitTrans(){}function RollbackTrans(){}
 function GetRow($q,$a=[]){return $this->row;}
 function Execute($q,$a=[]){
  if(strpos($q,'INSERT INTO lnp_sms_settings')===0)$this->row=['payload'=>$a[0],'token'=>$a[1],'revision'=>1];
  elseif(strpos($q,'UPDATE lnp_sms_settings')===0){$this->row['payload']=$a[0];$this->row['token']=$a[1];++$this->row['revision'];}
  else $this->history[]=$a;
 }
};
$store=new NotifySmsSettings($db);$fake='secret-for-unit-tests-only';
$store->save(['revision'=>0,'enabled'=>true,'allow_manual_sms'=>true,'allow_customer_sms'=>true,'sender'=>'NET-24H','test_number'=>'600000000','token'=>$fake],1);
$s=$store->settings();
if(!$s['has_token'] || isset($s['token']) || strpos(json_encode($s),$fake)!==false || $s['allow_customer_sms'] || $s['test_number']!=='48600000000')throw new RuntimeException('Unsafe public settings');
$store->save(['revision'=>1,'enabled'=>true,'sender'=>'NET-24H','token'=>''],1);
if($db->row['token']!==$fake)throw new RuntimeException('Blank input overwrote token');
try{$store->save(['revision'=>1,'sender'=>'NET-24H'],1);throw new LogicException('Stale revision accepted');}catch(RuntimeException $e){}
if($store->send('48600000000','Never sent','x')['status']!==MSG_ERROR)throw new RuntimeException('Automatic send not blocked');
if(strpos(json_encode($db->history),$fake)!==false)throw new RuntimeException('Secret logged');
$store->save(['revision'=>2,'sender'=>'NET-24H','delete_token'=>true],1);
if($store->settings()['has_token'])throw new RuntimeException('Token deletion failed');
echo 'PASS plugin SMS settings, masked token, preservation/deletion, stale revision, disabled automation and secret-free audit'.PHP_EOL;
