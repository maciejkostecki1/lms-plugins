<?php
// Verified definitions: HUAWEI-XPON-TRAP-MIB and HUAWEI-BULKSTATISTICS-MIB.
// https://mibs.observium.org/mib/HUAWEI-XPON-TRAP-MIB/
// https://mibs.observium.org/mib/HUAWEI-BULKSTATISTICS-MIB/
// Entries: Polish label, target, event type. Unlisted OIDs remain unknown.
function hdHuaweiTrapDictionary(): array
{
    $base = '1.3.6.1.4.1.2011.6.';
    $entries = [
        '128.1.1.12.3.0.2' => ['Wykryto niezarejestrowany ONT GPON', 'ont', 'notification'],
        '128.1.1.12.3.0.3' => ['ONT GPON połączył się z OLT', 'ont', 'notification'],
        '128.1.1.12.3.0.4' => ['Zmiana stanu wykrywania ONT GPON', 'ont', 'notification'],
        '128.1.1.12.3.0.50' => ['Wykryto ONT EPON', 'ont', 'notification'],
        '128.1.1.12.3.0.51' => ['Zmiana stanu portu ONT', 'ont', 'notification'],
        '128.1.1.12.3.0.52' => ['ONT EPON połączył się z OLT', 'ont', 'notification'],
        '128.1.1.12.3.0.100' => ['Zmiana stanu ONT', 'ont', 'notification'],
        '128.1.1.12.3.0.101' => ['Operacja wgrywania pliku do ONT', 'ont', 'notification'],
        '128.1.1.12.3.0.102' => ['Żądanie przesłania pliku ONT', 'ont', 'notification'],
        '128.1.1.12.3.0.115' => ['Wykryto VLAN/MAC bramy domowej na porcie ONT', 'ont', 'notification'],
        '128.1.1.12.2.2.0.4' => ['Utrata synchronizacji kanału GEM ONT (LCDGi)', 'ont', 'alarm'],
        '128.1.1.12.2.2.0.18' => ['Utrata zasilania ONT (Dying Gasp)', 'ont', 'alarm'],
        '128.1.1.12.2.2.0.19' => ['Ustąpienie alarmu zasilania ONT (Dying Gasp)', 'ont', 'recovery'],
        '128.1.1.12.2.1.0.121' => ['Jednoczesna utrata wielu ONT na porcie PON', 'olt', 'alarm'],
        '128.1.1.12.2.1.0.122' => ['Ustąpienie zbiorczej utraty ONT na porcie PON', 'olt', 'recovery'],
        '145.3.1.0.3' => ['Zmiana stanu portu PON w OLT', 'olt', 'notification'],
        '145.3.1.0.4' => ['Zmiana stanu konfiguracji ONT', 'ont', 'notification'],
        '145.3.1.0.6' => ['Zmiana trybu konfiguracji XPON', 'olt', 'notification'],
        '145.3.1.0.7' => ['Zmiana profilu SNMP', 'olt', 'notification'],
        '145.3.1.0.8' => ['Zmiana konfiguracji portu PON', 'olt', 'notification'],
        '145.3.1.0.20' => ['Pierwsze połączenie ONT', 'ont', 'notification'],
        '103.1.7.1.0.1' => ['Błąd utworzenia pliku statystyk OLT', 'olt', 'alarm'],
        '103.1.7.2.0.1' => ['Utworzono plik statystyk OLT', 'olt', 'notification'],
        '103.1.7.2.0.2' => ['Wynik przesyłania pliku statystyk OLT', 'olt', 'notification'],
        '103.1.7.2.0.3' => ['Automatyczna kopia statystyk OLT zakończona', 'olt', 'notification'],
        '103.1.7.2.0.4' => ['Błąd automatycznej kopii statystyk OLT', 'olt', 'alarm'],
    ];
    $result = [];
    foreach ($entries as $suffix => $entry) $result[$base . $suffix] = $entry;
    return $result;
}

function hdDescribeHuaweiTrap(array $row): array
{
    static $names;
    if ($names === null) $names = hdHuaweiTrapDictionary();
    $oid = $row['oid'];
    $definition = $names[$oid] ?? null;
    $row['scope'] = $definition[1] ?? ($row['ont'] !== '' ? 'ont' : 'unknown');
    $row['kind'] = $definition[2] ?? 'unknown';
    if ($definition) $row['event'] = $definition[0];
    if ($oid === '1.3.6.1.4.1.2011.6.128.1.1.12.3.0.51') {
        if ($row['uni_port'] !== '') $row['event'] .= ' — port ' . $row['uni_port'];
        if ($row['port_status'] !== '') {
            $row['event'] .= ': ' . (['1' => 'aktywny', '2' => 'nieaktywny'][$row['port_status']] ?? 'stan ' . $row['port_status']);
        }
    }
    if ($oid === '1.3.6.1.4.1.2011.6.128.1.1.12.3.0.100' && $row['ont_status'] !== '') {
        $row['event'] .= ' — ' . (['1' => 'online', '2' => 'offline'][$row['ont_status']] ?? 'stan ' . $row['ont_status']);
    }
    $row['scope_label'] = ['ont' => 'ONT', 'olt' => 'OLT', 'unknown' => 'Nierozpoznane'][$row['scope']];
    $row['kind_label'] = ['alarm' => 'Alarm', 'recovery' => 'Ustąpienie alarmu', 'notification' => 'Powiadomienie', 'unknown' => 'Nierozpoznane'][$row['kind']];
    return $row;
}
