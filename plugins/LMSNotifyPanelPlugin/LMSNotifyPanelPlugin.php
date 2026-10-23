<?php
class LMSNotifyPanelPlugin extends LMSPlugin
{
    const PLUGIN_DIRECTORY_NAME = 'LMSNotifyPanelPlugin';
    const PLUGIN_NAME = 'Płatności i blokady';
    const PLUGIN_DESCRIPTION = 'Procentowe progi salda: powiadomienia i blokady klientów';
    const PLUGIN_SOFTWARE_VERSION = '0.7.4';
    public function registerHandlers()
    {
        require_once __DIR__ . '/handlers/NotifyPanelInitHandler.php';
        $this->handlers = array(
            'smarty_initialized' => array('class' => 'NotifyPanelInitHandler', 'method' => 'smartyInit'),
            'modules_dir_initialized' => array('class' => 'NotifyPanelInitHandler', 'method' => 'modulesInit'),
            'menu_initialized' => array('class' => 'NotifyPanelInitHandler', 'method' => 'menuInit'),
            'access_table_initialized' => array('class' => 'NotifyPanelInitHandler', 'method' => 'accessInit'),
        );
    }
}
