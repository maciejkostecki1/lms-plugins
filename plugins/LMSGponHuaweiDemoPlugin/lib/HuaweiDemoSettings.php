<?php
declare(strict_types=1);

final class HuaweiDemoSettings
{
    public static function defaults(): array
    {
        return [
            'optics.ont.min' => -27,
            'optics.ont.good_min' => -24,
            'optics.ont.good_max' => -12,
            'optics.ont.max' => -9,

            'optics.olt.min' => -32,
            'optics.olt.good_min' => -28,
            'optics.olt.good_max' => -15,
            'optics.olt.max' => -12,
        ];
    }

    public static function load($DB, int $oltId): array
    {
        $values = self::defaults();

        $rows = $DB->GetAll(
            'SELECT setting_key, setting_value
             FROM huawei_gpon_demo_settings
             WHERE olt_id = ?',
            [$oltId]
        );

        foreach ($rows ?: [] as $row) {
            $key = (string)$row['setting_key'];

            if (array_key_exists($key, $values)
                && is_numeric($row['setting_value'])) {
                $values[$key] = (float)$row['setting_value'];
            }
        }

        return $values;
    }

    public static function validate(array $values): void
    {
        foreach (['ont', 'olt'] as $type) {
            $prefix = 'optics.' . $type . '.';

            $min = $values[$prefix . 'min'];
            $goodMin = $values[$prefix . 'good_min'];
            $goodMax = $values[$prefix . 'good_max'];
            $max = $values[$prefix . 'max'];

            if (!($min < $goodMin
                && $goodMin < $goodMax
                && $goodMax < $max)) {
                throw new RuntimeException(
                    'Nieprawidłowa kolejność progów ' .
                    strtoupper($type) . ' RX.'
                );
            }

            if ($min < -50 || $max > 10) {
                throw new RuntimeException(
                    'Progi muszą mieścić się w zakresie -50 do +10 dBm.'
                );
            }
        }
    }
}
