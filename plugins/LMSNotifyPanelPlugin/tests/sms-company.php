<?php
require_once __DIR__.'/../lib/NotifySmsCompany.php';
foreach([1=>'Net-24h Maciej Kostecki',2=>'Meganecik'] as $id=>$signature){
 $text=NotifySmsCompany::message($id,7,285.40);
 if(substr($text,-strlen($signature))!==$signature || strpos($text,'7 dni')===false || strlen($text)>160 || preg_match('/[^\x20-\x7e]/',$text))throw new RuntimeException('Bad company message');
 echo 'PASS signature '.$signature.'; single SMS: '.strlen($text).' characters'.PHP_EOL;
}
try{NotifySmsCompany::message(999,7,100);throw new LogicException('Unknown company accepted');}catch(RuntimeException $e){echo 'PASS unknown company blocked'.PHP_EOL;}
if(strpos(NotifySmsCompany::message(1,0,100),'7 dni')!==false)throw new RuntimeException('Disabled countdown must not promise seven days');
echo 'PASS disabled countdown text'.PHP_EOL;

foreach([0.01,27.2,100,285.4,999999999.99] as $debt)foreach([1,2] as $id){
 $text=NotifySmsCompany::message($id,7,$debt);
 if(strpos($text,number_format($debt,2,',','').' zl')===false || strlen($text)>160)throw new RuntimeException('Debt/length failure');
}
foreach([0,-1,NAN,INF] as $debt){try{NotifySmsCompany::message(1,7,$debt);throw new LogicException('Invalid amount accepted');}catch(RuntimeException $e){}}
try{NotifySmsCompany::message(1,365,1e100);throw new LogicException('Oversized message accepted');}catch(RuntimeException $e){}
echo 'PASS individual debt amounts, rounding, single SMS limit and invalid/oversized amounts'.PHP_EOL;
echo NotifySmsCompany::message(1,7,285).' ('.strlen(NotifySmsCompany::message(1,7,285)).' chars)'.PHP_EOL;
echo NotifySmsCompany::message(2,7,100).' ('.strlen(NotifySmsCompany::message(2,7,100)).' chars)'.PHP_EOL;

$custom='Dlug: %debt zl. %company';
if(NotifySmsCompany::message(2,7,12.34,$custom)!=='Dlug: 12,34 zl. Meganecik')throw new RuntimeException('Custom template ignored');
foreach(['%company','%debt','%debt %company %unknown'] as $bad){try{NotifySmsCompany::validateTemplate($bad);throw new LogicException('Bad template accepted');}catch(InvalidArgumentException $e){}}
foreach([[str_repeat('A',160),true],[str_repeat('A',161),false],[str_repeat('^',80),true],[str_repeat('^',81),false],[str_repeat('ą',70),true],[str_repeat('ą',71),false]] as [$text,$ok])if(NotifySmsCompany::fitsOneSms($text)!==$ok)throw new RuntimeException('SMS encoding length mismatch');
echo 'PASS editable template, required amount/signature, unknown tokens and GSM/Unicode SMS limits'.PHP_EOL;
