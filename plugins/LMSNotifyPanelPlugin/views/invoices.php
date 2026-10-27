<div class="lms-ui-box-container lnp-section"><h3 class="lms-ui-box-header">Powiadomienia o fakturach bieżących</h3>
<?php if($tab==='settings'): ?>
<div class="lms-ui-box-container lnp-section lnp-schedule"><h3 class="lms-ui-box-header">Wspólna godzina wysyłki</h3>

<form method="post"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="invoice_schedule">
<label><span>Godzina automatycznej wysyłki</span><input type="time" name="send_time" step="60" required value="<?= $e($invoiceSendTime??'08:00') ?>"></label>
<button type="submit" class="lms-ui-button"><span class="lms-ui-label">Zapisz godzinę</span></button></form></div>


<div class="lnp-invoice-cards">
<?php foreach(($invoiceProfiles??[]) as $ip): ?>
<div class="lms-ui-box-container lnp-section lnp-invoice-card"><h3 class="lms-ui-box-header"><?= $e($ip['label']) ?></h3>
<p><b><?= $e($ip['schedule']) ?></b></p>
<form method="post"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="invoice_settings"><input type="hidden" name="section" value="<?= $e($ip['section']) ?>">
<p><label><input type="checkbox" name="enabled" value="1" <?= !empty($ip['enabled'])?'checked':'' ?>> Włącz automatyczne wysyłanie tego powiadomienia</label></p>
<div class="grid"><label><span><?= $ip['type']==='reminder'?'Dni przed terminem faktury':'Minimalna liczba dni po terminie' ?></span><input type="number" name="days" min="1" max="365" required value="<?= $e($ip['days']) ?>"></label>
<label><span>Nadawca e-mail</span><input type="email" name="sender_email" required value="<?= $e($ip['sender_email']) ?>"></label>
<label><span>Nazwa nadawcy</span><input name="sender_name" maxlength="250" value="<?= $e($ip['sender_name']) ?>"></label></div>
<label><span>Temat wiadomości</span><input name="subject" style="width:100%" maxlength="250" required value="<?= $e($ip['subject']) ?>"></label>
<label><span>Treść wiadomości</span><textarea name="message" style="min-height:16em" maxlength="16000" required><?= $e($ip['message']) ?></textarea></label>

<button type="submit" class="lms-ui-button"><span class="lms-ui-label">Zapisz ten szablon</span></button></form></div>
<?php endforeach ?></div>
<?php endif ?>
<?php if($tab==='invoices' && isset($currentInvoices)): ?>
<div class="lms-ui-box-container lnp-section"><h3 class="lms-ui-box-header">Bieżące faktury — podgląd bez wysyłki</h3>
<p>Faktury abonamentowe z pozostałą kwotą do zapłaty: <?= count($currentInvoices) ?>.</p>
<table class="lmsbox"><thead><tr><th>Klient</th><th>Faktura</th><th>Termin</th><th>Pozostało</th><th>Ocena</th></tr></thead><tbody>
<?php foreach(array_slice($currentInvoices,0,50) as $ci): ?><tr><td><a href="?m=customerinfo&amp;id=<?= (int)$ci['customerid'] ?>">#<?= (int)$ci['customerid'] ?></a></td><td><?= $e(docnumber(['number'=>$ci['number'],'template'=>$ci['template']?:'%N/LMS/%Y','cdate'=>$ci['cdate'],'customerid'=>$ci['customerid']])) ?></td><td><?= $e((new DateTimeImmutable('@'.(int)$ci['cdate']))->setTimezone(new DateTimeZone('Europe/Warsaw'))->modify('+'.(int)$ci['paytime'].' days')->format('d.m.Y')) ?></td><td><?= $e(number_format((float)$ci['unpaid'],2,',',' ')) ?> zł</td><td><?= NotifyCurrentInvoice::eligible($ci,(int)($invoiceProfiles[1]['days']??3),time())?'Po terminie — do sprawdzenia zgód i wyjątków':'Przed progiem powiadomienia' ?></td></tr><?php endforeach ?>
</tbody></table><?php if(count($currentInvoices)>50): ?><p>Wyświetlono pierwszych 50 faktur.</p><?php endif ?></div>
<?php endif ?></div>
