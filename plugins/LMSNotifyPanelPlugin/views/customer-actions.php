<table class="lmsbox lms-ui-tab-container" id="lnp-customer-notifications"><thead><tr><td>Powiadomienia o zadłużeniu</td></tr></thead><tbody><tr><td>
<?php if(!empty($notification['status'])): ?><p><b><?= $e($notification['status']) ?></b><?php if(!empty($notification['time'])): ?> · <?= $e((new DateTimeImmutable('@'.$notification['time']))->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('d.m.Y H:i')) ?><?php endif ?></p><?php endif ?>
<form method="post" action="?m=notifypanel" id="lnp-customer-notify-form">
<input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="op" value="preview"><input type="hidden" name="customerid" value="<?= (int)$customer ?>"><input type="hidden" name="action" id="lnp-card-action" value="notify_manual">
<?php foreach(['notify_manual'=>'Powiadom mail','notify_history'=>'Powiadom z historią','notify_sms'=>'Powiadom SMS'] as $action=>$label): ?>
<button type="submit" class="lms-ui-button" onclick="document.getElementById('lnp-card-action').value='<?= $e($action) ?>'" <?= $action==='notify_sms' && (!$sms['enabled'] || !$sms['allow_manual_sms'])?'disabled title="Ręczne SMS-y są wyłączone w ustawieniach wtyczki"':'' ?>><span class="lms-ui-label"><?= $e($label) ?></span></button>
<?php endforeach ?>
<label><input type="checkbox" name="start_countdown" value="1" <?= (int)$settings['block_after_days']===0?'disabled':'' ?>> Odliczaj do blokady</label>
<?php if((int)$settings['block_after_days']===0): ?><span>Odliczanie wyłączone w ustawieniach wtyczki.</span><?php endif ?>
</form></td></tr></tbody></table>
