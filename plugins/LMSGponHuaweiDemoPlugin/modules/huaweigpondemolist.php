<?php
if (!isset($DB, $SMARTY, $AUTH) || empty($AUTH->islogged)) { http_response_code(403); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && !(($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_GET['ajax'] ?? '') === 'refresh')) { http_response_code(405); exit('Demo RO: operacja niedostępna.'); }

$layout['pagetitle'] = 'Huawei GPON — lista ONT';

// HG-LIST-SINGLE-REFRESH-V1
// Ręczny odczyt tylko jednego ONT; zapis do PostgreSQL.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['hd_list_refresh_csrf'])) {
    $_SESSION['hd_list_refresh_csrf'] = bin2hex(random_bytes(24));
}

if (($_GET['ajax'] ?? '') === 'refresh') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    try {
        if (empty($AUTH->islogged)) {
            throw new RuntimeException('Brak autoryzacji.');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            throw new RuntimeException('Wymagana metoda POST.');
        }

        $token = (string)($_POST['csrf'] ?? '');

        if (!hash_equals(
            (string)$_SESSION['hd_list_refresh_csrf'],
            $token
        )) {
            throw new RuntimeException('Nieprawidłowy token.');
        }

        $rawId = (string)($_POST['id'] ?? '');

        if (!ctype_digit($rawId) || (int)$rawId < 1) {
            throw new RuntimeException('Nieprawidłowe ID ONT.');
        }

        $ont = $DB->GetRow(
            'SELECT n.id, n.olt_id, r.host AS olt_host,
                    n.ifindex, n.ont_id
             FROM huawei_gpon_demo_onts n
             JOIN huawei_gpon_demo_olts r ON r.id = n.olt_id
             WHERE n.id = ?',
            [(int)$rawId]
        );

        if (!$ont) {
            throw new RuntimeException('Nie znaleziono ONT.');
        }

        require_once __DIR__ . '/huaweigpondemorefresh.php';

        $result = hdRefreshSingleOnt($DB, $ont);

        echo json_encode(
            $result,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

    } catch (Throwable $e) {
        http_response_code(400);

        error_log('[HuaweiGPON Refresh] ' . $e->getMessage());

        echo json_encode([
            'ok' => false,
            'error' => 'Nie udało się odświeżyć ONT. Sprawdź log serwera.'
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}

$SMARTY->assign(
    'huawei_refresh_csrf',
    $_SESSION['hd_list_refresh_csrf']
);

$q = substr(trim((string)($_GET['q'] ?? '')), 0, 120);
$olt = substr(trim((string)($_GET['olt'] ?? '')), 0, 255);
$slot = trim((string)($_GET['slot'] ?? ''));
$port = trim((string)($_GET['port'] ?? ''));
$page = max(1, min(100000, (int)($_GET['page'] ?? 1)));
$perPage = 100;

// Inventory comes from the SNMP-discovered GPON ports, including empty ports.
// HG-LIST-OLT-ID-V2
$registeredOlts = $DB->GetAll(
    'SELECT id, host
     FROM huawei_gpon_demo_olts
     ORDER BY id'
) ?: array();

$knownOlts = array();
$oltIdsByHost = array();

foreach ($registeredOlts as $device) {
    $host = (string)$device['host'];
    $knownOlts[] = $host;
    $oltIdsByHost[$host] = (int)$device['id'];
}

if ($olt !== '' && !isset($oltIdsByHost[$olt])) {
    $olt = '';
}

$selectedOltId = $olt !== ''
    ? $oltIdsByHost[$olt]
    : null;

if ($slot !== '' && !ctype_digit($slot)) $slot = '';
if ($port !== '' && !ctype_digit($port)) $port = '';

$inventorySql = 'SELECT olt_id, ifindex, frame, slot, port, ifname FROM huawei_gpon_demo_ports';
$inventoryParams = array();
if ($olt !== '') {
    $inventorySql .= ' WHERE olt_id = ?';
    $inventoryParams[] = $selectedOltId;
}
$inventorySql .= ' ORDER BY frame, slot, port';
$inventory = $DB->GetAll($inventorySql, $inventoryParams) ?: array();
$available = array();
foreach ($inventory as $entry) {
    $s = (string)$entry['slot'];
    $p = (string)$entry['port'];
    if (!isset($available[$s])) $available[$s] = array();
    $available[$s][$p] = true;
}
ksort($available, SORT_NUMERIC);
if ($slot !== '' && !isset($available[$slot])) { $slot = ''; $port = ''; }
if ($slot === '') $port = '';
if ($port !== '' && !isset($available[$slot][$port])) $port = '';
$slots = array_keys($available);
$ports = $slot !== '' ? array_keys($available[$slot]) : array();
sort($ports, SORT_NUMERIC);

$conditions = array();
$params = array();
if ($olt !== '') {
    $conditions[] = 'o.olt_id = ?';
    $params[] = $selectedOltId;
}
if ($slot !== '') {
    $conditions[] = 'p.slot = ?';
    $params[] = (int)$slot;
}
if ($port !== '') {
    $conditions[] = 'p.port = ?';
    $params[] = (int)$port;
}
if ($q !== '') {
    $conditions[] = '(o.serial_hex ILIKE ? OR o.description ILIKE ? OR CAST(o.ont_id AS TEXT) = ?)';
    array_push($params, '%'.$q.'%', '%'.$q.'%', $q);
}
$where = $conditions ? ' WHERE '.implode(' AND ', $conditions) : '';
$from = ' FROM huawei_gpon_demo_onts o
JOIN huawei_gpon_demo_olts r ON r.id = o.olt_id
LEFT JOIN huawei_gpon_demo_ports p
    ON p.olt_id = o.olt_id AND p.ifindex = o.ifindex';
$total = (int)$DB->GetOne('SELECT COUNT(*)'.$from.$where, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);

$sql = 'SELECT o.id, o.olt_id, r.host AS olt_host, o.ifindex, o.ont_id, o.serial_hex,
 o.description, o.online_state, o.distance_m,
 p.ifname AS gpon_port, s.temperature_c, s.rx_dbm, s.olt_rx_dbm,
 s.voltage_mv, s.sampled_at'
 .$from.' LEFT JOIN LATERAL (
   SELECT temperature_c, rx_dbm, olt_rx_dbm, voltage_mv, sampled_at
   FROM huawei_gpon_demo_samples WHERE ont_ref = o.id
   ORDER BY sampled_at DESC, id DESC LIMIT 1
 ) s ON TRUE'.$where.' ORDER BY o.olt_id, p.frame NULLS LAST, p.slot NULLS LAST,
 p.port NULLS LAST, o.ifindex, o.ont_id LIMIT '.$perPage.' OFFSET '.(($page - 1) * $perPage);
$rows = $DB->GetAll($sql, $params) ?: array();
foreach ($rows as &$r) {
    if (empty($r['gpon_port'])) $r['gpon_port'] = 'ifIndex '.$r['ifindex'];
    // Huawei may report 2147483647 for unavailable measurements.
    // Format in PHP to avoid template ambiguity between NULL and zero.
    foreach (array('rx_dbm', 'olt_rx_dbm', 'temperature_c') as $field) {
        $value = $r[$field] ?? null;
        $r[$field.'_display'] = ($value === null || $value === '' || !is_numeric($value)
            || (float)$value === 2147483647.0)
            ? '—' : (string)$value;
    }
    $voltage = $r['voltage_mv'] ?? null;
    $r['voltage_display'] = ($voltage === null || $voltage === '' || !is_numeric($voltage)
        || (float)$voltage === 2147483647.0)
        ? '—' : number_format(((float)$voltage) / 1000, 3, '.', '');
}
unset($r);

$base = '?m=huaweigpondemolist&olt='.rawurlencode($olt).'&slot='.rawurlencode($slot)
    .'&port='.rawurlencode($port).'&q='.rawurlencode($q);
$SMARTY->assign('huawei_rows', $rows);
$SMARTY->assign('huawei_query', $q);
$SMARTY->assign('huawei_olts', $knownOlts);
$SMARTY->assign('huawei_olt', $olt);
$SMARTY->assign('huawei_slots', $slots);
$SMARTY->assign('huawei_slot', $slot);
$SMARTY->assign('huawei_ports', $ports);
$SMARTY->assign('huawei_port', $port);
$SMARTY->assign('huawei_total', $total);
$SMARTY->assign('huawei_page', $page);
$SMARTY->assign('huawei_pages', $pages);
$SMARTY->assign('huawei_page_start', $total ? (($page - 1) * $perPage + 1) : 0);
$SMARTY->assign('huawei_page_end', min($total, $page * $perPage));
$SMARTY->assign('huawei_base_url', $base);
$hdOltNames = [];
foreach ($DB->GetAll('SELECT name, host FROM huawei_gpon_demo_olts ORDER BY id') ?: [] as $hdDevice) {
    $hdOltNames[$hdDevice['host']] = $hdDevice['name'] . ' — ' . $hdDevice['host'];
}
$SMARTY->assign('hd_olt_names', $hdOltNames);
$SMARTY->display('huaweigpondemo/list.html');
