<?php

declare(strict_types=1);

final class CsvValue
{
    public static function safe(mixed $value): string
    {
        $text = (string) $value;
        return preg_match('/^[=+\-@]/u', $text) === 1 ? "'" . $text : $text;
    }
}
