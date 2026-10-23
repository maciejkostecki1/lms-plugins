#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require __DIR__.'/huawei-common.php';

hdExecute(static function(PDO $pdo,array $cfg): void {
    $host=$cfg['host']; $oltId=$cfg['olt_id']; $ports=hdPorts($pdo,$oltId);
    $data=hdColumns($cfg,['serial_hex'=>'43.1.3','description'=>'43.1.9'],$ports);
    $found=0; foreach ($data['serial_hex'] as $rows) $found+=count($rows);
    if (!$found) throw new RuntimeException('No ONT serials returned; DB left intact');
    $stmt=$pdo->prepare('INSERT INTO huawei_gpon_demo_onts (olt_id,olt_host,ifindex,ont_id,serial_hex,description) VALUES (?,?,?,?,?,?) ON CONFLICT (olt_id,ifindex,ont_id) DO UPDATE SET olt_host=EXCLUDED.olt_host, serial_hex=EXCLUDED.serial_hex,description=EXCLUDED.description');
    $pdo->beginTransaction();
    try {
        foreach ($data['serial_hex'] as $idx=>$onts) foreach ($onts as $id=>$serial) {
            $get=static fn(string $key)=>$data[$key][$idx][$id] ?? null;
            $stmt->execute([$oltId,$host,$idx,$id,$serial,$get('description')]);
        }
        $pdo->commit();
    } catch(Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    echo "OLT $host: saved $found ONT inventory entries; no deletions\n";
});
