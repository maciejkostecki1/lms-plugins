<?php
define('CONTACT_DISABLED',1);define('CONTACT_NOTIFICATIONS',2);
require_once __DIR__.'/../lib/NotifyCustomerDetails.php';
foreach([
    [[],true,'Brak e-maila'],
    [[['contact'=>'invalid','type'=>0]],true,'Nieprawidłowy adres e-mail'],
    [[['contact'=>'test@example.invalid','type'=>1]],true,'E-mail wyłączony'],
    [[['contact'=>'test@example.invalid','type'=>2]],false,'Brak zgody na powiadomienia e-mail'],
    [[['contact'=>'test@example.invalid','type'=>0]],true,'E-mail nieoznaczony do powiadomień'],
    [[['contact'=>'test@example.invalid','type'=>2]],true,''],
] as [$contacts,$consent,$expected])if(NotifyCustomerDetails::emailStatus($contacts,$consent)!==$expected)throw new RuntimeException('Email status mismatch');
if(NotifyCustomerDetails::noteText('<p>Wpłata 25.10</p>Telefon<br>potwierdzony')!=="Wpłata 25.10\nTelefon\npotwierdzony")throw new RuntimeException('Note lines lost');
echo "PASS email status states and note line breaks.\n";
foreach([
    [['action'=>'notify_mail','result'=>'sent','details'=>'Wiadomości LMS: 1'],'Wysłano powiadomienie'],
    [['action'=>'notify_mail','result'=>'sent','details'=>'Załącznik: historia-platnosci-1.html; SHA256: test'],'Wysłano mail z historią'],
    [['action'=>'invoice_notify-overdue_1','result'=>'sent'],'Wysłano powiadomienie o fakturze'],
    [['result'=>'pending','details'=>'Załącznik: historia-platnosci-1.html; wynik nieznany'],'Wysyłka z historią — wynik do sprawdzenia'],
    [['result'=>'error'],'Błąd wysyłki powiadomienia'],
    [[], ''],
] as [$event,$expected])if(NotifyCustomerDetails::notificationStatus($event)!==$expected)throw new RuntimeException('Notification status mismatch');
echo "PASS ordinary/history/invoice send labels and no false sent label for pending or error.\n";

foreach([
    [['DELIVERED','EXPIRED'],'Doręczono SMS'],
    [['SENT','QUEUE'],'SMS wysłany lub w kolejce — oczekiwanie na doręczenie'],
    [['EXPIRED','UNDELIVERED'],'Nie doręczono powiadomienia SMS'],
    [[], 'Wysyłka SMS — brak potwierdzenia doręczenia'],
] as [$states,$expected])if(NotifyCustomerDetails::notificationStatus(['action'=>'notify_sms','result'=>'sent','sms_states'=>$states])!==$expected)throw new RuntimeException('SMS notification status mismatch');
echo "PASS SMS delivery information independent of countdown.\n";
