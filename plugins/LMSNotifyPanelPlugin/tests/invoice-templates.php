<?php
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../lib/NotifyInvoiceSettings.php';
$p=['subject'=>'Faktura %invoice','message'=>"Dzień dobry %name\nTermin płatności: %deadline-y\nKwota: %value\nFaktura: %invoice\ninformujemy, że minął termin płatności za fakturę %invoice.\nProsimy o płatność, dziękujemy za współpracę.\n%commented_balance\n%bankaccount\nZa 7 dni usługa zostanie skierowana do zablokowania."];
foreach(['notify-overdue','notify-overdue-7'] as $section){
    $a=NotifyInvoiceSettings::adapt($section,$p);
    if(strpos($a['message'],'%invoice')===false || strpos($a['message'],'%unpaid')===false || strpos($a['message'],'%commented_balance')!==false || strpos($a['message'],'Za 7 dni')!==false)throw new RuntimeException('Current invoice template invalid');
    if(strpos($a['message'],'%bankaccount')===false)throw new RuntimeException('Account symbol lost');
    if(!preg_match('//u',$a['message']))throw new RuntimeException('Polish UTF-8 text damaged');
    echo 'PASS current invoice template '.$section.PHP_EOL;
}
if(NotifyInvoiceSettings::adapt('notify-reminder',$p)!==$p)throw new RuntimeException('Invoice reminder unexpectedly changed');
echo "PASS original per-invoice reminder preserved\n";
