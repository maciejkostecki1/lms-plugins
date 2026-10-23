<?php
declare(strict_types=1);
// HG-SNMP-DB-RO-V1


// HG-SNMP-OLT-ID-V1
// Wspólna konfiguracja SNMP tylko do odczytu.
// Adres urządzenia pochodzi wyłącznie z PostgreSQL.

function hdSnmpAccessForOlt($DB, int $oltId): array
{
    if ($oltId < 1) {
        throw new RuntimeException('Nieprawidłowe ID OLT.');
    }

    $olt = $DB->GetRow(
        'SELECT id, host, enabled, snmp_timeout, snmp_retries
         FROM huawei_gpon_demo_olts
         WHERE id = ?',
        [$oltId]
    );

    if (!$olt || !in_array(
        $olt['enabled'],
        [true, 1, '1', 't', 'true'],
        true
    )) {
        throw new RuntimeException(
            'OLT nie istnieje lub jest nieaktywny.'
        );
    }

    $host = (string)$olt['host'];

    if (!filter_var(
        $host,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_IPV4
    )) {
        throw new RuntimeException('Nieprawidłowy IPv4 OLT.');
    }

    // Community RO przypisane do konkretnego OLT.
    $secret = $DB->GetOne(
        'SELECT community_ro
         FROM huawei_gpon_demo_snmp_secrets
         WHERE olt_id = ?',
        [$oltId]
    );

    if (!is_string($secret) || $secret === '') {
        throw new RuntimeException(
            'Brak community RO dla OLT #' . $oltId
        );
    }

    $community = $secret;

    if ($community === '') {
        throw new RuntimeException('Puste community RO.');
    }

    return [
        'olt_id' => $oltId,
        'host' => $host,
        'community' => $community,
        'timeout' => (int)$olt['snmp_timeout'],
        'retries' => (int)$olt['snmp_retries'],
    ];
}
