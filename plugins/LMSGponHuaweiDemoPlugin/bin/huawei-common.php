<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

// HG-COLLECTOR-DB-RO-V1


// Huawei GPON — konfiguracja wielu OLT z PostgreSQL.
// Community SNMP tymczasowo pozostaje w lms.ini.

function hdUnquote(mixed $value): string {
    $v = trim((string)$value);
    if (
        strlen($v) >= 2
        && (
            ($v[0] === '"' && substr($v, -1) === '"')
            || ($v[0] === "'" && substr($v, -1) === "'")
        )
    ) {
        return substr($v, 1, -1);
    }
    return $v;
}

function hdConnect(): array {
    $ini = parse_ini_file(
        '/etc/lms/lms.ini',
        true,
        INI_SCANNER_NORMAL
    );

    if ($ini === false || !isset($ini['database'])) {
        throw new RuntimeException('Missing [database]');
    }

    $d = $ini['database'];
    $g = $ini['huawei_gpon'] ?? [];

    if (!in_array(
        strtolower(hdUnquote($d['type'] ?? '')),
        ['postgres', 'postgresql', 'pgsql'],
        true
    )) {
        throw new RuntimeException('PostgreSQL required');
    }

    $dbname = hdUnquote($d['database'] ?? '');
    $user = hdUnquote($d['user'] ?? '');

    if ($dbname === '' || $user === '') {
        throw new RuntimeException('Database name/user missing');
    }

    $dsn = 'pgsql:dbname=' . $dbname;

    $host = hdUnquote($d['host'] ?? '');
    $port = hdUnquote($d['port'] ?? '');

    if ($host !== '') {
        $dsn .= ';host=' . $host;
    }
    if ($port !== '') {
        $dsn .= ';port=' . $port;
    }

    $pdo = new PDO(
        $dsn,
        $user,
        hdUnquote($d['password'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Community RO pobieramy wyłącznie z zaszyfrowanego magazynu DB.

    $stmt = $pdo->query(
        'SELECT o.id, o.name, o.host,
                o.snmp_timeout, o.snmp_retries,
                s.community_ro
         FROM huawei_gpon_demo_olts o
         LEFT JOIN huawei_gpon_demo_snmp_secrets s
           ON s.olt_id = o.id
         WHERE o.enabled = TRUE
         ORDER BY o.id'
    );

    $configs = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $olt) {
        $oltHost = (string)$olt['host'];

        if (!filter_var(
            $oltHost,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4
        )) {
            throw new RuntimeException(
                'Invalid OLT IPv4 in database, id=' . $olt['id']
            );
        }

        if (empty($olt['community_ro'])) {
            throw new RuntimeException(
                'Brak community RO dla OLT #' . $olt['id']
            );
        }

        $community = (string)$olt['community_ro'];

        if ($community === '') {
            throw new RuntimeException(
                'Puste community RO dla OLT #' . $olt['id']
            );
        }

        $configs[] = [
            'olt_id' => (int)$olt['id'],
            'name' => (string)$olt['name'],
            'host' => $oltHost,
            'community' => $community,
            'timeout' => (int)$olt['snmp_timeout'],
            'retries' => (int)$olt['snmp_retries'],
        ];
    }

    return [$pdo, $configs];
}

function hdWalk(array $cfg, string $oid): array {
    $command = ['/usr/bin/timeout','60','/usr/bin/snmpbulkwalk','-v2c','-c',$cfg['community'],'-On','-Cr20','-t',(string)$cfg['timeout'],'-r',(string)$cfg['retries'],$cfg['host'],'.'.$oid];
    $p = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['file','/dev/null','w']], $pipes, null, null, ['bypass_shell'=>true]);
    if (!is_resource($p)) throw new RuntimeException('Cannot start snmpbulkwalk');
    fclose($pipes[0]); $out = stream_get_contents($pipes[1],16777217); fclose($pipes[1]);
    if (strlen($out)>16777216) { proc_terminate($p); proc_close($p); throw new RuntimeException('Odpowiedź SNMP przekracza limit.'); }
    $rc = proc_close($p);
    if ($rc !== 0 || str_contains($out, 'Timeout:')) throw new RuntimeException('SNMP failed for '.$oid.'; no usable response within the time limit.');
    // Net-SNMP wraps long Hex-STRING values onto continuation lines.
    $out = preg_replace('/\r?\n[ \t]*((?:[0-9A-Fa-f]{2}[ \t]+)*[0-9A-Fa-f]{2})[ \t]*(?=\r?\n|$)/', ' $1', $out);
    $data = [];
    foreach (explode("\n",$out) as $line) {
        if (!preg_match('/^\.?'.preg_quote($oid,'/').'\.(\d+(?:\.\d+)*)\s+=\s+([\w-]+):\s*(.*)$/',trim($line),$m)) continue;
        $type=$m[2]; $raw=trim($m[3]);
        if (in_array($type,['INTEGER','Gauge32','Counter32'],true)) {
            if (!preg_match('/^-?\d+/', $raw, $n)) continue;
            $v=(int)$n[0];
        } elseif ($type==='Hex-STRING') {
            $v=strtoupper(preg_replace('/[^0-9A-F]/i','',$raw));
            if ($oid === '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.9') {
                $decoded=hex2bin($v);
                if ($decoded===false || preg_match('//u',$decoded)!==1) throw new RuntimeException('Opis ONT nie jest poprawnym tekstem UTF-8; zapis przerwany.');
                $v=rtrim($decoded, "\0 \t\r\n");
            }
        }
        elseif ($type==='STRING') $v=trim($raw,'"');
        else continue;
        $data[$m[1]]=$v;
    }
    return $data;
}
function hdColumns(array $cfg, array $columns, array $ports): array {
    $result=[]; $root='1.3.6.1.4.1.2011.6.128.1.1.2.';
    foreach ($columns as $field=>$oid) {
        $rows=hdWalk($cfg,$root.$oid); $result[$field]=[];
        foreach ($rows as $suffix=>$value) {
            if (!preg_match('/^(\d+)\.(\d+)$/',$suffix,$m) || !isset($ports[$m[1]])) continue;
            $result[$field][$m[1]][(int)$m[2]]=$value;
        }
        echo "  $field: ".count($rows)." SNMP entries\n";
    }
    return $result;
}

function hdPorts(PDO $pdo, int $oltId): array {
    $stmt = $pdo->prepare(
        'SELECT ifindex, ifname
         FROM huawei_gpon_demo_ports
         WHERE olt_id = ?
         ORDER BY frame, slot, port'
    );

    $stmt->execute([$oltId]);

    $ports = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $ports[(string)$p['ifindex']] = $p['ifname'];
    }

    if (!$ports) {
        throw new RuntimeException(
            'No GPON ports in database. Run collect-inventory.php first'
        );
    }

    return $ports;
}

function hdExecute(callable $fn): void {
    try {
        [$pdo, $configs] = hdConnect();
    } catch (Throwable $e) {
        fwrite(STDERR, 'Collector init error: '
            . $e->getMessage() . "\n");
        exit(1);
    }

    if (!$configs) {
        echo "No active Huawei OLT in database.\n";
        return;
    }

    $failed = 0;

    foreach ($configs as $cfg) {
        try {
            echo "OLT #" . $cfg['olt_id']
                . " (" . $cfg['host'] . ")\n";

            $fn($pdo, $cfg);
        } catch (Throwable $e) {
            ++$failed;
            fwrite(
                STDERR,
                'OLT #' . $cfg['olt_id']
                . ' (' . $cfg['host'] . '): '
                . $e->getMessage() . "\n"
            );
        }
    }

    if ($failed > 0) {
        fwrite(
            STDERR,
            "Collector finished with $failed OLT errors.\n"
        );
        exit(1);
    }
}
