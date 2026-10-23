#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require __DIR__.'/huawei-common.php';
hdExecute(static function(PDO $pdo,array $cfg): void {
    $host=$cfg['host']; $oltId=$cfg['olt_id'];
    echo "OLT $host: discovering physical GPON ports...\n";
    $names=hdWalk($cfg,'1.3.6.1.2.1.31.1.1.1.1'); $ports=[];
    foreach ($names as $idx=>$name) {
        if (!preg_match('/^GPON\s+(\d+)\/(\d+)\/(\d+)$/i',trim((string)$name),$m)) continue;
        $ports[]=['idx'=>$idx,'frame'=>(int)$m[1],'slot'=>(int)$m[2],'port'=>(int)$m[3],'name'=>$name];
    }
    if (!$ports) throw new RuntimeException('No GPON ports discovered; DB left intact');
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('INSERT INTO huawei_gpon_demo_ports (olt_id,olt_host,ifindex,frame,slot,port,ifname,last_seen) VALUES (?,?,?,?,?,?,?,NOW()) ON CONFLICT (olt_id,ifindex) DO UPDATE SET olt_host=EXCLUDED.olt_host,frame=EXCLUDED.frame,slot=EXCLUDED.slot,port=EXCLUDED.port,ifname=EXCLUDED.ifname,last_seen=NOW()');
        foreach ($ports as $p) $stmt->execute([$oltId,$host,$p['idx'],$p['frame'],$p['slot'],$p['port'],$p['name']]);
        $pdo->commit();
    } catch(Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    echo "OLT $host: saved ".count($ports)." GPON ports (physical interfaces only; card models not collected)\n";
});
