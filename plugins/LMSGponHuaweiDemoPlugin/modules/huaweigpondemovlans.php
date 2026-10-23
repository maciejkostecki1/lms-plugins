<?php
declare(strict_types=1);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); header('Allow: GET'); exit('Demo RO: operacja niedostępna.'); }

if (!isset($DB, $SMARTY, $AUTH) || empty($AUTH->islogged)) { http_response_code(403); exit; }


// HG-VLAN-LIST-V1

$layout['pagetitle'] = 'Huawei GPON — Lista VLAN';

require_once __DIR__ . '/../lib/HuaweiVlanSnmp.php';

$error = '';
$vlans = [];

$olts = $DB->GetAll(
    'SELECT id, name, host, enabled
     FROM huawei_gpon_demo_olts
     ORDER BY id'
) ?: [];

$oltId = 1;

if (isset($_GET['olt_id'])) {
    $requested = (string)$_GET['olt_id'];

    if (
        !ctype_digit($requested) ||
        (int)$requested < 1
    ) {
        $error = 'Nieprawidłowy identyfikator OLT.';
        $oltId = 0;
    } else {
        $oltId = (int)$requested;
    }
}

$selectedOlt = null;

foreach ($olts as $olt) {
    if ((int)$olt['id'] === $oltId) {
        $selectedOlt = $olt;
        break;
    }
}

if ($error === '' && !$selectedOlt) {
    $error = 'OLT ID ' . $oltId . ' nie istnieje.';
}

if ($error === '' && $selectedOlt) {
    try {
        $vlans = hdVlanReadList($DB, $oltId);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$SMARTY->assign('hd_vlan_olts', $olts);
$SMARTY->assign('hd_vlan_olt_id', $oltId);
$SMARTY->assign('hd_vlan_selected_olt', $selectedOlt);
$SMARTY->assign('hd_vlan_rows', $vlans);
$SMARTY->assign('hd_vlan_count', count($vlans));
$SMARTY->assign('hd_vlan_error', $error);

$SMARTY->display('huaweigpondemo/vlans.html');
