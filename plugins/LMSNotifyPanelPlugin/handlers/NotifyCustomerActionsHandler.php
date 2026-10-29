<?php
require_once __DIR__.'/../lib/NotifyPanel.php';
class NotifyCustomerActionsHandler
{
    public static function inject(string $html,string $panel): string
    {
        if(strpos($html,'id="lnp-customer-notifications"')!==false)return $html;
        return preg_replace_callback('~(<div\b[^>]*id="customerinfo-sortable"[^>]*>)~',static fn($m)=>$m[1].$panel,$html,1);
    }
    public function customer(array $data): array
    {
        if(!ConfigHelper::checkPrivilege('full_access') || (int)Auth::GetCurrentUser()<1 || isset($_POST['xjxfun']))return $data;
        $customer=(int)($data['customerinfo']['id']??0);if($customer<1)return $data;
        global $DB,$LMS;
        try{
            $settings=(new NotifyPanel($DB,$LMS,(int)Auth::GetCurrentUser()))->settings();
            $sms=(new NotifySmsSettings($DB))->settings();
            if(session_status()===PHP_SESSION_NONE)session_start();
            if(session_status()!==PHP_SESSION_ACTIVE)return $data;
            if(empty($_SESSION['lnp_csrf']))$_SESSION['lnp_csrf']=bin2hex(random_bytes(32));
            $csrf=$_SESSION['lnp_csrf'];
            $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            ob_start();require __DIR__.'/../views/customer-actions.php';$panel=ob_get_clean();
            $data['smarty']->registerFilter('output',static fn($html)=>self::inject($html,$panel),'lnp-customer-notifications');
        }catch(Throwable $e){error_log('[NotifyCustomerActions] panel unavailable');}
        return $data;
    }
}
