<?php
declare(strict_types=1);
final class NotifyCustomerDetails
{
    public static function emailStatus(array $contacts,bool $consent): string
    {
        $contacts=array_values(array_filter($contacts,static fn($c)=>trim((string)$c['contact'])!==''));
        if(!$contacts)return 'Brak e-maila';
        $valid=array_values(array_filter($contacts,static fn($c)=>filter_var(trim((string)$c['contact']),FILTER_VALIDATE_EMAIL)!==false));
        if(!$valid)return 'Nieprawidłowy adres e-mail';
        $active=array_values(array_filter($valid,static fn($c)=>!((int)$c['type'] & CONTACT_DISABLED)));
        if(!$active)return 'E-mail wyłączony';
        if(!$consent)return 'Brak zgody na powiadomienia e-mail';
        foreach($active as $c)if((int)$c['type'] & CONTACT_NOTIFICATIONS)return '';
        return 'E-mail nieoznaczony do powiadomień';
    }
    public static function noteText(?string $text): string
    {
        $text=preg_replace('/<br\s*\/?\s*>|<\/(?:p|div|li)>/i',"\n",$text??'');
        return trim(html_entity_decode(strip_tags($text),ENT_QUOTES|ENT_HTML5,'UTF-8'));
    }
    public static function notificationStatus(array $event): string
    {
        if(($event['action']??'')==='notify_sms'){
            $states=$event['sms_states']??[];
            if(in_array('DELIVERED',$states,true))return 'Doręczono powiadomienie SMS — co najmniej jeden numer';
            if(array_intersect($states,['QUEUE','ACCEPTED','SENT','RENEWAL']))return 'SMS wysłany lub w kolejce — oczekiwanie na doręczenie';
            if($states)return 'Nie doręczono powiadomienia SMS';
            return 'Wysyłka SMS — brak potwierdzenia doręczenia';
        }
        $history=strpos((string)($event['details']??''),'Załącznik: historia-platnosci-')===0;
        if(($event['result']??'')==='pending')return $history?'Wysyłka z historią — wynik do sprawdzenia':'Wysyłka powiadomienia — wynik do sprawdzenia';
        if(($event['result']??'')==='error')return $history?'Błąd wysyłki z historią':'Błąd wysyłki powiadomienia';
        if(($event['result']??'')!=='sent')return '';
        if($history)return 'Wysłano powiadomienie z historią';
        if(strpos((string)($event['action']??''),'invoice_')===0)return 'Wysłano powiadomienie o fakturze';
        return 'Wysłano powiadomienie';
    }
}
