#!/usr/bin/env php
<?php
if(PHP_SAPI!=='cli') { http_response_code(403); exit; }
$script_name='LMS Notify Panel';
$script_parameters=['apply'=>null,'install-schema'=>null,'upgrade-schema'=>null];
$script_help="--apply wykonuje tylko działania włączone w panelu. Bez --apply: podgląd.\n--install-schema tworzy wyłącznie tabele wtyczki (pierwsza instalacja).\n";
$root=dirname(__DIR__,3);
if(!is_file($root.'/bin/script-options.php')) { fwrite(STDERR,"Umieść wtyczkę w katalogu plugins instalacji LMS.\n"); exit(1); }
require $root.'/bin/script-options.php';
require_once __DIR__.'/../lib/NotifyPanel.php';
date_default_timezone_set('Europe/Warsaw');
try {
    if(isset($options['install-schema'])||isset($options['upgrade-schema'])) {
        if(isset($options['apply'])) throw new RuntimeException('Instaluj schemat oddzielnie od wykonywania działań.');
        $installDb=new NotifyDb($DB);
        $installDb->BeginTrans();
        try {
            $installDb->Execute(file_get_contents(__DIR__.'/../sql/'.(isset($options['upgrade-schema'])?'upgrade-0.3.0.pgsql.sql':'install.pgsql.sql')));
            $installDb->Execute(file_get_contents(__DIR__.'/../sql/upgrade-0.4.0.pgsql.sql'));
            $installDb->Execute(file_get_contents(__DIR__.'/../sql/upgrade-0.4.2.pgsql.sql'));
            $installDb->Execute(file_get_contents(__DIR__.'/../sql/upgrade-0.7.0.pgsql.sql'));
            $installDb->Execute(file_get_contents(__DIR__.'/../sql/upgrade-0.7.2.pgsql.sql'));
            $installDb->Execute(file_get_contents(__DIR__.'/../sql/upgrade-0.7.3.pgsql.sql'));
            $installDb->Execute(file_get_contents(__DIR__.'/../sql/upgrade-0.8.0.pgsql.sql'));
            $installDb->Execute(file_get_contents(__DIR__.'/../sql/upgrade-0.8.1.pgsql.sql'));
            $installDb->CommitTrans();
        } catch(Throwable $e) { $installDb->RollbackTrans(); throw $e; }
        echo "Schemat wtyczki gotowy; ustawienia automatyki zachowano.\n"; exit;
    }
    $AUTH=null; $SYSLOG=SYSLOG::getInstance();
    $LMS=new LMS($DB,$AUTH,$SYSLOG);
    $LMS->setPluginManager(LMSPluginManager::getInstance());
    $np=new NotifyPanel($DB,$LMS,0); $s=$np->settings(); $after=0; $errors=0;
    do {
        $rows=$np->customers($after,100);
        foreach($rows as $c) {
            $after=(int)$c['id'];
            if($c['excluded'] || $c['state']==='review') continue;
            $actions=$np->scheduledActions($c,$s);
            if(!isset($options['apply'])) {
                printf("#%d saldo=%.2f miesięcznie=%.2f procent=%.2f ocena=%s plan=%s\n",$c['id'],$c['balance'],$c['base'],$c['percent'],$c['state'],implode(',',$actions)?:'brak');
                continue;
            }
            foreach($actions as $action) {
                try { echo '#'.$c['id'].' '.$action.': '.$np->act((int)$c['id'],$action,$s['revision'],null,true).PHP_EOL; }
                catch(Throwable $e) { ++$errors; fwrite(STDERR,'#'.$c['id'].' '.$action.': '.$e->getMessage().PHP_EOL); }
            }
        }
    } while(count($rows)===100);
    if($errors) exit(2);
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1); }
