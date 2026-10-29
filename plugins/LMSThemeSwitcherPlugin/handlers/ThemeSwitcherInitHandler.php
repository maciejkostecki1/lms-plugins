<?php
class ThemeSwitcherInitHandler
{
    public function modulesInit(array $dirs = array())
    {
        array_unshift($dirs, dirname(__DIR__) . '/modules');
        return $dirs;
    }

    public function menuInit(array $menu = array())
    {
        $menu['config']['submenu']['lmstheme'] = array(
            'name' => 'Motywy LMS', 'link' => '?m=lmstheme', 'prio' => 18,
            'tip' => 'Motyw, czcionka i wielkość tekstu dla Twojego konta',
        );
        return $menu;
    }

    public function accessInit()
    {
        AccessRights::getInstance()->insertPermission(new Permission(
            'lms_theme_access', 'Motywy LMS — własne ustawienia wyglądu', '^lmstheme$', null,
            array('config' => array('lmstheme'))
        ), AccessRights::FIRST_FORBIDDEN_PERMISSION);
    }

    public function smartyInit(Smarty $smarty)
    {
        // An output filter extends rendered HTML; original templates stay intact.
        $smarty->registerFilter('output', array($this, 'injectAppearance'));
        return $smarty;
    }

    public function injectAppearance($html, $template = null)
    {
        global $AUTH;
        if (empty($AUTH) || empty($AUTH->islogged) || isset($_GET['popup']) || isset($_GET['api'])
            || !is_string($html) || stripos($html, '</head>') === false
            || strpos($html, 'id="lms-theme-switcher-assets"') !== false
        ) {
            return $html;
        }
        // Avoid changing printouts, PDFs, API responses and standalone popups.
        // Only the regular LMS application shell carries this body class.
        if (!preg_match('/<body\b[^>]*\bclass\s*=\s*([\x22\x27])[^\x22\x27]*\blms-ui-main-document\b[^\x22\x27]*\1/i', $html)) {
            return $html;
        }
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Disposition:') === 0
                || (stripos($header, 'Content-Type:') === 0
                    && stripos($header, 'text/html') === false)) {
                return $html;
            }
        }
        $directory = dirname(__DIR__) . '/assets/';
        $css = @file_get_contents($directory . 'themes.css');
        $js = @file_get_contents($directory . 'switcher.js');
        $compatCss = @file_get_contents($directory . 'compatibility.css');
        $compatJs = @file_get_contents($directory . 'compatibility.js');
        if ($css === false || $js === false || $compatCss === false || $compatJs === false) {
            return $html;
        }
        $userId = class_exists('Auth') ? (int) Auth::GetCurrentUser() : 0;
        // Assets are embedded from this plugin's fixed paths. No public asset
        // mapping, remote fonts, extra endpoint or writable directory is needed.
        $assets = '<style id="lms-theme-switcher-assets" media="screen">' . $css . "\n" . $compatCss . '</style>'
            . '<script id="lms-theme-switcher-script" data-user-id="' . $userId . '">'
            . $js . "\n" . $compatJs . '</script>';
        return preg_replace_callback('/<\/head\s*>/i', function ($match) use ($assets) {
            return $assets . $match[0];
        }, $html, 1);
    }
}
