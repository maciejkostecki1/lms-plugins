<?php
declare(strict_types=1);
require_once __DIR__.'/NotifySmsRelayProtocol.php';
final class NotifySmsRelayClient
{
    private string $url;private string $secret;private $transport;
    public function __construct(array $config,?callable $transport=null)
    {
        if(!isset($config['url'],$config['secret']) || !preg_match('~^https://[a-zA-Z0-9.-]+/[a-zA-Z0-9/_\-.]+$~D',$config['url']) || !preg_match('/^[a-f0-9]{64}$/D',$config['secret']))throw new RuntimeException('Nieprawidłowa konfiguracja odbiornika raportów.');
        $this->url=$config['url'];$this->secret=$config['secret'];$this->transport=$transport;
    }
    public function request(string $op,array $keys=[]): array
    {
        $body=json_encode($op==='pull'?['op'=>$op]:['op'=>$op,'keys'=>$keys],JSON_THROW_ON_ERROR);
        $timestamp=(string)time();$nonce=bin2hex(random_bytes(16));
        $headers=['Content-Type: application/json','X-LNP-Time: '.$timestamp,'X-LNP-Nonce: '.$nonce,
            'X-LNP-Signature: '.NotifySmsRelayProtocol::signature($op,$timestamp,$nonce,$body,$this->secret)];
        try{
            if($this->transport)$reply=($this->transport)($body,$headers,$nonce);
            else{
                $signature='';$c=curl_init($this->url);
                curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,
                    CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                    CURLOPT_HEADERFUNCTION=>static function($c,$line)use(&$signature){if(stripos($line,'X-LNP-Signature:')===0)$signature=trim(substr($line,16));return strlen($line);}]);
                $raw=curl_exec($c);$http=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
                $reply=['http'=>$http,'body'=>$raw,'signature'=>$signature];
            }
            if($reply['http']!==200 || !is_string($reply['body']) || strlen($reply['body'])>65536 || !is_string($reply['signature']) || !hash_equals(hash_hmac('sha256',$nonce."\n".$reply['body'],$this->secret),$reply['signature']))throw new RuntimeException('Invalid response');
            return json_decode($reply['body'],true,512,JSON_THROW_ON_ERROR);
        }catch(Throwable $e){throw new RuntimeException('Nie udało się bezpiecznie pobrać raportów. Bez automatycznego ponawiania SMS.');}
    }
}
