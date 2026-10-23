<?php

class KsefErsteInitHandler
{
    public function smartyInit(Smarty $hook_data)
    {
        $dirs = $hook_data->getTemplateDir();

        array_unshift($dirs, PLUGINS_DIR
            . DIRECTORY_SEPARATOR
            . NET24KsefErstePlugin::PLUGIN_DIRECTORY_NAME
            . DIRECTORY_SEPARATOR . 'templates');

        $hook_data->setTemplateDir($dirs);

        return $hook_data;
    }

    public function modulesDirInit(array $hook_data = array())
    {
        array_unshift($hook_data, PLUGINS_DIR
            . DIRECTORY_SEPARATOR
            . NET24KsefErstePlugin::PLUGIN_DIRECTORY_NAME
            . DIRECTORY_SEPARATOR . 'modules');

        return $hook_data;
    }

    public function menuInit(array $hook_data = array())
    {
        $hook_data['finances']['submenu']['ksefpaymentexport'] = array(
            'name' => 'Przelewy KSeF → ERSTE',
            'link' => '?m=ksefpaymentexport',
            'tip' => 'Koszyk przelewów za faktury zakupowe KSeF',
            'prio' => 165,
        );

        return $hook_data;
    }

    public function accessTableInit()
    {
        $access = AccessRights::getInstance();

        $access->insertPermission(
            new Permission(
                'ksef_payment_export',
                'Eksport przelewów KSeF do ERSTE',
                '^ksefpaymentexport$',
                null,
                array(
                    'finances' => array('ksefpaymentexport'),
                )
            ),
            AccessRights::FIRST_FORBIDDEN_PERMISSION
        );
    }
}
