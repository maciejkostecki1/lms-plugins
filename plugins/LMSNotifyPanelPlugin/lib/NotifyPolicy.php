<?php
declare(strict_types=1);
require_once __DIR__.'/NotifySmsCompany.php';
final class NotifyPolicy
{
    public static function defaults(): array
    {
        return ['notify_percent'=>200, 'block_percent'=>300, 'suspend_percent'=>400, 'auto_notify'=>false,
            'auto_block'=>false, 'auto_unblock'=>false, 'channels'=>['mail'],
            'repeat_days'=>7, 'block_after_days'=>0, 'sender_email'=>'', 'sender_name'=>'Obsługa klienta',
            'subject'=>'Przypomnienie o płatności','sms_body'=>NotifySmsCompany::defaultTemplate(),
            'body'=>'Szanowny Kliencie, saldo wynosi %balance zł. Prosimy o uregulowanie zadłużenia %debt zł. Numer klienta: %cid.'];
    }
    public static function validate(array $input): array
    {
        $s = array_replace(self::defaults(), $input);
        if(!array_key_exists('suspend_percent',$input))$s['suspend_percent']=max(400,(int)$s['block_percent']);
        foreach (['notify_percent','block_percent','suspend_percent','repeat_days','block_after_days'] as $key) {
            if (filter_var($s[$key], FILTER_VALIDATE_INT) === false) throw new InvalidArgumentException('Nieprawidłowy próg lub odstęp wysyłki.');
            $s[$key] = (int)$s[$key];
        }
        if ($s['notify_percent'] < 1 || $s['block_percent'] <= $s['notify_percent']
            || $s['block_percent'] > 10000 || ($s['suspend_percent']<1 || ((int)$s['block_after_days']===0 && $s['suspend_percent']<$s['block_percent'])) || $s['suspend_percent']>10000 || $s['repeat_days'] < 1 || $s['repeat_days'] > 365) {
            throw new InvalidArgumentException('Próg blokady musi być wyższy od powiadomienia, a próg zawieszenia naliczania musi wynosić co najmniej 1% (w trybie procentowym nie mniej niż próg blokady) (maks. 10000%). Odstęp od 1 do 365 dni.');
        }
        if(($s['block_after_days']!==0 && $s['block_after_days']<7) || $s['block_after_days']>365)throw new InvalidArgumentException('Ustaw 0, aby wyłączyć odliczanie, albo od 7 do 365 dni.');
        foreach (['auto_notify','auto_block','auto_unblock'] as $key) $s[$key] = !empty($s[$key]);
        if (!is_array($s['channels']) || !$s['channels'] || array_diff($s['channels'], ['mail','sms'])) {
            throw new InvalidArgumentException('Wybierz e-mail lub SMS.');
        }
        $s['channels'] = array_values(array_unique($s['channels']));
        foreach (['sender_email','sender_name','subject','body'] as $key) {
            if (!is_string($s[$key]) || strlen($s[$key]) > ($key === 'body' ? 8000 : 250)
                || ($key !== 'body' && preg_match('/[\r\n]/', $s[$key]))) {
                throw new InvalidArgumentException('Nieprawidłowa treść lub nagłówek wiadomości.');
            }
        }
        if ($s['sender_email'] !== '' && !filter_var($s['sender_email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Nieprawidłowy adres nadawcy.');
        }
        if (trim($s['body']) === '' || trim($s['subject']) === '') throw new InvalidArgumentException('Uzupełnij temat i treść.');
        if(!is_string($s['sms_body']))throw new InvalidArgumentException('Nieprawidlowa tresc SMS.');
        NotifySmsCompany::validateTemplate($s['sms_body']);
        foreach([1,2] as $division)NotifySmsCompany::message($division,$s['block_after_days'],9999.99,$s['sms_body']);
        return array_intersect_key($s, self::defaults());
    }
    public static function decision(float $balance, array $monthly, array $s): array
    {
        // LMS balances are expressed in PLN; never compare them to foreign currency fees.
        $base = 0.0;
        foreach ($monthly as $row) {
            if (strtoupper((string)$row['currency']) !== 'PLN' || !is_numeric($row['sum'])) {
                return ['state'=>'review','reason'=>'Opłaty w innej walucie lub nieprawidłowa podstawa.','base'=>0,'debt'=>max(0,-$balance),'percent'=>null];
            }
            $base += (float)$row['sum'];
        }
        $cents = (int)round($base * 100);
        $debt = max(0, (int)round(-$balance * 100));
        if ($cents <= 0) return ['state'=>'review','reason'=>'Brak dodatnich miesięcznych opłat.','base'=>$base,'debt'=>$debt/100,'percent'=>null];
        $state = $debt * 100 >= $cents * $s['block_percent'] ? 'block'
            : ($debt * 100 >= $cents * $s['notify_percent'] ? 'notify' : 'ok');
        return ['state'=>$state,'reason'=>'','base'=>$cents/100,'debt'=>$debt/100,'percent'=>round(100*$debt/$cents,2)];
    }
    public static function thresholdDue(array $c,int $percent): bool
    {
        $base=(int)round((float)($c['base']??0)*100);
        $debt=(int)round((float)($c['overdue_debt']??$c['debt']??0)*100);
        return $base>0 && $debt>0 && $debt*100 >= $base*$percent;
    }
    public static function notificationDue(array $c,array $s): bool
    {
        return self::thresholdDue($c,(int)$s['notify_percent']);
    }
    public static function internetBlockDue(array $c,array $s,array $notice): bool
    {
        if((float)$c['debt']<=0.009)return false;
        return (int)$s['block_after_days']>0 ? !empty($notice['ready']) : $c['state']==='block';
    }
    public static function suspendDue(array $c,array $s): bool
    {
        $base=(int)round((float)$c['base']*100);$debt=(int)round((float)($c['overdue_debt']??$c['debt'])*100);
        return $base>0 && $debt*100 >= $base*(int)$s['suspend_percent'];
    }
    public static function render(string $text, array $c): string
    {
        self::manualTokens($text);
        if(strpos($text,'%bankaccount')!==false && empty($c['bankaccount']))throw new RuntimeException('Brak numeru rachunku w podglądzie wiadomości.');
        return strtr($text, ['%cid'=>(string)$c['id'], '%customername'=>$c['customername'],
            '%name'=>$c['customername'],'%bankaccount'=>$c['bankaccount']??'',
            '%balance'=>number_format($c['balance'],2,',',' '), '%debt'=>number_format($c['debt'],2,',',' '),
            '%monthly'=>number_format($c['base'],2,',',' '), '%percent'=>(string)$c['percent']]);
    }
    public static function manualTokens(string $text): void
    {
        $remaining=str_replace(['%cid','%customername','%name','%bankaccount','%balance','%debt','%monthly','%percent'],'',$text);
        if(preg_match('/%[a-zA-Z_]/',$remaining))throw new RuntimeException('Nieznany symbol szablonu ręcznego. Dług abonamentowy: %debt; saldo konta: %balance. Symbol %unpaid jest dostępny w powiadomieniach o fakturach.');
    }
}
