<?php
require_once __DIR__ . '/../lib/HuaweiSnmpAccess.php';
// HG-SNMP-OLT-ID-V1
// Odczyt diagnostyki pojedynczego ONT, tylko SNMP GET.
// Wywoływany wewnętrznie przez huaweigpondemolist.php.

function hdRefreshSingleOnt($DB, array $ont): array
{
    $access = hdSnmpAccessForOlt(
        $DB,
        (int)($ont['olt_id'] ?? 0)
    );

    $host = $access['host'];
    $community = $access['community'];
    $ifindex = (int)$ont['ifindex'];
    $ontId = (int)$ont['ont_id'];

    $fields = [
        'state' => '46.1.15',
        'ont_rx' => '51.1.4',
        'olt_rx' => '51.1.6',
        'distance' => '46.1.20',
        'temperature' => '51.1.1',
        'voltage' => '51.1.5',
    ];

    $cmd = [
        'snmpget', '-v2c', '-c', $community,
        '-On', '-Oqv', '-t', '3', '-r', '0', $host
    ];

    foreach ($fields as $oid) {
        $cmd[] = '.1.3.6.1.4.1.2011.6.128.1.1.2.'
            . $oid . '.' . $ifindex . '.' . $ontId;
    }

    $proc = proc_open(
        $cmd,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        null,
        ['bypass_shell' => true]
    );

    if (!is_resource($proc)) {
        throw new RuntimeException('Nie można uruchomić SNMP.');
    }

    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    if (proc_close($proc) !== 0) {
        throw new RuntimeException('OLT nie odpowiedział na SNMP.');
    }

    $lines = preg_split('/\r?\n/', trim($out));

    if (count($lines) !== count($fields)) {
        throw new RuntimeException('Niekompletna odpowiedź OLT.');
    }

    $values = [];
    foreach (array_keys($fields) as $i => $name) {
        $v = trim($lines[$i], " \t\n\r\"");

        $values[$name] = preg_match('/^-?\d+$/', $v)
            ? (int)$v
            : null;

        if (in_array($values[$name], [2147483647, -2147483648], true)) {
            $values[$name] = null;
        }
    }

    if ($values['state'] === null) {
        throw new RuntimeException('Brak poprawnego statusu ONT.');
    }

    $rx = $values['ont_rx'] === null
        ? null : $values['ont_rx'] / 100;

    $oltRx = $values['olt_rx'] === null
        ? null : ($values['olt_rx'] - 10000) / 100;

    if ($oltRx !== null && ($oltRx < -50 || $oltRx > 10)) {
        $oltRx = null;
    }

    $state = $values['state'];

    // Aktualizacja statusu i zapis pomiaru w jednej transakcji.
    $DB->Execute('BEGIN');

    try {
        $DB->Execute(
            'UPDATE huawei_gpon_demo_onts
             SET online_state = ?,
                 distance_m = COALESCE(?, distance_m)
             WHERE id = ?',
            [$state, $values['distance'], (int)$ont['id']]
        );

        $DB->Execute(
            'INSERT INTO huawei_gpon_demo_samples
             (ont_ref, rx_dbm, olt_rx_dbm, temperature_c, voltage_mv)
             VALUES (?, ?, ?, ?, ?)',
            [
                (int)$ont['id'],
                $rx,
                $oltRx,
                $values['temperature'],
                $values['voltage']
            ]
        );

        $DB->Execute('COMMIT');

    } catch (Throwable $e) {
        $DB->Execute('ROLLBACK');
        throw $e;
    }

    return [
        'ok' => true,
        'state' => $state,
        'ont_rx' => $rx === null ? '—' : number_format($rx, 2, '.', ''),
        'olt_rx' => $oltRx === null ? '—' : number_format($oltRx, 2, '.', ''),
        'distance' => $values['distance'],
        'time' => date('Y-m-d H:i:s'),
    ];
}
