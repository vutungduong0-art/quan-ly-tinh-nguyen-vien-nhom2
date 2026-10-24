<?php

declare(strict_types=1);

final class Snapshot
{
    public static function canonicalize(array $snapshot): array
    {
        self::sortRecursive($snapshot);
        return $snapshot;
    }

    public static function encode(array $snapshot): string
    {
        $json = json_encode(
            self::canonicalize($snapshot),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
        );
        return $json . PHP_EOL;
    }

    public static function hash(array $snapshot): string
    {
        return hash('sha256', self::encode($snapshot));
    }

    public static function volunteersById(array $snapshot): array
    {
        $result = [];
        foreach ($snapshot['volunteers'] ?? [] as $volunteer) {
            $result[(int) $volunteer['id']] = $volunteer;
        }
        return $result;
    }

    public static function shiftsById(array $snapshot): array
    {
        $result = [];
        foreach ($snapshot['shifts'] ?? [] as $shift) {
            $result[(int) $shift['id']] = $shift;
        }
        return $result;
    }

    private static function sortRecursive(array &$value): void
    {
        if (array_is_list($value)) {
            foreach ($value as &$item) {
                if (is_array($item)) {
                    self::sortRecursive($item);
                }
            }
            unset($item);
            usort($value, static function (mixed $a, mixed $b): int {
                if (is_array($a) && is_array($b)) {
                    $aKey = $a['id'] ?? $a['code'] ?? json_encode($a);
                    $bKey = $b['id'] ?? $b['code'] ?? json_encode($b);
                    return ($aKey <=> $bKey);
                }
                return ($a <=> $b);
            });
            return;
        }

        ksort($value, SORT_STRING);
        foreach ($value as &$item) {
            if (is_array($item)) {
                self::sortRecursive($item);
            }
        }
        unset($item);
    }
}

