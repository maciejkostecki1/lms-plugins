<?php
declare(strict_types=1);
final class NotifySmsRelayProtocol
{
    public const SOURCES=['89.174.81.98','91.185.187.219','213.189.53.211','31.186.83.18','212.91.26.253'];
    public const STATES=[401=>'NOT_FOUND',402=>'EXPIRED',403=>'SENT',404=>'DELIVERED',405=>'UNDELIVERED',406=>'FAILED',407=>'REJECTED',408=>'UNKNOWN',409=>'QUEUE',410=>'ACCEPTED',411=>'RENEWAL',412=>'STOP'];
    public static function record(array $r): array
    {
        foreach(['id','idx','number','state'] as $key)if(!isset($r[$key]) || !is_string($r[$key]))throw new InvalidArgumentException('Invalid report');
        if(!preg_match('/^[a-zA-Z0-9]{1,32}$/D',$r['id']) || !preg_match('/^[a-f0-9]{64}$/D',$r['idx']) || !preg_match('/^48[0-9]{9}$/D',$r['number']) || !in_array($r['state'],self::STATES,true) || !isset($r['date']) || !is_int($r['date']) || $r['date']<0 || $r['date']>time()+300)throw new InvalidArgumentException('Invalid report');
        return ['id'=>$r['id'],'idx'=>$r['idx'],'number'=>$r['number'],'state'=>$r['state'],'date'=>$r['date']];
    }
    public static function callback(array $input): array
    {
        $data=[];$count=null;
        foreach(['MsgId','status','idx','to','donedate'] as $key){
            if(!isset($input[$key]) || !is_string($input[$key]))throw new InvalidArgumentException('Invalid callback');
            $data[$key]=explode(',',$input[$key]);$count=$count??count($data[$key]);
            if(count($data[$key])!==$count || $count<1 || $count>5)throw new InvalidArgumentException('Invalid batch');
        }
        $out=[];
        for($i=0;$i<$count;++$i){
            $code=$data['status'][$i];$date=$data['donedate'][$i];
            if(!isset(self::STATES[$code]) || !ctype_digit($date))throw new InvalidArgumentException('Invalid status');
            $number=$data['to'][$i];if(!preg_match('/^\+?[0-9 ()-]+$/D',$number))throw new InvalidArgumentException('Invalid number');
            $number=preg_replace('/[ ()+\-]/','',$number);if(strlen($number)===9)$number='48'.$number;
            $out[]=self::record(['id'=>$data['MsgId'][$i],'idx'=>$data['idx'][$i],'number'=>$number,'state'=>self::STATES[$code],'date'=>(int)$date]);
        }
        return $out;
    }
    public static function signature(string $operation,string $timestamp,string $nonce,string $body,string $secret): string
    {
        return hash_hmac('sha256',$operation."\n".$timestamp."\n".$nonce."\n".$body,$secret);
    }
}
