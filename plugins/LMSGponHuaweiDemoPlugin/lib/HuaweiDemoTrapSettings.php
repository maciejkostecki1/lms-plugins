<?php
declare(strict_types=1);

final class HuaweiDemoTrapSettings
{
    public const KEY = 'trap.source_addresses';

    public static function parse($raw): array
    {
        if (!is_string($raw) || strlen($raw) > 1024) {
            throw new RuntimeException('Nieprawidłowa lista adresów trapów.');
        }
        $addresses = preg_split('/[\s,;]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
        if (count($addresses) > 16) throw new RuntimeException('Można podać najwyżej 16 adresów IPv4.');
        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw new RuntimeException('Podaj poprawne adresy IPv4, po jednym w wierszu.');
            }
        }
        return array_values(array_unique($addresses));
    }

    public static function loadAll($DB): array
    {
        $result = [];
        foreach ($DB->GetAll('SELECT olt_id, setting_value FROM huawei_gpon_demo_settings WHERE setting_key = ?', [self::KEY]) ?: [] as $row) {
            $decoded = json_decode((string)$row['setting_value'], true);
            if (!is_array($decoded) || count($decoded) > 16) continue;
            $valid = [];
            foreach ($decoded as $address) {
                if (is_string($address) && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $valid[] = $address;
            }
            $result[(int)$row['olt_id']] = array_values(array_unique($valid));
        }
        return $result;
    }

    public static function status($DB, int $oltId): array
    {
        $result = ['state' => 'Brak danych eksportu', 'updated' => '—', 'last' => 'Brak w ostatnich 200 wpisach', 'count' => 0];
        $path = '/var/lib/lms-huawei-alerts/alerts.json';
        if (!is_readable($path) || filesize($path) > 2097152) return $result;
        $data = json_decode((string)file_get_contents($path), true, 16);
        if (!is_array($data) || !is_array($data['rows'] ?? null)) return $result;
        $format = static function ($value): string {
            if (!is_string($value) || $value === '') return '—';
            try { return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('Y-m-d H:i:s'); }
            catch (Exception $e) { return '—'; }
        };
        $result['updated'] = $format($data['updated_at'] ?? '');
        $result['state'] = filemtime($path) >= time() - 180 ? 'Eksport logów działa' : 'Eksport logów jest opóźniony';
        $idsByAddress = [];
        foreach ($DB->GetAll('SELECT id, host FROM huawei_gpon_demo_olts') ?: [] as $olt) $idsByAddress[$olt['host']][(int)$olt['id']] = true;
        foreach (self::loadAll($DB) as $id => $addresses) foreach ($addresses as $address) $idsByAddress[$address][$id] = true;
        foreach (array_slice($data['rows'], 0, 200) as $row) {
            if (!is_array($row)) continue;
            $source = is_string($row['olt'] ?? null) ? $row['olt'] : '';
            $sender = is_string($row['sender'] ?? null) ? $row['sender'] : '';
            $matches = ($idsByAddress[$source] ?? []) + ($idsByAddress[$sender] ?? []);
            if (count($matches) !== 1 || !isset($matches[$oltId])) continue;
            if ($result['count'] === 0) $result['last'] = $format($row['time'] ?? '');
            $result['count']++;
        }
        return $result;
    }
}
