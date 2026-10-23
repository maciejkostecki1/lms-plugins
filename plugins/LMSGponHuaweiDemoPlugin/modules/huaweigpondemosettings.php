<?php
if (!isset($DB,$SMARTY,$AUTH) || empty($AUTH->islogged)) { http_response_code(403); exit; }
if (session_status()===PHP_SESSION_NONE) session_start();
if (empty($_SESSION['hd_demo_settings_csrf'])) $_SESSION['hd_demo_settings_csrf']=bin2hex(random_bytes(24));
$csrf=$_SESSION['hd_demo_settings_csrf'];
$canEdit=trim((string)$DB->GetOne('SELECT rights FROM users WHERE id=?',[(int)Auth::GetCurrentUser()]))==='full_access';
$error='';$message='';
$isPost=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
$raw=$isPost?($_POST['olt_id']??'0'):($_GET['olt_id']??'0');
$id=is_string($raw)&&ctype_digit($raw)?(int)$raw:0;
if($isPost){
 if(!$canEdit){http_response_code(403);exit('Brak uprawnień administratora LMS.');}
 try{
  if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Nieprawidłowy token formularza.');
  $action=$_POST['action']??'';
  if(!in_array($action,['add','save','save_ro','delete'],true)||!is_string($raw)||!ctype_digit($raw))throw new RuntimeException('Nieprawidłowa operacja.');
  if($action==='delete'){
   if($id<1||($_POST['confirm_delete']??'')!=='1')throw new RuntimeException('Potwierdź usunięcie danych OLT z demo.');
   if($DB->Execute('BEGIN')===false)throw new RuntimeException('Nie można rozpocząć usuwania.');
   try{
    if(!$DB->GetOne('SELECT id FROM huawei_gpon_demo_olts WHERE id=? FOR UPDATE',[$id]))throw new RuntimeException('Nie znaleziono OLT.');
    foreach([
     'DELETE FROM huawei_gpon_demo_samples WHERE ont_ref IN (SELECT id FROM huawei_gpon_demo_onts WHERE olt_id=?)',
     'DELETE FROM huawei_gpon_demo_onts WHERE olt_id=?',
     'DELETE FROM huawei_gpon_demo_ports WHERE olt_id=?',
     'DELETE FROM huawei_gpon_demo_settings WHERE olt_id=?',
     'DELETE FROM huawei_gpon_demo_snmp_secrets WHERE olt_id=?',
     'DELETE FROM huawei_gpon_demo_olts WHERE id=?'
    ]as$sql)if($DB->Execute($sql,[$id])===false)throw new RuntimeException('Usuwanie nieudane.');
    if($DB->Execute('COMMIT')===false)throw new RuntimeException('Usuwanie nieudane.');
   }catch(Throwable$e){$DB->Execute('ROLLBACK');throw$e;}
   $id=0;$message='Usunięto OLT i jego lokalne dane wyłącznie z demo. Urządzenie i pełna wtyczka nie zostały zmienione.';
  }else{
  if($action==='save_ro'){
   $current=$DB->GetRow('SELECT name,host,enabled,snmp_timeout,snmp_retries FROM huawei_gpon_demo_olts WHERE id=?',[$id]);
   if(!$current)throw new RuntimeException('Nie znaleziono OLT.');
   $_POST['name']=$current['name'];$_POST['host']=$current['host'];$_POST['timeout']=(string)$current['snmp_timeout'];$_POST['retries']=(string)$current['snmp_retries'];
   $_POST['enabled']=in_array($current['enabled'],[true,1,'1','t','true'],true)?'1':'0';$action='save';
  }
  $_POST+=['community_ro'=>'','timeout'=>'5','retries'=>'1'];
  foreach(['name','host','community_ro','timeout','retries']as$f)if(!is_string($_POST[$f]??null))throw new RuntimeException('Nieprawidłowe pola formularza.');
  $name=trim($_POST['name']);$host=trim($_POST['host']);$community=$_POST['community_ro'];
  if($name===''||strlen($name)>150||preg_match('/[\x00-\x1f\x7f]/',$name))throw new RuntimeException('Podaj poprawną nazwę OLT.');
  if(!filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))throw new RuntimeException('Podaj poprawny IPv4 OLT.');
  if(strlen($community)>256||preg_match('/[\x00-\x1f\x7f]/',$community))throw new RuntimeException('Community RO: 1–256 znaków bez znaków sterujących.');
  $timeout=filter_var($_POST['timeout'],FILTER_VALIDATE_INT);$retries=filter_var($_POST['retries'],FILTER_VALIDATE_INT);
  if($timeout===false||$timeout<1||$timeout>60||$retries===false||$retries<0||$retries>5)throw new RuntimeException('Timeout: 1–60 s, retries: 0–5.');
  $enabled=($_POST['enabled']??'')==='1'?'true':'false';
  if($DB->Execute('BEGIN')===false)throw new RuntimeException('Nie można rozpocząć zapisu.');
  try{
   if($action==='add'){
    if($DB->Execute('INSERT INTO huawei_gpon_demo_olts(name,host,enabled,snmp_timeout,snmp_retries) VALUES(?,?,CAST(? AS boolean),?,?)',[$name,$host,$enabled,$timeout,$retries])===false)throw new RuntimeException('Zapis nieudany.');
    $id=(int)$DB->GetOne("SELECT currval(pg_get_serial_sequence('huawei_gpon_demo_olts','id'))");
   }else{
    if($id<1||!$DB->GetOne('SELECT id FROM huawei_gpon_demo_olts WHERE id=? FOR UPDATE',[$id]))throw new RuntimeException('Nie znaleziono OLT.');
    if($DB->Execute('UPDATE huawei_gpon_demo_olts SET name=?,host=?,enabled=CAST(? AS boolean),snmp_timeout=?,snmp_retries=?,updated_at=NOW() WHERE id=?',[$name,$host,$enabled,$timeout,$retries,$id])===false)throw new RuntimeException('Zapis nieudany.');
   }
   if($community!==''){
    if($DB->Execute('INSERT INTO huawei_gpon_demo_snmp_secrets(olt_id,community_ro) VALUES(?,?) ON CONFLICT(olt_id) DO UPDATE SET community_ro=EXCLUDED.community_ro,updated_at=NOW()',[$id,$community])===false)throw new RuntimeException('Zapis nieudany.');
   }
   if($DB->Execute('COMMIT')===false)throw new RuntimeException('Zapis nieudany.');
  }catch(Throwable$e){$DB->Execute('ROLLBACK');throw$e;}
  $message='Zapisano lokalną konfigurację odczytu. Nie zmieniono ustawień OLT.';
  }
 }catch(Throwable$e){$error=$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'Nie udało się zapisać konfiguracji. Sprawdź unikalność adresu OLT.';}
}
$layout['pagetitle']='Huawei GPON — Konfiguracja wtyczki (demo RO)';
$olts=$DB->GetAll('SELECT id,name,host,enabled,snmp_timeout,snmp_retries FROM huawei_gpon_demo_olts ORDER BY id')?:[];
$selected=null;foreach($olts as$o)if((int)$o['id']===$id)$selected=$o;
if(!$selected&&$olts){$selected=$olts[0];$id=(int)$selected['id'];}
if($selected)$selected['enabled']=in_array($selected['enabled'],[true,1,'1','t','true'],true);
$saved=$id>0&&(bool)$DB->GetOne("SELECT COUNT(*) FROM huawei_gpon_demo_snmp_secrets WHERE olt_id=? AND COALESCE(community_ro,'')<>''",[$id]);
require_once __DIR__.'/../lib/HuaweiDemoSettings.php';
require_once __DIR__.'/../lib/HuaweiDemoTrapSettings.php';
$thresholds=$id>0?HuaweiDemoSettings::load($DB,$id):HuaweiDemoSettings::defaults();
$sources=HuaweiDemoTrapSettings::loadAll($DB);
$counts=['onts'=>0,'ports'=>0,'samples'=>0];
if($id>0){foreach(['onts','ports']as$t)$counts[$t]=(int)$DB->GetOne('SELECT COUNT(*) FROM huawei_gpon_demo_'.$t.' WHERE olt_id=?',[$id]);$counts['samples']=(int)$DB->GetOne('SELECT COUNT(*) FROM huawei_gpon_demo_samples WHERE ont_ref IN (SELECT id FROM huawei_gpon_demo_onts WHERE olt_id=?)',[$id]);}
$SMARTY->assign(['demo_thresholds'=>$thresholds,'demo_sources'=>implode("\n",$sources[$id]??[]),'demo_counts'=>$counts,'demo_olts'=>$olts,'demo_selected'=>$selected,'demo_olt_id'=>$id,'demo_can_edit'=>$canEdit,'demo_csrf'=>$csrf,'demo_error'=>$error,'demo_message'=>$message,'demo_ro_saved'=>$saved]);
$trapStatus=$id>0?HuaweiDemoTrapSettings::status($DB,$id):['state'=>'Brak OLT','updated'=>'—','last'=>'—','count'=>0];
$SMARTY->assign(['hg_settings_message'=>$message,'hg_settings_error'=>$error,'hg_settings_csrf'=>$csrf,'hg_settings_selected'=>$selected,'hg_settings_olts'=>$olts,'hg_settings_olt_id'=>$id,'hg_settings_values'=>$thresholds,'hg_delete_counts'=>$counts,'hg_snmp_presence'=>['ro'=>$saved,'rw'=>false],'hg_trap_sources'=>implode("\n",$sources[$id]??[]),'hg_trap_status'=>$trapStatus]);
$SMARTY->display('huaweigpondemo/settings.html');
