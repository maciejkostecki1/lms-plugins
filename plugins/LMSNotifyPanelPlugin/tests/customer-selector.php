<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}

$script_name='Native LMS customer selector rendering';$script_parameters=[];
require getcwd().'/bin/script-options.php';
$smarty=new LMSSmarty();
$smarty->setTemplateDir(dirname(__DIR__).'/templates');
$compile=sys_get_temp_dir().'/lnp-selector-test-'.getmypid();mkdir($compile,0700);
$smarty->setCompileDir($compile);$smarty->assign('error',[]);$smarty->assign('warning',[]);
foreach(['lnp-customer-filter'=>false,'lnp-exception-form'=>true] as $form=>$required){
    $smarty->assign('lnp_selector_form',$form);$smarty->assign('lnp_selector_input',$form.'-customer');
    $smarty->assign('lnp_selector_selected','1271');$smarty->assign('lnp_selector_required',$required);
    $html=$smarty->fetch('notifypanel/customer-select.html');
    foreach(['data-version="2"','name="customerid"','form="'.$form.'"','lms-ui-customer-select-suggestion-input','value="1271"'] as $needle)if(strpos($html,$needle)===false)throw new RuntimeException('Native selector missing '.$needle);
    if($required && strpos($html,' required')===false)throw new RuntimeException('Required exception selection missing');
}
echo "PASS actual LMS customerlist version 2 compiles and renders for filter and required exception form, using the Huawei mechanism.\n";
