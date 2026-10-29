<?php
/** Standalone appearance plugin. No database migrations or core overrides. */
class LMSThemeSwitcherPlugin extends LMSPlugin
{
    const PLUGIN_DIRECTORY_NAME = 'LMSThemeSwitcherPlugin';
    const PLUGIN_NAME = 'Motywy LMS';
    const PLUGIN_DESCRIPTION = 'Dwanaście motywów LMS, własne kolory w trybie ręcznym i tryb kompaktowy';
    const PLUGIN_SOFTWARE_VERSION = '2.0.2';
    const PLUGIN_AUTHOR = 'NET-24h';

    public function registerHandlers()
    {
        require_once __DIR__ . '/handlers/ThemeSwitcherInitHandler.php';
        $this->handlers = array(
            'smarty_initialized' => array(
                'class' => 'ThemeSwitcherInitHandler', 'method' => 'smartyInit',
            ),
            'modules_dir_initialized' => array('class' => 'ThemeSwitcherInitHandler', 'method' => 'modulesInit'),
            'menu_initialized' => array('class' => 'ThemeSwitcherInitHandler', 'method' => 'menuInit'),
            'access_table_initialized' => array('class' => 'ThemeSwitcherInitHandler', 'method' => 'accessInit'),
        );
    }
}
