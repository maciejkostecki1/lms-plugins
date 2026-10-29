<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../lib/KsefErsteExport.php';
class Auth {public static function GetCurrentUser(){return 42;}}
class ConfigHelper {public static function getConfig($key,$default=''){return '04'.str_repeat('0',24);}}
function check($ok,$name){if(!$ok)throw new RuntimeException($name);if(!isset($GLOBALS['downloadMode']))echo 'PASS '.$name.PHP_EOL;}
function invoice($id=1,$settled=0){return ['id'=>$id,'division_id'=>1,'invoice_number'=>'TEST/'.$id,'seller_name'=>'Fixture supplier','gross_amount'=>'100.00','vat_amount'=>'23.00','currency'=>'PLN','bank_account'=>'04'.str_repeat('0',24),'seller_ten'=>'1234567890','issue_date'=>strtotime('2026-09-15'),'settled'=>$settled];}
$DB=new class {
    public array $rows=[];public int $queries=0;
    public function GetAll($sql,$args=[]){
        ++$this->queries;if(strpos($sql,'SELECT d.id')===0)return [['id'=>1,'name'=>'Fixture division']];
        if(strpos($sql,'AND i.id IN ?')!==false)return array_values(array_filter($this->rows,static fn($i)=>in_array($i['id'],$args[4],true)&&$i['division_id']===$args[0]));
        preg_match('/OFFSET ([0-9]+)/',$sql,$m);return array_slice($this->rows,(int)($m[1]??0),250);
    }
};
$SESSION=new class {public array $data=[];public function get($key){return $this->data[$key]??null;}public function save($key,$value){$this->data[$key]=$value;}public function close(){global $argv;if(isset($argv[2]))file_put_contents($argv[2],json_encode($this->data,JSON_THROW_ON_ERROR));}};
$SMARTY=new class {public array $data=[];public function assign($key,$value){$this->data[$key]=$value;}public function display($template){}};
function render(array $post=[],array $get=[]){
    global $DB,$SESSION,$SMARTY;
    $_POST=$post;$_GET=$get;$_REQUEST=array_merge(['division'=>1,'date_from'=>'2026-09-01','date_to'=>'2026-09-30'],$get,$post);$_SERVER['REQUEST_METHOD']=$post?'POST':'GET';
    $SMARTY->data=[];require __DIR__.'/../modules/ksefpaymentexport.php';return $SMARTY->data;
}
function preview(array $ids=[1]){
    global $DB;$view=render();$addresses=array_fill_keys($ids,'Fixture address');
    return render(['action'=>'preview','csrf_token'=>$view['ksef_payment_csrf'],'view_token'=>$view['ksef_payment_view_token'],'invoice_ids'=>$ids,'recipient_address'=>$addresses]);
}
if(($argv[1]??'')==='download'){
    $GLOBALS['downloadMode']=true;$DB->rows=[invoice(1)];$view=preview();$p=$view['ksef_payment_preview'];check((bool)$p,'download preview');
    render(['action'=>'export','csrf_token'=>$view['ksef_payment_csrf'],'draft_token'=>$p['token']]);exit(2);
}
$account='04'.str_repeat('0',24);
check(kpe_nrb($account)===$account && kpe_nrb('PL'.$account)===$account && kpe_nrb('pl '.substr($account,0,10).'-'.substr($account,10))===$account,'NRB and PL IBAN share checksum validation');
try{kpe_export_line($account,invoice(),'');throw new LogicException('Blank address accepted');}catch(RuntimeException $e){check(true,'blank address refused');}
try{kpe_amount('100.001');throw new LogicException('Fractional grosz accepted');}catch(RuntimeException $e){check(true,'fractional grosz refused');}
$DB->rows=[invoice(1),invoice(2,1)];$result=preview([1,2]);
check($result['ksef_payment_preview']['total']==='200,00' && $result['ksef_payment_preview']['warnings'],'full gross total and settled warning shown without accounting changes');
$p=$result['ksef_payment_preview'];$bad=render(['action'=>'export','csrf_token'=>$result['ksef_payment_csrf'],'draft_token'=>$p['token']]);
check(strpos($bad['ksef_payment_error'],'Potwierdź')!==false,'settled or repeated export requires explicit confirmation');
$DB->rows[0]['gross_amount']='101.00';$bad=render(['action'=>'export','csrf_token'=>$result['ksef_payment_csrf'],'draft_token'=>$p['token'],'allow_repeat'=>'1']);
check(strpos($bad['ksef_payment_error'],'zmieniły')!==false,'changed amount after preview refuses download');
$DB->rows=[invoice(1)];$view=render();$DB->rows[0]['bank_account']='74'.str_repeat('0',23).'1';
$bad=render(['action'=>'preview','csrf_token'=>$view['ksef_payment_csrf'],'view_token'=>$view['ksef_payment_view_token'],'invoice_ids'=>[1],'recipient_address'=>[1=>'Fixture']]);
check(strpos($bad['ksef_payment_error'],'zmieniły')!==false,'changed bank account after listing requires fresh view');
$bad=render(['action'=>'preview','csrf_token'=>'bad']);check(strpos($bad['ksef_payment_error'],'CSRF')!==false,'CSRF rejects export preparation');
$DB->rows=[];for($i=1;$i<=250;$i++){$r=invoice($i);$r['bank_account']=str_repeat('0',26);$DB->rows[]=$r;}$DB->rows[]=invoice(251);
$result=render();check(count($result['ksef_payment_invoices'])===1 && $result['ksef_payment_invoices'][0]['id']===251,'valid invoice beyond invalid SQL batch remains visible');
$DB->rows=[];for($i=1;$i<=401;$i++)$DB->rows[]=invoice($i);
$result=render();check(count($result['ksef_payment_invoices'])===200 && $result['ksef_payment_has_next'],'validated pagination first page');
$result=render([],['page'=>3]);check(count($result['ksef_payment_invoices'])===1 && !$result['ksef_payment_has_next'],'validated pagination last page');
$DB->rows=[invoice(1)];$DB->queries=0;$result=preview();check($DB->queries<=6,'preview loads selected invoices as one batch');
$p=$result['ksef_payment_preview'];$SESSION->data['kpe_exports']['1:1']=time();
$bad=render(['action'=>'export','csrf_token'=>$result['ksef_payment_csrf'],'draft_token'=>$p['token']]);check(strpos($bad['ksef_payment_error'],'Inna karta')!==false,'concurrent tab download requires a new repeat preview');
unset($SESSION->data['kpe_exports']['1:1']);$SESSION->data['kpe_drafts'][$p['token']]['expires']=time()-1;
$bad=render(['action'=>'export','csrf_token'=>$result['ksef_payment_csrf'],'draft_token'=>$p['token']]);check(strpos($bad['ksef_payment_error'],'wygasł')!==false,'expired confirmation refuses export');
$snapshot=tempnam(sys_get_temp_dir(),'kpe-fixture-');$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'download',$snapshot],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
fclose($pipes[0]);$bytes=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
check($code===0 && substr($bytes,0,11)==="4120414|1\r\n" && strpos($bytes,'|100,00|')!==false,'confirmed fixture download preserves ERSTE header, CRLF and amount');
$state=json_decode(file_get_contents($snapshot),true);unlink($snapshot);
check(empty($state['kpe_drafts']) && !empty($state['kpe_exports']['1:1']),'confirmation is consumed once and session export history recorded');
$SESSION->data['kpe_exports']['1:1']=time();$DB->rows=[invoice(1)];$result=preview();
check($result['ksef_payment_preview']['warnings'] && $result['ksef_payment_preview']['items'][0]['previous_export'],'previous session download requires explicit repeat acknowledgement');
echo "All tests use fake invoices, DB and sessions; no bank or production database connection.\n";
