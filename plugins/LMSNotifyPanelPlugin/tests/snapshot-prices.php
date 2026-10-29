<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}

if(!function_exists('f_round')){function f_round($v){return round((float)$v,2);}}
require_once __DIR__.'/../lib/NotifySnapshot.php';
$base=['netflag'=>0,'unitary_value'=>100,'unitary_netvalue'=>100,'pdiscount'=>10,'unitary_vdiscount'=>5,'count'=>2,'tax_value'=>23,'taxl_value'=>null];
foreach([
    [$base,170],
    [array_replace($base,['netflag'=>1]),209.1],
    [array_replace($base,['netflag'=>1,'tax_value'=>0,'taxl_value'=>8]),183.6],
    [array_replace($base,['netflag'=>1,'tax_value'=>0]),170],
    [array_replace($base,['netflag'=>1,'unitary_netvalue'=>10.005,'pdiscount'=>0,'unitary_vdiscount'=>0,'count'=>3]),36.94],
    [array_replace($base,['netflag'=>1,'unitary_netvalue'=>615,'pdiscount'=>0,'unitary_vdiscount'=>0,'count'=>1]),756.45],
] as [$row,$expected])if(abs(NotifySnapshot::gross($row)-$expected)>.00001)throw new RuntimeException('Gross rounding mismatch');
echo "PASS batch-list gross/net prices, percentage and amount discounts, quantity, VAT fallback and rounding.\n";
