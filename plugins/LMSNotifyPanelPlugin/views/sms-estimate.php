<?php if(isset($smsEstimate)): ?>
<p>SMS: <?= (int)$smsEstimate['customers'] ?> klientów · <?= (int)$smsEstimate['numbers'] ?> numerów · <?= (int)$smsEstimate['segments'] ?> segmentów · szacowany koszt: <b><?= $smsEstimate['cost']===null?'ustaw stawkę w ustawieniach SMSAPI':$e(number_format($smsEstimate['cost'],2,',',' ')).' zł' ?></b><?php if($smsEstimate['rate']!==null): ?> (<?= $e($smsEstimate['rate']) ?> zł/segment)<?php endif ?>.</p>
<?php endif ?>
