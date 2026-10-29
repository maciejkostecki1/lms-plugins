<?php
class LMSPlugin { protected $handlers = array(); }
class Smarty {
    public $filters = array();
    public function registerFilter($type, $callback) { $this->filters[$type] = $callback; }
}
class Auth {
    public $islogged = true;
    public static $user = 42;
    public static function GetCurrentUser() { return self::$user; }
}
require __DIR__ . '/../LMSThemeSwitcherPlugin.php';
$plugin = new LMSThemeSwitcherPlugin();
$plugin->registerHandlers();
$handler = new ThemeSwitcherInitHandler();
$menu = $handler->menuInit(array('config' => array('submenu' => array('existing' => array('name' => 'Existing')))));
check(isset($menu['config']['submenu']['existing']) && $menu['config']['submenu']['lmstheme']['link'] === '?m=lmstheme', 'settings page added without replacing other configuration entries');
$dirs = $handler->modulesInit(array('/existing/modules'));
check($dirs[1] === '/existing/modules' && is_file($dirs[0] . '/lmstheme.php'), 'settings module discovered alongside core modules');
$smarty = new Smarty();
assert($handler->smartyInit($smarty) === $smarty);
$AUTH = new Auth();
function check($condition, $name) {
    if (!$condition) { throw new RuntimeException($name); }
    echo "PASS $name\n";
}
$header = '<!doctype html><html><head><title>LMS</title></head><body class="lms-ui-main-document main-menu-visible">';
$page = $header . '<section id="lms-ui-contents"><form action="?m=save" method="post"><input name="csrf" value="unchanged"></form></section></body></html>';
$result = call_user_func($smarty->filters['output'], $page);
check(strpos($result, 'data-user-id="42"') !== false, 'logged-in operator receives plugin');
check(strpos($result, '<form action="?m=save" method="post"><input name="csrf" value="unchanged"></form>') !== false, 'business form and CSRF token unchanged');
check(strpos($handler->injectAppearance($header), 'lms-theme-switcher-assets') !== false, 'separately rendered LMS header supported');
check($handler->injectAppearance($result) === $result, 'duplicate filter execution does not inject twice');
$_GET['popup'] = '1';
check($handler->injectAppearance($page) === $page, 'LMS popup parameter excludes appearance assets');
unset($_GET['popup']);
$_GET['api'] = '';
check($handler->injectAppearance($page) === $page, 'LMS API parameter excludes appearance assets');
unset($_GET['api']);
foreach (array('{"result":"ok"}', '<tr><td>ajax fragment</td></tr>', '<html><head></head><body>Invoice print</body></html>', '<html><head></head><body class="lms-ui-popup">Popup</body></html>') as $other) {
    check($handler->injectAppearance($other) === $other, 'non-application output unchanged');
}
$AUTH->islogged = false;
check($handler->injectAppearance($page) === $page, 'login page untouched');
$AUTH->islogged = true;
Auth::$user = 73;
check(strpos($handler->injectAppearance($page), 'data-user-id="73"') !== false, 'operator-specific preference namespace');
check(strpos($result, 'media="screen"') !== false, 'appearance stylesheet excluded from print');
check(strpos($result, '<script id="lms-theme-switcher-script"') < strpos($result, '</head>'), 'saved theme applied before body rendering');
echo "ALL PASSED\n";
