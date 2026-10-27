<?php
require_once __DIR__.'/../lib/NotifySmsSettings.php';
class NotifySmsApiHandler
{
    public function send(array $data)
    {
        if(($data['service']??'')!=='smsapi')return $data;
        global $DB;
        $data['abort']=true;
        $data['result']=(new NotifySmsSettings($DB))->send((string)$data['number'],(string)$data['message'],(string)$data['messageid'],$data['sms_options']??[]);
        return $data;
    }
}
