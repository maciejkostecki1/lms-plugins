<?php
declare(strict_types=1);

final class NotifySmsApi
{
    public static function send(string $number, string $message, string $messageId, array $options, ?callable $transport=null): array
    {
        $fail=static fn(string $error): array=>['status'=>MSG_ERROR,'errors'=>[$error]];
        if (empty($options['enabled'])) return $fail('SMSAPI: integracja jest wyłączona.');
        $token=trim((string)($options['token']??''));
        if ($token==='' || preg_match('/[\r\n]/',$token)) return $fail('SMSAPI: brak poprawnego tokenu API.');
        $number=preg_replace('/[^0-9]/','',$number);
        if (!preg_match('/^48[0-9]{9}$/D',$number)) return $fail('SMSAPI: wymagany polski numer z prefiksem 48.');
        if (trim($message)==='') return $fail('SMSAPI: pusta wiadomość.');
        $params=['to'=>$number,'message'=>$message,'format'=>'json','encoding'=>'utf-8',
            'idx'=>hash('sha256','lms-smsapi-'.$messageId.'-'.$number),'check_idx'=>1,'details'=>1,'max_parts'=>1];
        if (!empty($options['dry_run'])) $params['test']=1;
        if (!empty($options['sender'])) $params['from']=(string)$options['sender'];
        try {
            if ($transport) $response=$transport($params,$token);
            else {
                if (!extension_loaded('curl')) return $fail('SMSAPI: brak rozszerzenia cURL.');
                $curl=curl_init('https://api.smsapi.pl/sms.do');
                curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($params),
                    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token],CURLOPT_RETURNTRANSFER=>true,
                    CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,
                    CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
                $raw=curl_exec($curl);$http=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
                if ($raw===false) return $fail('SMSAPI: nieznany wynik połączenia; sprawdź historię przed ponowieniem.');
                $response=['http'=>$http,'body'=>$raw];
            }
            $data=json_decode((string)$response['body'],true,512,JSON_THROW_ON_ERROR);
            if ((int)$response['http']!==200 || !empty($data['error'])) {
                $code=isset($data['error'])?(int)$data['error']:(int)$response['http'];
                return $fail('SMSAPI: błąd '.$code.'. Sprawdź panel operatora przed ponowieniem.');
            }
            $items=$data['list']??[];
            if (count($items)!==1 || empty($items[0]['id']) || empty($items[0]['status']))
                return $fail('SMSAPI: niepełna odpowiedź; sprawdź historię przed ponowieniem.');
            $item=$items[0];$state=strtoupper((string)$item['status']);
            if (!empty($options['dry_run'])) return ['status'=>MSG_NEW,'id'=>(string)$item['id'],'dry_run'=>true,'errors'=>[]];
            // Acceptance by the gateway is not delivery and must not start a blocking countdown.
            if (!in_array($state,['QUEUE','ACCEPTED','SENT','DELIVERED'],true)) return $fail('SMSAPI: status '.$state.'.');
            return ['status'=>$state==='DELIVERED'?MSG_DELIVERED:($state==='SENT'?MSG_SENT:MSG_NEW),
                'id'=>(string)$item['id'],'gateway_status'=>$state,'errors'=>[]];
        } catch (Throwable $ex) {
            // Never expose tokens, raw API replies, message contents or transport exceptions.
            return $fail('SMSAPI: nieznany wynik; sprawdź historię operatora przed ponowieniem.');
        }
    }
}
