<?php
class LMSGponHuaweiDemoPlugin extends LMSPlugin
{
    const PLUGIN_DIRECTORY_NAME = 'LMSGponHuaweiDemoPlugin';
    const PLUGIN_NAME = 'Huawei GPON — DEMO RO';
    const PLUGIN_DESCRIPTION = 'Demo RO: lista ONT, VLAN OLT i alerty OLT';
    const PLUGIN_SOFTWARE_VERSION = '1.1.0-demo-ro-lite';

    public function registerHandlers()
    {
        $this->handlers = array(
            'smarty_initialized' => array('class' => 'HuaweiGponDemoInitHandler', 'method' => 'smartyInit'),
            'modules_dir_initialized' => array('class' => 'HuaweiGponDemoInitHandler', 'method' => 'modulesDirInit'),
            'menu_initialized' => array('class' => 'HuaweiGponDemoInitHandler', 'method' => 'menuInit'),
            'access_table_initialized' => array('class' => 'HuaweiGponDemoInitHandler', 'method' => 'accessTableInit'),
        );
    }
}
