<?php
require_once __DIR__.'/../lib/NotifyPolicy.php';
$c=['id'=>1271,'customername'=>'Jan Testowy','balance'=>-1663,'debt'=>875,'base'=>380,'percent'=>230.26,'bankaccount'=>'12 3456 7890'];
$text=NotifyPolicy::render('Dzień dobry %name. Saldo: %balance zł. Dług abonamentowy: %debt zł. Rachunek: %bankaccount.',$c);
if(strpos($text,'Saldo: -1 663,00 zł.')===false || strpos($text,'875,00 zł')===false || strpos($text,'Jan Testowy')===false || strpos($text,'12 3456 7890')===false)throw new RuntimeException('Manual symbols invalid');
try{NotifyPolicy::render('%unpaid',$c);throw new LogicException('Invoice-only symbol accepted');}catch(RuntimeException $e){}
echo "PASS full signed balance, separate subscription debt, customer name, bank account and rejection of invoice-only unpaid token.\n";
