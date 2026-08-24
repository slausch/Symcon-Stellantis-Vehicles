<?php

declare(strict_types=1);

final class VehicleStatusMapper
{
    /** @param array<string, mixed> $status
     *  @return array<string, mixed>
     */
    public static function map(array $status): array
    {
        $electric = self::findEnergy($status, 'Electric');
        $charging = self::path($electric, ['extension', 'electric', 'charging'], []);
        if (!is_array($charging)) {
            $charging = [];
        }

        $preconditioning = self::path($status, ['preconditioning', 'airConditioning'], null);
        if (!is_array($preconditioning)) {
            $preconditioning = self::path($status, ['preconditionning', 'airConditioning'], []);
        }

        $coordinates = self::path($status, ['lastPosition', 'geometry', 'coordinates'], []);
        $doorsState = self::path($status, ['doorsState', 'lockedStates'], null);
        $remaining = self::durationToMinutes($charging['remainingTime'] ?? null);

        $updatedAt = $status['updatedAt'] ?? self::path($electric, ['updatedAt'], null);
        $updatedTimestamp = self::timestamp($updatedAt);

        return [
            'SOC' => self::number($electric['level'] ?? null),
            'Range' => self::number($electric['autonomy'] ?? null),
            'ChargingPlugged' => self::boolean($charging['plugged'] ?? null),
            'Charging' => ($charging['status'] ?? null) === 'InProgress',
            'ChargingStatus' => isset($charging['status']) ? (string) $charging['status'] : null,
            'ChargingMode' => isset($charging['chargingMode']) ? (string) $charging['chargingMode'] : null,
            'ChargingRemainingMinutes' => $remaining,
            'Mileage' => self::number(self::path($status, ['odometer', 'mileage'], null)),
            'AmbientTemperature' => self::number(self::path($status, ['environment', 'air', 'temp'], null)),
            'Preconditioning' => ($preconditioning['status'] ?? null) === 'Enabled',
            'DoorsLocked' => self::doorsLocked($doorsState),
            'Latitude' => is_array($coordinates) && isset($coordinates[1]) ? self::number($coordinates[1]) : null,
            'Longitude' => is_array($coordinates) && isset($coordinates[0]) ? self::number($coordinates[0]) : null,
            'LastDataAt' => $updatedTimestamp,
            'DataAge' => $updatedTimestamp === null ? null : max(0, time() - $updatedTimestamp)
        ];
    }

    /** @param array<string, mixed> $status
     *  @return array<string, mixed>
     */
    private static function findEnergy(array $status, string $type): array
    {
        $energies = $status['energies'] ?? [];
        if (!is_array($energies)) {
            return [];
        }

        foreach ($energies as $energy) {
            if (is_array($energy) && ($energy['type'] ?? null) === $type) {
                return $energy;
            }
        }
        return [];
    }

    /** @param array<string, mixed> $data
     *  @param array<int, string|int> $path
     */
    private static function path(array $data, array $path, mixed $default): mixed
    {
        $value = $data;
        foreach ($path as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    private static function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private static function boolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }
        return null;
    }

    private static function doorsLocked(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            if (strcasecmp($value, 'Locked') === 0) {
                return true;
            }
            if (strcasecmp($value, 'Unlocked') === 0) {
                return false;
            }
        }
        return null;
    }

    private static function durationToMinutes(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return max(0, (int) round(((float) $value) / 60));
        }
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            $interval = new DateInterval($value);
            return ($interval->d * 1440) + ($interval->h * 60) + $interval->i + ($interval->s >= 30 ? 1 : 0);
        } catch (Exception) {
            return null;
        }
    }

    private static function timestamp(mixed $value): ?int
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }
}
