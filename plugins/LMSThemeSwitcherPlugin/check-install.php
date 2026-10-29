<?php
/** Optional, read-only CLI installation check. No credentials or DB access. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$failures = 0;
foreach (array('LMSThemeSwitcherPlugin.php', 'handlers/ThemeSwitcherInitHandler.php',
    'modules/lmstheme.php', 'assets/themes.css', 'assets/switcher.js',
    'assets/compatibility.css', 'assets/compatibility.js') as $file) {
    $ok = is_readable(__DIR__ . '/' . $file);
    echo ($ok ? 'OK ' : 'BRAK ') . $file . PHP_EOL;
    if (!$ok) $failures++;
}
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!is_readable($autoload)) {
    echo 'BRAK: umiesc wtyczke w plugins/LMSThemeSwitcherPlugin aktywnej instalacji LMS.' . PHP_EOL;
    $failures++;
} else {
    require_once $autoload;
    $ok = class_exists('LMSThemeSwitcherPlugin') && class_exists('ThemeSwitcherInitHandler');
    echo $ok ? 'OK rejestracja klas' . PHP_EOL
        : 'BRAK: odswiez indeks klas Composer w katalogu glownym LMS.' . PHP_EOL;
    if (!$ok) $failures++;
}
echo 'Nastepnie wlacz Motywy LMS w Konfiguracja > Wtyczki. Ustawienia wygladu sa lokalne dla przegladarki.' . PHP_EOL;
exit($failures ? 1 : 0);
