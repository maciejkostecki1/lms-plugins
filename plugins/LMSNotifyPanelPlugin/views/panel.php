<?php
$tab=$tab??'customers';$sort=$sort??'percent';$direction=$direction??'desc';$search=$search??0;
$url=static function(array $changes=[]) use($sort,$direction,$search) {return '?'.http_build_query(array_replace(['m'=>'notifypanel','tab'=>'customers','sort'=>$sort,'direction'=>$direction,'customerid'=>$search?:null],$changes));};
$labels=['block'=>'Do blokady','notify'=>'Do powiadomienia','ok'=>'Poniżej progów','review'=>'Do weryfikacji']; ?>
<style>
/* Layout only. Colours, typography, inputs, buttons and tables come from LMS. */
.lnp .lnp-statistics{display:flex;flex-wrap:wrap;gap:.6em 1.5em;margin:.6em}.lnp .lnp-statistics b{font-size:1.2em}
.lnp .lnp-section{margin:1em 0}.lnp .lnp-section>form,.lnp .lnp-section>p,.lnp .lnp-section>pre,.lnp .lnp-section>.scroll{margin:.6em}
.lnp .settings-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.5em 1.5em;margin-bottom:1em}.lnp .settings-grid label{min-width:0}.lnp .settings-grid small{display:block;white-space:normal;overflow-wrap:anywhere;margin-top:.35em}.lnp .settings-grid input[type="number"]{width:8em}.lnp .settings-grid input[type="email"],.lnp .settings-grid input[maxlength]{width:100%}@media(max-width:800px){.lnp .settings-grid{grid-template-columns:1fr}}
.lnp .scroll{overflow:auto}.lnp table{width:100%}.lnp .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:.5em 1em}
.lnp .lnp-tabs{display:flex;flex-wrap:wrap;gap:.5em;margin:1em 0}.lnp .lnp-tabs [aria-current="page"]{text-decoration:underline}.lnp .lnp-pagination{display:flex;gap:.7em;align-items:center;flex-wrap:wrap}
.lnp th,.lnp td{padding:.35em .5em;vertical-align:top}
.lnp label{display:block;margin:.5em 0}.lnp label span{display:block;margin-bottom:.25em}.lnp textarea{width:100%;min-height:7em;box-sizing:border-box}
.lnp input{max-width:100%;box-sizing:border-box}.lnp .inline{display:inline}.lnp summary{cursor:pointer}.lnp pre{white-space:pre-wrap}
.lnp .lnp-actions{white-space:nowrap}.lnp .lnp-message{margin:1em 0;padding:.7em}.lnp .lnp-summary td{width:25%}
.lnp .lnp-invoice-cards{align-self:stretch;min-width:0;display:grid;grid-template-columns:minmax(0,1fr);grid-auto-rows:1fr;gap:1em;margin:.6em}
.lnp .lnp-invoice-card{display:flex;flex-direction:column;width:100%;box-sizing:border-box;margin:0}
.lnp .lnp-invoice-card>form{align-self:stretch;display:flex;flex:1;flex-direction:column;min-width:0}
.lnp .lnp-invoice-card button{align-self:flex-start;margin-top:auto}.lnp .lnp-invoice-card textarea{height:16em}
.lnp .lnp-schedule>form{display:flex;gap:1em;align-items:flex-end;flex-wrap:wrap}.lnp .lnp-schedule label{margin:0}
.lnp .lnp-customer-note{white-space:pre-wrap;overflow-wrap:anywhere;max-width:38em;margin:.4em 0}.lnp .lnp-note-label{display:block}
.lnp .lnp-action-row{display:flex;align-items:center;flex-wrap:nowrap;width:max-content;gap:.35em;margin:.35em 0}.lnp .lnp-action-row form{margin:0}.lnp .lnp-action-row>form,.lnp .lnp-action-row>a{flex:0 0 auto}
.lnp .lnp-action-row .lms-ui-button{min-height:22px;height:22px;padding:1px 4px}
</style>
<div class="lnp">
<h1>Płatności i blokady</h1>
<?php if($error): ?><div class="lms-ui-box-container lnp-message red"><?= $e($error) ?></div><?php endif ?>
<?php if($notice): ?><div class="lms-ui-box-container lnp-message"><?= $e($notice) ?></div><?php endif ?>
<?php if(isset($settings)): ?>
<nav class="lms-ui-tab-button-panel lnp-tabs" aria-label="Zakładki panelu">
<?php foreach(['customers'=>'Klienci','settings'=>'Ustawienia wtyczki','invoices'=>'Powiadomienia o fakturach bieżących','exceptions'=>'Wyjątki','history'=>'Historia'] as $key=>$label): ?>
<a class="lms-ui-button lms-ui-link-button" href="<?= $e($url(['tab'=>$key])) ?>" <?= $tab===$key?'aria-current="page"':'' ?>><span class="lms-ui-label"><?= $e($label) ?></span></a>
<?php endforeach ?>
</nav>
<?php if($tab!=='invoices'): ?>
<table class="lmsbox lnp-summary"><thead><tr><th>Próg powiadomienia</th><th>Próg blokady internetu</th><th>Próg zawieszenia naliczania</th><th>Automatyka progów i blokad</th></tr></thead><tbody><tr>
<td class="bold"><?= $e($settings['notify_percent']) ?>%</td><td class="bold"><?= $e($settings['block_percent']) ?>%</td><td class="bold"><?= $e($settings['suspend_percent']) ?>%</td>
<td><?= ($settings['auto_notify']||$settings['auto_block']||$settings['auto_unblock'])?'Włączona':'Wyłączona' ?></td>
</tr></tbody></table>
<?php endif ?>

<?php if($tab==='customers'): ?>
<?php require __DIR__.'/bulk.php'; ?>
<?php if($preview): $c=$preview['customer']; ?>
<div class="lms-ui-box-container lnp-section"><h3 class="lms-ui-box-header">Podgląd: <?= $e(['notify'=>'powiadomienie','notify_manual'=>'powiadomienie e-mail','notify_sms_no_mail'=>'powiadomienie SMS','notify_sms'=>'powiadomienie SMS','notify_history'=>'powiadomienie z historią płatności','block'=>'blokada','unblock'=>'odblokowanie'][$preview['action']]) ?></h3>
<p><b>#<?= $e($c['id']) ?> <?= $e($c['customername']) ?></b> · saldo <?= $e(number_format($c['balance'],2,',',' ')) ?> zł · opłaty miesięczne brutto po rabatach <?= $e(number_format($c['base'],2,',',' ')) ?> zł · zadłużenie <?= $e($c['percent']??'—') ?>%</p>
<?php if($c['excluded']): ?><div class="lms-ui-box-container lnp-message red">Wyjątek: <?= $e($c['exception']) ?>. Wykonanie zablokowane.</div><?php endif ?>
<?php if(!in_array($preview['action'],['notify_sms_no_mail','notify_sms'],true) && !empty($preview['start_countdown'])): ?><p>Odliczanie do blokady: <b>TAK</b></p><?php endif ?>
<?php if(in_array($preview['action'],['notify_sms_no_mail','notify_sms'],true)): ?><p>Odliczanie do blokady: <b><?= !empty($preview['start_countdown'])?'TAK':'NIE' ?></b></p><p>Numery: <?= $e(implode(', ',$preview['recipients']??[])) ?></p><?php endif ?>
<?php if(in_array($preview['action'],['notify','notify_manual','notify_history','notify_sms_no_mail','notify_sms'],true)): ?><?php if(!in_array($preview['action'],['notify_sms_no_mail','notify_sms'],true)): ?><h4><?= $e($preview['subject']) ?></h4><pre><?= $e($preview['body']) ?></pre><?php endif ?><?php if(!empty($preview['sms_body'])): ?><h4>Treść SMS</h4><pre><?= $e($preview['sms_body']) ?></pre><?php endif ?><p >Kanały: <?= $e(implode(', ',in_array($preview['action'],['notify_sms_no_mail','notify_sms'],true)?['sms']:['mail'])) ?>.</p>
<?php $smsEstimate=$preview['sms_estimate']??null; require __DIR__.'/sms-estimate.php'; ?>
<?php if(!empty($preview['attachment'])): ?><p><b>Załącznik: <?= $e($preview['attachment']['filename']) ?></b> · pozycji: <?= (int)$preview['attachment']['count'] ?> · odbiorcy: <?= $e(implode(', ',$preview['recipients'])) ?: 'Brak uprawnionych odbiorców — nic nie zostanie wysłane.' ?></p><details><summary>Podgląd załącznika HTML</summary><iframe title="Historia płatności klienta" sandbox="" referrerpolicy="no-referrer" style="width:100%;height:460px;border:1px solid #aaa" srcdoc="<?= $e($preview['attachment']['data']) ?>"></iframe></details><?php endif ?>
<?php endif ?>
<?php if(!empty($preview['block_notice'])): ?><p><b><?= $e($preview['block_notice']['reason']) ?></b><?php if($preview['block_notice']['due_at']): ?> Najwcześniej: <?= $e(date('d.m.Y H:i:s',$preview['block_notice']['due_at'])) ?><?php endif ?></p><?php endif ?>

<form method="post"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="execute"><input type="hidden" name="token" value="<?= $e($preview['token']) ?>"><button <?= $preview['action']==='block' && empty($c['owned_blocks']) && empty($preview['block_notice']['ready'])?'disabled':'' ?> class="lms-ui-button <?= $preview['action']==='block'?'lms-ui-error':'' ?>"><span class="lms-ui-label"><?= $preview['action']==='notify_history'?'Potwierdź wysyłkę z historią płatności':'Potwierdź wykonanie dla tego klienta' ?></span></button> <a class="lms-ui-button lms-ui-link-button" href="?m=notifypanel">Anuluj</a></form>
</div><?php endif ?>
<div class="lms-ui-box-container lnp-section"><h3 class="lms-ui-box-header">Klienci</h3>
<?php if(isset($statistics)): ?>
<div class="lnp-statistics" role="status" aria-label="Globalne statystyki zadłużenia">
<span>Zadłużeni abonamentowo: <b><?= (int)$statistics['debtors'] ?></b></span>
<span>Do blokady — próg <?= (int)$settings['block_percent'] ?>%: <b><?= (int)($statistics['block_threshold']??$statistics['block']) ?></b></span>
<span>Gotowi do blokady teraz: <b><?= (int)$statistics['block'] ?></b></span>
<span>Pozostali zadłużeni: <b><?= (int)($statistics['below_block_threshold']??$statistics['remaining']) ?></b></span>
<span>Odbiorcy SMS bez e-maila: <b><?= (int)$statistics['sms']['customers'] ?></b></span>
</div>
<?php $smsEstimate=$statistics['sms']; require __DIR__.'/sms-estimate.php'; ?>
<details style="margin:.6em"><summary>Cała lista · zasady liczników</summary><p>Próg blokady: zadłużenie abonamentowe po terminie względem opłat miesięcznych. Wśród klientów ponad progiem wyjątek lub weryfikacja: <?= (int)($statistics['threshold_held']??0) ?>. Gotowi teraz: obecne warunki blokady, w tym termin ostrzeżenia i wyjątki; także już zablokowani spełniający reguły. W trybie czasowym gotowy może być również klient poniżej progu procentowego. SMS: zgody, numery i odstęp wysyłki; przy wyłączonej wysyłce ręcznej — 0. Nieprawidłowe treści: <?= (int)$statistics['invalid_messages'] ?>.</p></details>
<?php endif ?>
<form method="post"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="bulk_preview"><button type="submit" class="lms-ui-button"><span class="lms-ui-label">Wyślij powiadomienie do wszystkich zadłużonych — e-mail</span></button> <label class="inline"><input type="checkbox" name="start_countdown" value="1" <?= (int)$settings['block_after_days']===0?'disabled':'' ?>> Uruchom odliczanie do blokady (niezależnie od automatycznej blokady)</label></form>
<form method="post"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="bulk_sms_preview"><button type="submit" class="lms-ui-button"><span class="lms-ui-label">Wyślij SMS do wszystkich zadłużonych bez e-maila</span></button> <label class="inline"><input type="checkbox" name="start_countdown" value="1" <?= (int)$settings['block_after_days']===0?'disabled':'' ?>> Uruchom odliczanie do blokady</label></form>
<form method="post"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="bulk_history_preview"><button type="submit" class="lms-ui-button"><span class="lms-ui-label">Powiadom wszystkich zadłużonych z historią płatności</span></button> <label class="inline"><input type="checkbox" name="start_countdown" value="1" <?= (int)$settings['block_after_days']===0?'disabled':'' ?>> Uruchom odliczanie do blokady (niezależnie od automatycznej blokady)</label></form>
<?php if(!empty($bulkJobs)): ?><p>Twoje ostatnie wysyłki: <?php foreach($bulkJobs as $bj): ?><a href="?m=notifypanel&amp;bulkjob=<?= (int)$bj['id'] ?>">#<?= (int)$bj['id'] ?> (<?= (int)$bj['total'] ?> klientów)</a> <?php endforeach ?></p><?php endif ?>
<form method="get" id="lnp-customer-filter"><input type="hidden" name="m" value="notifypanel"><input type="hidden" name="tab" value="customers"><input type="hidden" name="sort" value="<?= $e($sort) ?>"><input type="hidden" name="direction" value="<?= $e($direction) ?>"><div><span>Wyszukaj klienta LMS</span><?= $customerSelector??'' ?></div> <button type="submit" class="lms-ui-button"><span class="lms-ui-label">Pokaż</span></button> <a href="?m=notifypanel">Wszyscy</a></form>

<p><a class="lms-ui-button lms-ui-link-button" href="<?= $e($url(['refresh'=>1])) ?>"><span class="lms-ui-label">Odśwież dane</span></a><?php if(!empty($snapshotTime)): ?> <span class="lms-ui-font-smaller">Stan z <?= $e(date('H:i:s',$snapshotTime)) ?></span><?php endif ?></p>
<div class="scroll"><table class="lmsbox lms-ui-background-cycle"><thead><tr>
<?php foreach(['id'=>'Nr','customername'=>'Klient','balance'=>'Saldo','debt'=>'Dług abonamentowy (zł)','base'=>'Miesięcznie brutto','percent'=>'Dług / opłaty (%)','state'=>'Ocena'] as $column=>$label): $next=$sort===$column?($direction==='asc'?'desc':'asc'):(in_array($column,['debt','base','percent'])?'desc':'asc'); ?>
<th scope="col" <?= $sort===$column?'aria-sort="'.($direction==='asc'?'ascending':'descending').'"':'' ?>><a href="<?= $e($url(['sort'=>$column,'direction'=>$next])) ?>"><?= $e($label) ?><?= $sort===$column?($direction==='asc'?' ↑':' ↓'):'' ?></a></th>
<?php endforeach ?><th>Działanie</th></tr></thead><tbody>
<?php foreach(($rows??[]) as $c): ?>
<tr><td><?= $e($c['id']) ?></td><td><a href="?m=customerinfo&amp;id=<?= $e($c['id']) ?>"><?= $e($c['customername']) ?></a>
<?php if(!empty($c['email_status'])): ?><p class="lms-ui-font-smaller bold <?= in_array($c['email_status'],['Brak e-maila','Nieprawidłowy adres e-mail'],true)?'red':'' ?>"><?= $e($c['email_status']) ?></p><?php endif ?>
<?php if(!empty($c['customer_info'])): ?><p class="lnp-customer-note lms-ui-font-smaller"><b class="lnp-note-label">Informacje:</b><?= $e($c['customer_info']) ?></p><?php endif ?>
<?php if(!empty($c['customer_notes'])): ?><p class="lnp-customer-note lms-ui-font-smaller"><b class="lnp-note-label">Notatki:</b><?= $e($c['customer_notes']) ?></p><?php endif ?>
<?php if(!empty($c['last_note']) && $c['last_note']!==($c['customer_notes']??'')): ?><p class="lnp-customer-note lms-ui-font-smaller"><b class="lnp-note-label">Ostatnia notatka<?= !empty($c['last_note_time'])?' · '.$e(date('d.m.Y H:i',$c['last_note_time'])):'' ?>:</b><?= $e($c['last_note']) ?></p><?php endif ?>
</td>
<td><?= $e(number_format($c['balance'],2,',',' ')) ?> zł<?php if(!empty($c['oneoff_debt'])): ?><p class="lms-ui-font-smaller">w tym jednorazowe: <?= $e(number_format($c['oneoff_debt'],2,',',' ')) ?> zł</p><?php endif ?></td><td><?= $e(number_format($c['debt'],2,',',' ')) ?> zł<?php if(isset($c['overdue_debt'])): ?><p class="lms-ui-font-smaller">Po terminie: <?= $e(number_format($c['overdue_debt'],2,',',' ')) ?> zł</p><?php endif ?></td><td><?= $e(number_format($c['base'],2,',',' ')) ?> zł</td><td><?= $c['percent']===null?'—':$e($c['percent']).'%' ?></td>
<td><span class="bold <?= $c['state']==='block'?'red':'' ?>"><?= $e($labels[$c['state']]) ?></span><?php if($c['reason']): ?><p class="lms-ui-font-smaller"><?= $e($c['reason']) ?></p><?php endif ?><?php if(!empty($c['owned_billing'])): ?><p class="lms-ui-font-smaller">Naliczanie zawieszone przez wtyczkę: <?= (int)$c['owned_billing'] ?></p><?php endif ?><?php if($c['owned_blocks']): ?><p class="lms-ui-font-smaller">Blokady wtyczki: <?= $e($c['owned_blocks']) ?></p><?php endif ?>
<?php if(!empty($c['block_notice']) && $c['debt']>0.009): ?><p class="lms-ui-font-smaller"><b><?= $e($c['block_notice']['reason']) ?></b><?php if($c['block_notice']['due_at']): ?><br>Odliczanie od: <?= $e((new DateTimeImmutable('@'.$c['block_notice']['sent_at']))->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('d.m.Y H:i:s')) ?><br>Najwcześniejsza blokada: <?= $e((new DateTimeImmutable('@'.$c['block_notice']['due_at']))->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('d.m.Y H:i:s')) ?><?php endif ?></p><?php endif ?>
<?php if(!empty($c['notification_status'])): ?><p class="lms-ui-font-smaller"><b><?= $e($c['notification_status']) ?></b><?php if(!empty($c['notification_time'])): ?><br><?= $e((new DateTimeImmutable('@'.$c['notification_time']))->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('d.m.Y H:i')) ?><?php endif ?></p><?php endif ?>
</td><td>
<?php if(!$c['excluded'] && $c['state']!=='review' && $c['debt']>0.009 && NotifyPolicy::notificationDue($c,$settings)): ?><div class="lnp-action-row">
<?php $notificationActions=!empty($c['no_email'])?['notify_sms_no_mail'=>'Powiadom SMS']:(!empty($c['mail_ready'])?['notify_manual'=>'Powiadom e-mail','notify_history'=>'Powiadom z historią']:[]); foreach($notificationActions as $a=>$label): ?>
<form method="post" class="inline"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="preview"><input type="hidden" name="customerid" value="<?= $e($c['id']) ?>"><input type="hidden" name="action" value="<?= $e($a) ?>"><button type="submit" class="lms-ui-button" <?= $a==='notify_sms_no_mail' && empty($c['sms_ready'])?'disabled title="Brak zgody SMS lub numeru do powiadomień"':'' ?>><span class="lms-ui-label"><?= $e($label) ?></span></button></form>
<?php endforeach ?></div><?php endif ?>
<?php if($c['excluded']): ?><p class="lms-ui-font-smaller lnp-customer-note"><b>Wyjątek:</b> <?= $e($c['exception']) ?></p><?php endif ?>
<?php if(!empty($c['panel_exception']) && !empty($c['lms_protected'])): ?><p class="lms-ui-font-smaller">Dodatkowo działa ochrona klienta w LMS.</p><?php endif ?>
<div class="lnp-action-row">
<?php if(!$c['excluded'] && $c['state']!=='review'): foreach(['block'=>'Zablokuj','unblock'=>'Odblokuj'] as $a=>$label): if(($a==='block' && ($c['state']==='block' || ((int)$settings['block_after_days']>0 && $c['debt']>0.009 && !empty($c['block_notice']['ready'])))) || ($a==='unblock' && $c['debt']<=0 && $c['owned_blocks'])): ?>
<form method="post" class="inline"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="preview"><input type="hidden" name="customerid" value="<?= $e($c['id']) ?>"><input type="hidden" name="action" value="<?= $e($a) ?>"><button type="submit" class="lms-ui-button"><span class="lms-ui-label <?= $a==='block'?'red':'' ?>"><?= $e($label) ?></span></button></form>
<?php endif; endforeach; endif ?>
<a class="lms-ui-button lms-ui-link-button" href="?m=notifypanel&amp;tab=exceptions&amp;customerid=<?= (int)$c['id'] ?>"><span class="lms-ui-label"><?= !empty($c['panel_exception'])?'Zmień wyjątek':'Dodaj wyjątek' ?></span></a>
<?php if(!empty($c['panel_exception'])): ?><form method="post" class="inline"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="exception"><input type="hidden" name="customerid" value="<?= (int)$c['id'] ?>"><input type="hidden" name="days" value="0"><input type="hidden" name="return_tab" value="customers"><input type="hidden" name="return_sort" value="<?= $e($sort) ?>"><input type="hidden" name="return_direction" value="<?= $e($direction) ?>"><input type="hidden" name="return_page" value="<?= (int)($pagination['page']??1) ?>"><input type="hidden" name="return_customerid" value="<?= (int)$search ?>"><button type="submit" class="lms-ui-button"><span class="lms-ui-label">Usuń wyjątek</span></button></form><?php endif ?>
</div>
</td></tr>
<?php endforeach ?>
<?php if(empty($rows)): ?><tr><td colspan="8">Brak klientów na tej stronie.</td></tr><?php endif ?>
</tbody></table></div>
<?php if(!empty($pagination)): ?><p class="lnp-pagination">
<?php if($pagination['page']>1): ?><a class="lms-ui-button lms-ui-link-button" href="<?= $e($url(['page'=>$pagination['page']-1])) ?>">← Poprzednia</a><?php endif ?>
<span>Strona <?= $e($pagination['page']) ?> z <?= $e($pagination['pages']) ?> · klientów: <?= $e($pagination['total']) ?></span>
<?php if($pagination['page']<$pagination['pages']): ?><a class="lms-ui-button lms-ui-link-button" href="<?= $e($url(['page'=>$pagination['page']+1])) ?>">Następna →</a><?php endif ?>
</p><?php endif ?>
</div>
<?php endif ?>
<?php if($tab==='settings'): ?>
<h2>Ustawienia wtyczki</h2>
<?php require __DIR__.'/sms-settings.php'; ?>
<div class="lms-ui-box-container lnp-section"><h3 class="lms-ui-box-header">Ustawienia progów, wiadomości i automatyki</h3>

<form method="post"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="settings"><input type="hidden" name="revision" value="<?= $e($settings['revision']) ?>">
<div class="settings-grid">
<label><span>Powiadomienie od (%)</span><input name="notify_percent" type="number" min="1" max="9999" required value="<?= $e($settings['notify_percent']) ?>"></label>
<label><span>Blokada od (%)</span><input name="block_percent" type="number" min="2" max="10000" required value="<?= $e($settings['block_percent']) ?>"></label>
<label><span>Zawieszenie naliczania od (%)</span><input name="suspend_percent" type="number" min="1" max="10000" required value="<?= $e($settings['suspend_percent']) ?>"></label>
</div>
<div class="settings-grid">
<label><span>Zablokuj po … dniach od wysłania powiadomienia</span><input name="block_after_days" type="number" min="0" max="365" required value="<?= $e($settings['block_after_days']) ?>"></label>
<label><span>Odstęp przypomnień (dni)</span><input name="repeat_days" type="number" min="1" max="365" required value="<?= $e($settings['repeat_days']) ?>"></label>
</div>
<div class="settings-grid">
<label><span>Adres nadawcy e-mail</span><input name="sender_email" type="email" value="<?= $e($settings['sender_email']) ?>"></label>
<label><span>Nazwa nadawcy</span><input name="sender_name" maxlength="250" value="<?= $e($settings['sender_name']) ?>"></label>
<label><span>Temat</span><input name="subject" maxlength="250" required value="<?= $e($settings['subject']) ?>"></label>
</div>
<label><span>Treść powiadomienia ręcznego e-mail</span><textarea name="body" required maxlength="8000"><?= $e($settings['body']) ?></textarea></label>
<label><span>Treść powiadomienia ręcznego SMS</span><textarea id="lnp-sms-template" name="sms_body" required maxlength="2000"><?= $e($settings['sms_body']) ?></textarea><small>%debt — kwota; %company — podpis podmiotu; %payment_request — prośba z terminem; %days — liczba dni. Limit: 1 SMS.</small></label>
<?php require __DIR__.'/sms-preview.php'; ?>

<?php foreach(['mail'=>'E-mail','sms'=>'SMS'] as $ch=>$label): ?><label><input type="checkbox" name="channels[]" value="<?= $e($ch) ?>" <?= in_array($ch,$settings['channels'])?'checked':'' ?>> <?= $e($label) ?></label><?php endforeach ?>

<?php foreach(['auto_notify'=>'Automatyczne powiadomienia','auto_block'=>'Automatyczna blokada internetu i zawieszenie naliczania według osobnych progów','auto_unblock'=>'Automatyczne odblokowanie po spłacie długu abonamentowego'] as $key=>$label): ?><label><input type="checkbox" name="<?= $e($key) ?>" <?= $settings[$key]?'checked':'' ?>> <?= $e($label) ?></label><?php endforeach ?>

<button class="lms-ui-button"><span class="lms-ui-label">Zapisz ustawienia</span></button>
</form></div>
<?php endif ?>
<?php if($tab==='invoices')require __DIR__.'/invoices.php'; ?>
<?php if($tab==='exceptions'): ?>
<div class="lms-ui-box-container lnp-section"><h3 class="lms-ui-box-header">Wyjątki dla klientów</h3>
<div class="scroll"><table class="lmsbox lms-ui-background-cycle"><thead><tr><th>Klient</th><th>Wyjątek do</th><th>Powód</th><th>Działanie</th></tr></thead><tbody>
<?php foreach(($exceptions??[]) as $x): ?><tr><td>#<?= $e($x['customerid']) ?> <?= $e(trim(($x['lastname']??'').' '.($x['name']??''))) ?></td><td><?= $e(date('Y-m-d H:i',(int)$x['until_time'])) ?></td><td><?= $e($x['reason']) ?></td><td><form method="post"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="exception"><input type="hidden" name="customerid" value="<?= (int)$x['customerid'] ?>"><input type="hidden" name="days" value="0"><button type="submit" class="lms-ui-button"><span class="lms-ui-label">Usuń wyjątek</span></button></form></td></tr><?php endforeach ?>
<?php if(empty($exceptions)): ?><tr><td colspan="4">Brak aktywnych wyjątków.</td></tr><?php endif ?>
</tbody></table></div><form method="post" id="lnp-exception-form"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="exception">
<div class="grid"><div><span>Wyszukaj klienta LMS</span><?= $customerSelector??'' ?></div><label><span>Liczba dni (0 usuwa wyjątek)</span><input name="days" type="number" min="0" max="365" value="30" required></label><label><span>Powód</span><input name="reason" maxlength="500"></label></div><button type="submit" class="lms-ui-button"><span class="lms-ui-label">Zapisz wyjątek</span></button></form></div>
<?php endif ?>
<?php if($tab==='history'): ?>
<div class="lms-ui-box-container lnp-section"><h3 class="lms-ui-box-header">Historia SMS — ostatnie 100</h3><div class="scroll"><table class="lmsbox lms-ui-background-cycle"><thead><tr><th>Aktualizacja</th><th>Klient</th><th>Numer</th><th>Status SMSAPI</th><th>ID SMSAPI</th><th>Pozycja LMS</th></tr></thead><tbody>
<?php foreach(($smsHistory??[]) as $h): ?><tr><td><?= $e(date('Y-m-d H:i:s',(int)$h['updated_at'])) ?></td><td><a href="?m=customerinfo&amp;id=<?= (int)$h['customerid'] ?>"><?= $e(trim((string)($h['customername']??''))?:'Klient #'.(int)$h['customerid']) ?></a></td><td><?= $e($h['number']) ?></td><td><?= $e($h['gateway_status']) ?></td><td><?= $e($h['externalid']??'—') ?></td><td><?= (int)$h['itemid'] ?></td></tr><?php endforeach ?>
</tbody></table></div><p>QUEUE / ACCEPTED oznacza przyjęcie do kolejki. DELIVERED jest raportem doręczenia operatora.</p></div>
<div class="lms-ui-box-container lnp-section"><h3 class="lms-ui-box-header">Historia operacji — ostatnie 100</h3><div class="scroll"><table class="lmsbox lms-ui-background-cycle"><thead><tr><th>Czas</th><th>Klient</th><th>Operacja</th><th>Wynik</th><th>Szczegóły</th><th>Operator</th></tr></thead><tbody>
<?php foreach(($history??[]) as $h): ?><tr><td><?= $e(date('Y-m-d H:i:s',(int)$h['created_at'])) ?></td><td><?= $e($h['customerid']) ?></td><td><?= $e($h['action']) ?></td><td><?= $e($h['result']) ?></td><td><?= $e($h['details']) ?></td><td><?= $e($h['userid']?:'Harmonogram') ?></td></tr><?php endforeach ?>
</tbody></table></div></div>
<?php endif ?>
<?php endif ?>
</div>
