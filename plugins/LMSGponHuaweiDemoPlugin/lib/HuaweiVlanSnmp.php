<?php
declare(strict_types=1);

// HG-VLAN-RO-V1


require_once __DIR__ . '/HuaweiSnmpAccess.php';


/**
 * Odczyt kolumny SNMP zawierającej wartości INTEGER.
 * Tylko odczyt, bez SNMP SET.
 */
function hdVlanReadIntColumn(
    array $cfg,
    int $column
): array {
    $oid = '.1.3.6.1.4.1.2011.5.6.1.1.1.' . $column;

    $cmd = [
        'snmpbulkwalk',
        '-v2c',
        '-On',
        '-Cr10',
        '-t', (string)max(1, min(30, (int)$cfg['timeout'])),
        '-r', (string)max(0, min(5, (int)$cfg['retries'])),
        '-c', (string)$cfg['community'],
        (string)$cfg['host'],
        $oid,
    ];

    $pipes = [];

    $process = proc_open(
        $cmd,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Nie można wykonać odczytu SNMP.');
    }

    fclose($pipes[0]);

    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $code = proc_close($process);

    if ($code !== 0) {
        throw new RuntimeException(
            'Błąd odczytu kolumny SNMP ' . $column
        );
    }

    $result = [];

    foreach (preg_split('/\r\n|\r|\n/', (string)$output) as $line) {
        $pattern = '/^\.?' .
            preg_quote(ltrim($oid, '.'), '/') .
            '\.(\d+)\s+=\s+INTEGER:\s*(-?\d+)\s*$/';

        if (preg_match($pattern, trim($line), $m)) {
            $result[(int)$m[1]] = (int)$m[2];
        }
    }

    return $result;
}


// HG-VLAN-UPLINK-RO-V1
// Odczyt map portów, bez zapisu SNMP.

function hdVlanReadPortMaps(array $cfg): array
{
    $oid = '.1.3.6.1.4.1.2011.5.6.1.1.1.3';

    $cmd = [
        'snmpbulkwalk',
        '-v2c', '-On', '-Cr10',
        '-t', (string)max(1, min(30, (int)$cfg['timeout'])),
        '-r', (string)max(0, min(5, (int)$cfg['retries'])),
        '-c', (string)$cfg['community'],
        (string)$cfg['host'],
        $oid,
    ];

    $pipes = [];
    $process = proc_open(
        $cmd,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Nie można odczytać map portów.');
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        throw new RuntimeException(
            'Błąd odczytu map portów SNMP.'
        );
    }

    $maps = [];

    foreach (preg_split('/\r\n|\r|\n/', (string)$output) as $line) {
        if (!preg_match(
            '/^\.?1\.3\.6\.1\.4\.1\.2011\.5\.6\.1\.1\.1\.3\.(\d+)\s+=\s+STRING:\s*"([0-9A-Fa-f]+)"$/',
            trim($line),
            $m
        )) {
            continue;
        }

        $maps[(int)$m[1]] = strtoupper($m[2]);
    }

    return $maps;
}

function hdVlanDecodeUplinks(?string $map): string
{
    if ($map === null || strlen($map) !== 416) {
        return 'Brak danych';
    }

    // Pozycja 129, indeks liczony od zera.
    // Potwierdzone dla Huawei MA5683T:
    // bit 1 = 0/8/0
    // bit 2 = 0/8/1

    $value = hexdec($map[129]);
    $ports = [];

    if ($value & 1) {
        $ports[] = '0/8/0';
    }

    if ($value & 2) {
        $ports[] = '0/8/1';
    }

    // Sprawdź, czy występują inne niezerowe bity.
    $remaining = $map;
    $remaining[129] = strtoupper(
        dechex($value & ~3)
    );

    $unknown = trim($remaining, '0') !== '';

    if ($unknown) {
        $ports[] = 'inne (niezdekodowane)';
    }

    if (!$ports) {
        return 'Brak';
    }

    return implode(', ', $ports);
}

function hdVlanReadList($DB, int $oltId): array
{
    $cfg = hdSnmpAccessForOlt($DB, $oltId);

    $baseOid = '.1.3.6.1.4.1.2011.5.6.1.1.1.2';

    $timeout = max(1, min(30, (int)$cfg['timeout']));
    $retries = max(0, min(5, (int)$cfg['retries']));

    $cmd = [
        'snmpbulkwalk',
        '-v2c',
        '-On',
        '-Cr10',
        '-t', (string)$timeout,
        '-r', (string)$retries,
        '-c', (string)$cfg['community'],
        (string)$cfg['host'],
        $baseOid,
    ];

    $pipes = [];

    $process = proc_open(
        $cmd,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );

    if (!is_resource($process)) {
        throw new RuntimeException(
            'Nie można uruchomić snmpbulkwalk.'
        );
    }

    fclose($pipes[0]);

    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        // Nie ujawniamy community w komunikatach.
        throw new RuntimeException(
            'Błąd odczytu VLAN przez SNMP. ' .
            'Sprawdź dostępność OLT i uprawnienia RO. ' .
            'Kod: ' . $exitCode
        );
    }

    $vlans = [];

    foreach (preg_split('/\r\n|\r|\n/', (string)$output) as $line) {
        if (!preg_match(
            '/^\.?1\.3\.6\.1\.4\.1\.2011\.5\.6\.1\.1\.1\.2\.(\d+)\s+=\s+(.*)$/',
            trim($line),
            $matches
        )) {
            continue;
        }

        $id = (int)$matches[1];

        if ($id < 1 || $id > 4094) {
            continue;
        }

        $value = trim($matches[2]);

        if (preg_match('/^STRING:\s*(.*)$/s', $value, $m)) {
            $value = trim($m[1]);

            if (
                strlen($value) >= 2 &&
                $value[0] === '"' &&
                substr($value, -1) === '"'
            ) {
                $value = substr($value, 1, -1);
                $value = stripcslashes($value);
            }
        } elseif (preg_match(
            '/^Hex-STRING:\s*(.*)$/s',
            $value,
            $m
        )) {
            $hex = preg_replace('/\s+/', '', trim($m[1]));

            if (
                $hex !== '' &&
                ctype_xdigit($hex) &&
                strlen($hex) % 2 === 0
            ) {
                $decoded = hex2bin($hex);
                if ($decoded !== false) {
                    $value = rtrim($decoded, "\0");
                }
            }
        } elseif ($value === '""') {
            // Huawei zwraca pusty opis bez prefiksu STRING.
            $value = '';
        } else {
            // Nie traktujemy nieznanych typów jako opisu VLAN.
            continue;
        }

        $vlans[$id] = [
            'id' => $id,
            'description' => $value,
        ];
    }


    // Odczyt typu i atrybutu VLAN.
    $types = hdVlanReadIntColumn($cfg, 4);
    $attributes = hdVlanReadIntColumn($cfg, 14);

    $typeNames = [
        7 => 'Smart',
    ];

    $attributeNames = [
        1 => 'Common',
        2 => 'QinQ',
    ];

    foreach ($vlans as $id => &$vlan) {
        $type = $types[$id] ?? null;
        $attribute = $attributes[$id] ?? null;

        $vlan['type'] = $type === null
            ? 'Brak danych'
            : ($typeNames[$type] ?? 'Nieznany (' . $type . ')');

        $vlan['attribute'] = $attribute === null
            ? 'Brak danych'
            : ($attributeNames[$attribute]
                ?? 'Nieznany (' . $attribute . ')');
    }

    unset($vlan);

    // Porty uplink z mapy VLAN.
    $portMaps = hdVlanReadPortMaps($cfg);

    foreach ($vlans as $id => &$vlan) {
        $vlan['uplinks'] = hdVlanDecodeUplinks(
            $portMaps[$id] ?? null
        );
    }
    unset($vlan);

    ksort($vlans, SORT_NUMERIC);


    return array_values($vlans);
}
