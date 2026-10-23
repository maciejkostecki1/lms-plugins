<?php
require_once __DIR__.'/../lib/NotifyPaymentHistory.php';
$customer=['id'=>17,'customername'=>'Żółć <script>bad</script>'];
$rows=[['id'=>1,'time'=>1,'type'=>0,'value'=>-100,'currency'=>'PLN','currencyvalue'=>1,'comment'=>'<img src=x onerror=bad>','fullnumber'=>'FV/1'],
    ['id'=>2,'time'=>2,'type'=>1,'value'=>10,'currency'=>'EUR','currencyvalue'=>4,'comment'=>'Wpłata','fullnumber'=>null]];
$a=NotifyPaymentHistory::render($customer,$rows);
if(strpos($a['data'],'-60,00 PLN')===false || strpos($a['data'],'<script>')!==false || strpos($a['data'],'<img')!==false || $a['count']!==2)throw new RuntimeException('Ledger arithmetic or escaping failed');
if($a['hash']!==NotifyPaymentHistory::render($customer,$rows)['hash'])throw new RuntimeException('Preview is not stable');
$rows[1]['value']=11;
if($a['hash']===NotifyPaymentHistory::render($customer,$rows)['hash'])throw new RuntimeException('Ledger changes not detected');
echo "PASS history arithmetic, currency conversion, HTML escaping, stable preview and change detection.\n";
