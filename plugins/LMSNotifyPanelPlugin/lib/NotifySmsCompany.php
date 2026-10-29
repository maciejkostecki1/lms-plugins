<?php
declare(strict_types=1);
final class NotifySmsCompany
{
    public static function defaultTemplate(): string
    {
        return 'Zaleglosc: %debt zl. %payment_request Brak wplaty moze skutkowac ograniczeniem uslug. %company';
    }
    public static function validateTemplate(string $template): void
    {
        if(trim($template)==='' || strlen($template)>2000 || !preg_match('//u',$template))throw new InvalidArgumentException('Nieprawidlowa tresc SMS.');
        foreach(['%debt','%company'] as $token)if(strpos($template,$token)===false)throw new InvalidArgumentException('SMS musi zawierac %debt i %company.');
        if(preg_match('/%[a-zA-Z_]/',str_replace(['%payment_request','%company','%debt','%days'],'',$template)))throw new InvalidArgumentException('Nieznany symbol w tresci SMS.');
    }
    public static function measure(string $text): array
    {
        $chars=preg_split('//u',$text,-1,PREG_SPLIT_NO_EMPTY);
        if($chars===false)throw new InvalidArgumentException("Nieprawidłowe kodowanie SMS.");
        $units=0;$gsm=true;
        foreach($chars as $char){
            if(strpos('^{}\\[~]|€',$char)!==false || $char==="\f"){$units+=2;continue;}
            if((strlen($char)===1 && ord($char)>=32 && ord($char)<=126 && $char!=='`') || $char==="\r" || $char==="\n" || strpos('£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ¤¡ÄÖÑÜ§¿äöñüà',$char)!==false){++$units;continue;}
            $gsm=false;
        }
        if(!$gsm)$units=(int)(strlen(iconv('UTF-8','UTF-16BE',$text))/2);
        return ['encoding'=>$gsm?'GSM-7':'Unicode','units'=>$units,'segments'=>$units===0?0:($units<=($gsm?160:70)?1:(int)ceil($units/($gsm?153:67)))];
    }
    public static function fitsOneSms(string $text): bool
    {
        try{return self::measure($text)['segments']<=1;}catch(InvalidArgumentException $e){return false;}
    }
    public static function message(int $division,int $days,float $debt,?string $template=null): string
    {
        $signatures=[1=>'Net-24h Maciej Kostecki',2=>'Meganecik'];
        if(!isset($signatures[$division]))throw new RuntimeException('Brak podpisu SMS dla podmiotu klienta.');
        if(!is_finite($debt) || $debt<=0)throw new RuntimeException('Brak poprawnej kwoty zadluzenia SMS.');
        $amount=number_format($debt,2,',','');
        $template=$template??self::defaultTemplate();self::validateTemplate($template);
        $request=$days>0?'Prosimy o wplate w ciagu '.$days.' dni od tej wiadomosci.':'Prosimy o wplate.';
        $message=strtr($template,['%debt'=>$amount,'%company'=>$signatures[$division],'%days'=>(string)$days,'%payment_request'=>$request]);
        if(!self::fitsOneSms($message))throw new RuntimeException('Tresc z kwota i podpisem przekracza jeden SMS.');
        return $message;
    }
}
