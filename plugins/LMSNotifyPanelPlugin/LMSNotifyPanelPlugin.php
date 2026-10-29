<?php
class LMSNotifyPanelPlugin extends LMSPlugin
{
    const PLUGIN_DIRECTORY_NAME = 'LMSNotifyPanelPlugin';
    const PLUGIN_NAME = 'Płatności i blokady';
    const PLUGIN_DESCRIPTION = 'Procentowe progi salda: powiadomienia i blokady klientów';
    const PLUGIN_SOFTWARE_VERSION = '0.8.6';
    public function registerHandlers()
    {
        require_once __DIR__ . '/handlers/NotifyPanelInitHandler.php';
        require_once __DIR__ . '/handlers/NotifySmsApiHandler.php';
        require_once __DIR__ . '/handlers/NotifyCustomerActionsHandler.php';
        $this->handlers = array(
            'customerinfo_before_display' => array('class'=>'NotifyCustomerActionsHandler','method'=>'customer'),
            'send_sms_before' => array('class' => 'NotifySmsApiHandler', 'method' => 'send'),
            'smarty_initialized' => array('class' => 'NotifyPanelInitHandler', 'method' => 'smartyInit'),
            'modules_dir_initialized' => array('class' => 'NotifyPanelInitHandler', 'method' => 'modulesInit'),
            'menu_initialized' => array('class' => 'NotifyPanelInitHandler', 'method' => 'menuInit'),
            'access_table_initialized' => array('class' => 'NotifyPanelInitHandler', 'method' => 'accessInit'),
        );
    }
}
