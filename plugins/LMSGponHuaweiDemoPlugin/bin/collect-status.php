#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require __DIR__ . '/huawei-common.php';

// Fast poll: ONT state + ONT RX + OLT RX. Exactly three SNMP column walks.
// Keep latest previously recorded slow diagnostics (temperature/voltage) in each snapshot.
hdExecute(static function (PDO $pdo, array $cfg): void {
    $host = $cfg['host'];
    $oltId = $cfg['olt_id'];
    $ports = hdPorts($pdo, $oltId);
    $data = hdColumns($cfg, [
        'online_state' => '46.1.15',
        'ont_rx_raw' => '51.1.4',
        'olt_rx_raw' => '51.1.6',
    ], $ports);
    $entries = array_sum(array_map('count', $data['online_state']));
    if ($entries === 0) throw new RuntimeException('No ONT status returned; database unchanged');

    $lookup = $pdo->prepare('SELECT id,ifindex,ont_id FROM huawei_gpon_demo_onts WHERE olt_id=?');
    $lookup->execute([$oltId]);
    $ontRefs=[];
    foreach ($lookup->fetchAll(PDO::FETCH_ASSOC) as $row) $ontRefs[$row['ifindex'].':'.$row['ont_id']]=$row['id'];
    $update = $pdo->prepare('UPDATE huawei_gpon_demo_onts SET online_state=? WHERE id=?');
    // Cached diagnostics are copied, not measured. They stay visible until slow collector refreshes them.
    $sample = $pdo->prepare('INSERT INTO huawei_gpon_demo_samples
        (ont_ref,temperature_c,rx_dbm,olt_rx_dbm,tx_dbm,voltage_mv)
        SELECT ?, old.temperature_c, ?, ?, NULL, old.voltage_mv
        FROM (SELECT 1) x
        LEFT JOIN LATERAL (
            SELECT temperature_c, voltage_mv
            FROM huawei_gpon_demo_samples WHERE ont_ref = ?
            ORDER BY sampled_at DESC, id DESC LIMIT 1
        ) old ON TRUE');
    $clean = static fn($v): ?int => ($v === null || $v === 2147483647 || $v === -2147483648) ? null : (int)$v;
    $saved = 0; $missing = 0; $invalidOltRx = 0;
    $pdo->beginTransaction();
    try {
        foreach ($data['online_state'] as $idx => $onts) {
            foreach ($onts as $ontId => $state) {
                $ref = $ontRefs[$idx.':'.$ontId] ?? false;
                if ($ref === false) { ++$missing; continue; }
                $get = static fn(string $key) => $data[$key][$idx][$ontId] ?? null;
                $update->execute([$clean($state), $ref]);
                $ontRaw = $clean($get('ont_rx_raw'));
                $oltRaw = $clean($get('olt_rx_raw'));
                $ontRx = $ontRaw === null ? null : $ontRaw / 100;
                $oltRx = null;
                if ($oltRaw !== null) {
                    $calculated = ($oltRaw - 10000) / 100;
                    if ($calculated >= -50 && $calculated <= 10) $oltRx = $calculated;
                    else ++$invalidOltRx;
                }
                $sample->execute([$ref, $ontRx, $oltRx, $ref]);
                ++$saved;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    echo "OLT $host: fast status/optics saved $saved ONTs; $missing unknown skipped; $invalidOltRx invalid OLT RX ignored\n";
});
