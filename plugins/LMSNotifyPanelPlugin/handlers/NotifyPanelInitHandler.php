<?php
class NotifyPanelInitHandler
{
    public function smartyInit(Smarty $smarty)
    {
        $dirs = $smarty->getTemplateDir();
        array_unshift($dirs, PLUGINS_DIR . '/LMSNotifyPanelPlugin/templates');
        $smarty->setTemplateDir($dirs);
        return $smarty;
    }
    public function modulesInit(array $dirs = array())
    {
        array_unshift($dirs, PLUGINS_DIR . '/LMSNotifyPanelPlugin/modules');
        return $dirs;
    }
    public function menuInit(array $menu = array())
    {
        // The module also enforces full_access independently of menu visibility.
        $menu['finances']['submenu']['notifypanel'] = array('name'=>'Płatności i blokady',
            'link'=>'?m=notifypanel','prio'=>45,
            'tip'=>'Powiadomienia od 200%, blokady od 300% miesięcznych opłat');
        return $menu;
    }
    public function accessInit()
    {
        AccessRights::getInstance()->insertPermission(new Permission(
            'notify_panel_access', 'Płatności i blokady — panel', '^notifypanel$',null,
            array('finances'=>array('notifypanel'))
        ), AccessRights::FIRST_FORBIDDEN_PERMISSION);
    }
}
