<?php
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); header('Allow: GET'); exit('Demo RO: operacja niedostępna.'); }

if (!isset($DB, $SMARTY, $AUTH) || empty($AUTH->islogged)) { http_response_code(403); exit; }

if (!isset($SMARTY, $AUTH, $DB) || empty($AUTH->islogged)) {
    http_response_code(403);
    exit;
}
$layout['pagetitle'] = 'Huawei GPON - Alerty OLT';
$rows = [];
$error = '';
$updated = '';
require_once __DIR__ . '/../lib/HuaweiTrapNames.php';
$scopes = ['all' => 'Wszystkie urządzenia', 'ont' => 'ONT', 'olt' => 'OLT', 'unknown' => 'Nierozpoznane'];
$kinds = ['all' => 'Wszystkie zdarzenia', 'alarm' => 'Alarmy', 'recovery' => 'Ustąpienie alarmu', 'notification' => 'Powiadomienia', 'unknown' => 'Nierozpoznane'];
$scope = is_string($_GET['scope'] ?? null) && isset($scopes[$_GET['scope']]) ? $_GET['scope'] : 'all';
$kind = is_string($_GET['kind'] ?? null) && isset($kinds[$_GET['kind']]) ? $_GET['kind'] : 'all';
$total = 0;
$path = '/var/lib/lms-huawei-alerts/alerts.json';
if (!is_readable($path) || filesize($path) > 2097152) {
    $error = 'Dane alertów są chwilowo niedostępne.';
} else {
    $data = json_decode(file_get_contents($path), true, 16);
    if (!is_array($data) || !isset($data['rows']) || !is_array($data['rows'])) {
        $error = 'Nieprawidłowy format danych alertów.';
    } else {
        // Mask configured RO communities; never pass them to Smarty.
        
        $communities = [];
        try {
            $secrets = $DB->GetAll('SELECT community_ro FROM huawei_gpon_demo_snmp_secrets') ?: [];
            foreach ($secrets as $secret) {
                foreach (['community_ro'] as $key) {
                    if (!empty($secret[$key])) {
                        $value = (string)$secret[$key];
                        if ($value !== '') $communities[] = $value;
                    }
                }
            }
        } catch (Throwable $e) {
            $data['rows'] = [];
            $error = 'Nie można bezpiecznie odczytać konfiguracji SNMP.';
        }
        // Additional OLT addresses verified against its SNMP IP address table.
        require_once __DIR__ . '/../lib/HuaweiDemoTrapSettings.php';
        $trapSources = HuaweiDemoTrapSettings::loadAll($DB);
        $oltIdsByAddress = [];
        $knownOltIds = [];
        foreach ($DB->GetAll('SELECT id, host FROM huawei_gpon_demo_olts') ?: [] as $oltRow) {
            $knownOltIds[(int)$oltRow['id']] = true;
            $oltIdsByAddress[$oltRow['host']][(int)$oltRow['id']] = true;
        }
        foreach ($trapSources as $oltId => $addresses) {
            foreach ($addresses as $address) {
                if (isset($knownOltIds[$oltId])) $oltIdsByAddress[$address][$oltId] = true;
            }
        }
        $ports = $DB->GetAll('SELECT r.id AS olt_id, r.host, p.ifindex, p.ifname FROM huawei_gpon_demo_ports p JOIN huawei_gpon_demo_olts r ON r.id = p.olt_id') ?: [];
        $portNames = [];
        foreach ($ports as $port) {
            $portNames[$port['olt_id'] . ':' . $port['ifindex']] = $port['ifname'];
        }
        foreach (array_slice($data['rows'], 0, 200) as $row) {
            if (!is_array($row)) continue;
            $item = [];
            foreach (['time', 'event', 'oid', 'olt', 'sender', 'ifindex', 'ont', 'uni_port', 'port_status', 'ont_status'] as $key) {
                $item[$key] = isset($row[$key]) && is_string($row[$key]) ? substr($row[$key], 0, 1200) : '';
                if ($communities) $item[$key] = str_replace($communities, '[UKRYTO]', $item[$key]);
            }
            try {
                $item['time'] = (new DateTimeImmutable($item['time']))->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('Y-m-d H:i:s');
            } catch (Exception $e) { $item['time'] = '—'; }
            $oltCandidates = ($oltIdsByAddress[$item['olt']] ?? []) + ($oltIdsByAddress[$item['sender']] ?? []);
            $item['olt_id'] = count($oltCandidates) === 1 ? (int)array_key_first($oltCandidates) : 0;
            $item['gpon'] = $portNames[$item['olt_id'] . ':' . $item['ifindex']] ?? ($item['ifindex'] !== '' ? 'ifIndex ' . $item['ifindex'] : '—');
            $item = hdDescribeHuaweiTrap($item);
            // Redact again after dictionary formatting; secrets never enter template variables.
            if ($communities) $item['event'] = str_replace($communities, '[UKRYTO]', $item['event']);
            $total++;
            if ($scope !== 'all' && $item['scope'] !== $scope) continue;
            if ($kind !== 'all' && $item['kind'] !== $kind) continue;
            $rows[] = $item;
        }
        $updated = is_string($data['updated_at'] ?? null) ? $data['updated_at'] : '';
        if (filemtime($path) < time() - 180) $error = 'Aktualizacja alertów jest opóźniona. Wyświetlane są ostatnio pobrane dane.';
    }
}
$SMARTY->assign('huawei_alert_rows', $rows);
$SMARTY->assign('huawei_alert_error', $error);
$SMARTY->assign('huawei_alert_updated', $updated);
$SMARTY->assign('huawei_alert_scopes', $scopes);
$SMARTY->assign('huawei_alert_kinds', $kinds);
$SMARTY->assign('huawei_alert_scope', $scope);
$SMARTY->assign('huawei_alert_kind', $kind);
$SMARTY->assign('huawei_alert_total', $total);
$SMARTY->assign('huawei_alert_count', count($rows));
$SMARTY->display('huaweigpondemo/alerts.html');
