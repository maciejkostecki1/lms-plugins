<?php

class NET24KsefErstePlugin extends LMSPlugin
{
    const PLUGIN_DIRECTORY_NAME = 'NET24KsefErstePlugin';
    const PLUGIN_NAME = 'NET-24h KSeF ERSTE';
    const PLUGIN_DESCRIPTION = 'Eksport przelewow KSeF do ERSTE';
    const PLUGIN_AUTHOR = 'NET-24h';

    public function registerHandlers()
    {
        $this->handlers = array(
            'smarty_initialized' => array(
                'class' => 'KsefErsteInitHandler',
                'method' => 'smartyInit',
            ),
            'modules_dir_initialized' => array(
                'class' => 'KsefErsteInitHandler',
                'method' => 'modulesDirInit',
            ),
            'menu_initialized' => array(
                'class' => 'KsefErsteInitHandler',
                'method' => 'menuInit',
            ),
            'access_table_initialized' => array(
                'class' => 'KsefErsteInitHandler',
                'method' => 'accessTableInit',
            ),
        );
    }
}
