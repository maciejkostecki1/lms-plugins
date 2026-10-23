<?php
class HuaweiGponDemoInitHandler
{
    public function smartyInit(Smarty $smarty)
    {
        $dirs = $smarty->getTemplateDir();
        array_unshift($dirs, PLUGINS_DIR . '/LMSGponHuaweiDemoPlugin/templates');
        $smarty->setTemplateDir($dirs);
        return $smarty;
    }

    public function modulesDirInit(array $dirs = array())
    {
        array_unshift($dirs, PLUGINS_DIR . '/LMSGponHuaweiDemoPlugin/modules');
        return $dirs;
    }

    public function menuInit(array $menu = array())
    {
        $menu['huawei-gpon-demo'] = array(
            'name' => 'Huawei GPON — DEMO RO',
            'css' => 'lms-ui-icon-fiberoptic',
            'link' => '?m=huaweigpondemolist',
            'tip' => 'Huawei GPON DEMO RO — odczyt',
            'prio' => 48,
            'submenu' => array(
                'huaweigpondemolist' => array(
                    'name' => 'Lista ONT',
                    'link' => '?m=huaweigpondemolist',
                    'tip' => 'Lista ONT',
                    'prio' => 10,
                ),
                'huaweigpondemovlans' => array(
                    'name' => 'VLAN OLT',
                    'link' => '?m=huaweigpondemovlans',
                    'tip' => 'VLAN OLT',
                    'prio' => 50,
                ),
                'huaweigpondemoalerts' => array(
                    'name' => 'Alerty OLT',
                    'link' => '?m=huaweigpondemoalerts',
                    'tip' => 'Alerty i zdarzenia OLT',
                    'prio' => 55,
                ),
                'huaweigpondemosettings' => array(
                    'name' => 'Konfiguracja wtyczki',
                    'link' => '?m=huaweigpondemosettings',
                    'tip' => 'Konfiguracja wtyczki',
                    'prio' => 60,
                ),
            ),
        );
        return $menu;
    }

    public function accessTableInit()
    {
        $access = AccessRights::getInstance();
        if (DBVERSION >= '2020060900') {
            $permission = new Permission('huaweigpondemo_access', 'Huawei GPON DEMO RO — odczyt',
                '^huaweigpondemo(list|alerts)$', null,
                array('huawei-gpon-demo' => Permission::MENU_ALL));
        } else {
            $permission = new Permission('huaweigpondemo_access', 'Huawei GPON DEMO RO — odczyt',
                '^huaweigpondemo(list|alerts)$');
        }
        $access->insertPermission($permission, AccessRights::FIRST_FORBIDDEN_PERMISSION);
        $settingsPermission = new Permission(
            'huaweigpondemo_settings_access',
            'Huawei GPON DEMO RO — podgląd konfiguracji',
            '^huaweigpondemosettings$'
        );
        $access->insertPermission(
            $settingsPermission,
            AccessRights::FIRST_FORBIDDEN_PERMISSION
        );

        $vlanPermission = new Permission(
            'huaweigpondemo_vlan_access',
            'Huawei GPON DEMO RO — podgląd VLAN',
            '^huaweigpondemovlans$'
        );

        $access->insertPermission(
            $vlanPermission,
            AccessRights::FIRST_FORBIDDEN_PERMISSION
        );


    }
}
