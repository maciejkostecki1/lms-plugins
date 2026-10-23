<?php
if(PHP_SAPI!=='cli')exit(1);
define('DISPOSABLE',0);define('MONTHLY',3);define('QUARTERLY',4);define('YEARLY',5);define('HALFYEARLY',7);
require_once __DIR__.'/../lib/NotifyFees.php';
$base=['tariffid'=>1,'liabilityid'=>null,'commited'=>1,'suspended'=>0,'datefrom'=>0,'dateto'=>0,
    'periodvalue'=>MONTHLY,'currency'=>'PLN','unitary_value'=>123,'pdiscount'=>10,'vdiscount'=>10,
    'count'=>2,'discounted_value'=>196.80];
function fee($a,$p=[]){return NotifyFees::monthly($a,$p,1000);}
function checkFee($ok,$label){if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
checkFee(fee([$base])['rows'][0]['sum']===196.80,'uses LMS discounted gross total, no second discount or quantity multiplication');
$net=array_replace($base,['netflag'=>1,'unitary_netvalue'=>100,'discounted_value'=>221.40]);
checkFee(fee([$net])['rows'][0]['sum']===221.40,'net tariff uses computed gross after VAT and discounts');
checkFee(abs(fee([array_replace($base,['discounted_value'=>300])],[1=>QUARTERLY])['rows'][0]['sum']-100)<0.001,'quarterly gross normalised monthly');
checkFee(abs(fee([array_replace($base,['discounted_value'=>1200])],[1=>YEARLY])['rows'][0]['sum']-100)<0.001,'yearly gross normalised monthly');
checkFee(fee([array_replace($base,['periodvalue'=>DISPOSABLE])])['rows']===[],'one-off excluded');
foreach([['suspended'=>1],['commited'=>0],['datefrom'=>1001],['dateto'=>1000]] as $skip)checkFee(fee([array_replace($base,$skip)])['rows']===[],'inactive assignment excluded '.key($skip));
$invalid=$base;unset($invalid['discounted_value']);checkFee(fee([$invalid])['reason']!=='','missing gross requires review');
checkFee(fee([array_replace($base,['periodvalue'=>2])])['reason']!=='','unsupported period requires review');
