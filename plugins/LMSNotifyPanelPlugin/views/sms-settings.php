<?php require_once __DIR__.'/../lib/NotifySmsSettings.php'; $smsSettings=$smsSettings??NotifySmsSettings::defaults()+['revision'=>0,'has_token'=>false]; ?>
<div class="lms-ui-box-container lnp-section"><h3 class="lms-ui-box-header">Bramka SMSAPI</h3>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="smsapi_settings"><input type="hidden" name="revision" value="<?= (int)$smsSettings['revision'] ?>">
<label><input type="checkbox" name="enabled" value="1" <?= $smsSettings['enabled']?'checked':'' ?>> Włącz integrację SMSAPI</label>
<label><span>Token API SMSAPI</span><input type="password" name="token" value="" autocomplete="new-password" maxlength="4096" placeholder="<?= $smsSettings['has_token']?'Token zapisany — puste pole zachowuje obecny':'Wprowadź token API' ?>"></label>
<label><input type="checkbox" name="delete_token" value="1"> Usuń zapisany token</label>
<div class="grid"><label><span>Nadawca SMS</span><input name="sender" maxlength="11" required value="<?= $e($smsSettings['sender']) ?>"></label><label><span>Numer testowy</span><input type="tel" name="test_number" value="<?= $e($smsSettings['test_number']) ?>"></label></div>
<label><input type="checkbox" name="allow_manual_sms" value="1" <?= $smsSettings['allow_manual_sms']?'checked':'' ?>> Zezwól na ręczne SMS-y po zatwierdzeniu podglądu</label>
<p>Automatyczne SMS-y do klientów: <b>wyłączone</b>.</p>
<button type="submit" class="lms-ui-button"><span class="lms-ui-label">Zapisz ustawienia SMSAPI</span></button></form>
<form method="post"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="smsapi_test"><button type="submit" class="lms-ui-button"><span class="lms-ui-label">Test połączenia — bez wysyłki SMS</span></button></form>
</div>
