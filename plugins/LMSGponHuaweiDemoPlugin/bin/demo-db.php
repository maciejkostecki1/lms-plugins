#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

try {
    $ini=parse_ini_file('/etc/lms/lms.ini',true,INI_SCANNER_NORMAL);
    $d=$ini['database']??[];
    if (!in_array(strtolower((string)($d['type']??'')),['postgres','postgresql','pgsql'],true)) throw new RuntimeException('Wymagany PostgreSQL.');
    $dsn='pgsql:dbname='.$d['database'];
    foreach(['host','port']as$key)if(!empty($d[$key]))$dsn.=';'.$key.'='.$d[$key];
    $pdo=new PDO($dsn,$d['user'],$d['password']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $action=$argv[1]??'';
    if($action==='schema'){
        $pdo->beginTransaction();
        $pdo->exec(file_get_contents(__DIR__.'/../database/demo.sql'));
        $pdo->commit();
        echo "Zainstalowano lokalny schemat demo.\n";
    }else throw new RuntimeException('Użycie: php bin/demo-db.php schema. Urządzenia dodaje się w konfiguracji demo w LMS.');
}catch(Throwable$e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,"Konfiguracja demo nie powiodła się. Sprawdź dane, uprawnienia bazy i klucz szyfrowania.\n");exit(1);}
