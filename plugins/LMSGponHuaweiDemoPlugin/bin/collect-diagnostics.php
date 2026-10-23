#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require __DIR__ . '/huawei-common.php';

// Slow poll: temperature, voltage, distance, last disconnect cause.
// Optical RX and state belong exclusively to the fast collector.
hdExecute(static function (PDO $pdo, array $cfg): void {
    $host = $cfg['host'];
    $oltId = $cfg['olt_id'];
    $ports = hdPorts($pdo, $oltId);
    $data = hdColumns($cfg, [
        'temperature_c' => '51.1.1',
        'voltage_mv' => '51.1.5',
        'distance_m' => '46.1.20',
        'last_down_cause' => '46.1.24',
    ], $ports);
    // Temperature can be absent for offline ONTs: allow other diagnostic fields to anchor existence.
    $keys = [];
    foreach ($data as $column) foreach ($column as $idx => $onts) {
        foreach ($onts as $ontId => $_value) $keys[$idx][$ontId] = true;
    }
    $found = array_sum(array_map('count', $keys));
    if ($found === 0) throw new RuntimeException('No diagnostics returned; database unchanged');

    $lookup = $pdo->prepare('SELECT id FROM huawei_gpon_demo_onts WHERE olt_id=? AND ifindex=? AND ont_id=?');
    $update = $pdo->prepare('UPDATE huawei_gpon_demo_onts
        SET distance_m=COALESCE(?,distance_m), last_down_cause=COALESCE(?,last_down_cause)
        WHERE id=?');
    // Copy the most recent RX readings; do not overwrite status or RX fields.
    $sample = $pdo->prepare('INSERT INTO huawei_gpon_demo_samples
        (ont_ref,temperature_c,rx_dbm,olt_rx_dbm,tx_dbm,voltage_mv)
        SELECT ?, ?, old.rx_dbm, old.olt_rx_dbm, NULL, ?
        FROM (SELECT 1) x
        LEFT JOIN LATERAL (
            SELECT rx_dbm, olt_rx_dbm
            FROM huawei_gpon_demo_samples WHERE ont_ref = ?
            ORDER BY sampled_at DESC, id DESC LIMIT 1
        ) old ON TRUE');
    $clean = static fn($v): ?int => ($v === null || $v === 2147483647 || $v === -2147483648) ? null : (int)$v;
    $saved = 0; $missing = 0;
    $pdo->beginTransaction();
    try {
        foreach ($keys as $idx => $onts) foreach ($onts as $ontId => $_) {
            $lookup->execute([$oltId, $idx, $ontId]);
            $ref = $lookup->fetchColumn();
            if ($ref === false) { ++$missing; continue; }
            $get = static fn(string $key) => $data[$key][$idx][$ontId] ?? null;
            $update->execute([$clean($get('distance_m')), $clean($get('last_down_cause')), $ref]);
            $sample->execute([$ref, $clean($get('temperature_c')), $clean($get('voltage_mv')), $ref]);
            ++$saved;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    echo "OLT $host: slow diagnostics saved $saved ONTs; $missing unknown skipped\n";
});
