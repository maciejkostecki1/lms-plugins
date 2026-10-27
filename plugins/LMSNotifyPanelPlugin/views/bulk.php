<?php if(!empty($bulkPreview)): $historyMode=($bulkPreview['mode']??'plain')==='history';$smsMode=($bulkPreview['mode']??'plain')==='sms_no_mail'; ?>
<div class="lms-ui-box-container lnp-section"><h3 class="lms-ui-box-header">Podgląd ręcznej wysyłki do wszystkich zadłużonych<?= $historyMode?' z historią płatności':($smsMode?' — SMS, tylko bez e-maila':' — e-mail') ?></h3>
<p><b>Odliczanie do blokady: <?= !empty($bulkPreview['start_countdown'])?'TAK':'NIE' ?></b></p>
<p><b>Odbiorcy: <?= count($bulkPreview['rows']) ?> klientów.</b></p>
<p>Pominięto: wyjątki — <?= (int)$bulkPreview['skipped']['excluded'] ?>; dane do weryfikacji — <?= (int)$bulkPreview['skipped']['review'] ?>; brak zgody, kontaktu lub wymagany odstęp — <?= (int)$bulkPreview['skipped']['contact'] ?>.</p>
<?php if(!empty($bulkPreview['message'])): ?><h4>Przykładowa wiadomość dla pierwszego odbiorcy</h4><?php if(!$smsMode): ?><h4><?= $e($bulkPreview['message']['subject']) ?></h4><pre><?= $e($bulkPreview['message']['body']) ?></pre><?php endif ?><?php if(!empty($bulkPreview['message']['sms_body'])): ?><h4>Treść SMS</h4><pre><?= $e($bulkPreview['message']['sms_body']) ?></pre><?php endif ?><?php endif ?>
<p>Kanały: <?= $e(implode(', ',$smsMode?['sms']:['mail'])) ?>. Odstęp od ostatniego powiadomienia: <?= (int)$settings['repeat_days'] ?> dni.</p>

<details><summary>Lista odbiorców (<?= count($bulkPreview['rows']) ?>)</summary><div class="scroll"><table class="lmsbox"><thead><tr><th>Klient</th><th>Dług abonamentowy</th><th>Całe saldo</th><th>Kanały</th><?php if($historyMode): ?><th>Osobny załącznik klienta</th><?php endif ?><?php if($smsMode): ?><th>Treść SMS</th><?php endif ?></tr></thead><tbody>
<?php foreach($bulkPreview['rows'] as $bc): ?><tr><td>#<?= (int)$bc['id'] ?> <?= $e($bc['customername']) ?></td><td><?= $e(number_format($bc['debt'],2,',',' ')) ?> zł</td><td><?= $e(number_format($bc['balance'],2,',',' ')) ?> zł</td><td><?= $e(implode(', ',$bc['channels'])) ?></td><?php if($historyMode): ?><td><?= $e($bc['attachment_name']) ?> · pozycji: <?= (int)$bc['attachment_count'] ?><form method="post" target="_blank" rel="noopener"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="bulk_attachment_preview"><input type="hidden" name="token" value="<?= $e($bulkPreview['token']) ?>"><input type="hidden" name="customerid" value="<?= (int)$bc['id'] ?>"><button type="submit" class="lms-ui-button"><span class="lms-ui-label">Podgląd historii klienta #<?= (int)$bc['id'] ?></span></button></form></td><?php endif ?><?php if($smsMode): ?><td><?= $e($bc['sms_body']) ?></td><?php endif ?></tr><?php endforeach ?>
</tbody></table></div></details>
<?php if($bulkPreview['rows']): ?><form method="post"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="bulk_start"><input type="hidden" name="token" value="<?= $e($bulkPreview['token']) ?>"><button type="submit" class="lms-ui-button"><span class="lms-ui-label">Potwierdź wysyłkę<?= $historyMode?' z osobnymi historiami płatności':($smsMode?' SMS (bez e-maila)':' e-mail') ?> do <?= count($bulkPreview['rows']) ?> klientów</span></button> <a href="?m=notifypanel">Anuluj</a></form><?php else: ?><p>Brak odbiorców spełniających warunki. Nic nie wysłano.</p><?php endif ?></div>
<?php endif ?>
<?php if(!empty($bulkJob)): ?>
<div class="lms-ui-box-container lnp-section" id="lnp-bulk-progress"><h3 class="lms-ui-box-header">Ręczna wysyłka #<?= (int)$bulkJob['id'] ?><?= ($bulkJob['mode']??'plain')==='history'?' — z osobnymi historiami płatności':(($bulkJob['mode']??'plain')==='sms_no_mail'?' — SMS bez e-maila':' — e-mail') ?></h3>
<p id="lnp-bulk-status"><?= $bulkJob['status']==='running'?'Wysyłka potwierdzona. Pozostaw tę stronę otwartą podczas przetwarzania.':'Przetwarzanie zakończone lub zatrzymane.' ?></p>
<p id="lnp-bulk-counts">Łącznie: <?= (int)$bulkJob['total'] ?> · przyjęte do wysyłki: <?= (int)$bulkJob['counts']['done'] ?> · oczekujące: <?= (int)$bulkJob['counts']['queued'] ?> · pominięte: <?= (int)$bulkJob['counts']['skipped'] ?> · błędy: <?= (int)$bulkJob['counts']['error'] ?> · do sprawdzenia: <?= (int)$bulkJob['counts']['working'] ?></p>
<?php if($bulkJob['status']==='running'): ?><button type="button" class="lms-ui-button" id="lnp-bulk-resume"><span class="lms-ui-label">Kontynuuj potwierdzoną wysyłkę</span></button>
<form method="post" class="inline"><input type="hidden" name="csrf" value="<?= $e($_SESSION['lnp_csrf']) ?>"><input type="hidden" name="op" value="bulk_cancel"><input type="hidden" name="jobid" value="<?= (int)$bulkJob['id'] ?>"><button type="submit" class="lms-ui-button"><span class="lms-ui-label">Zatrzymaj pozostałą wysyłkę</span></button></form><?php endif ?>
<p><a href="?m=notifypanel&amp;bulkjob=<?= (int)$bulkJob['id'] ?>">Odśwież wyniki i szczegóły pominięć</a></p>
<?php foreach($bulkJob['problems'] as $bp): ?><p>#<?= (int)$bp['customerid'] ?>: <?= $e($bp['details']) ?></p><?php endforeach ?></div>
<?php if($bulkJob['status']==='running'): ?>
<script>
(()=>{
    const button=document.getElementById('lnp-bulk-resume'),status=document.getElementById('lnp-bulk-status'),counts=document.getElementById('lnp-bulk-counts');
    let running=false;
    const run=async()=>{
        if(running)return;running=true;button.disabled=true;
        try{
            while(running){
                const response=await fetch('?m=notifypanel',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({op:'bulk_process',jobid:<?= json_encode((string)$bulkJob['id']) ?>,csrf:<?= json_encode($_SESSION['lnp_csrf'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>})});
                const data=await response.json();if(!response.ok||data.error)throw new Error(data.error||'Nie udało się odczytać postępu.');
                const c=data.counts;
                counts.textContent=`Łącznie: ${data.total} · przyjęte do wysyłki: ${c.done} · oczekujące: ${c.queued} · pominięte: ${c.skipped} · błędy: ${c.error} · do sprawdzenia: ${c.working}`;
                status.textContent=data.status==='running'?'Trwa wysyłka. Pozostaw stronę otwartą.':'Przetwarzanie zakończone lub zatrzymane. Odśwież wyniki, aby sprawdzić szczegóły.';
                if(data.status!=='running'){running=false;button.hidden=true;break;}
            }
        }catch(error){status.textContent='Przetwarzanie wstrzymane: '+error.message;running=false;button.disabled=false;}
    };
    button.addEventListener('click',run);
    <?php if(!empty($bulkAuto)): ?>run();<?php endif ?>
})();
</script><?php endif ?>
<?php endif ?>
