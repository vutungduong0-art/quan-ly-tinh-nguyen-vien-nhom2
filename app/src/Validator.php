<?php

declare(strict_types=1);

final class Validator
{
    public const DISPLAY_TIMEZONE = 'Asia/Ho_Chi_Minh';

    public static function requiredText(mixed $value, string $label, int $maxLength, array &$errors): string
    {
        $text = trim(is_string($value) ? $value : '');
        if ($text === '') {
            $errors[] = "{$label} không được để trống.";
        } elseif ((function_exists('mb_strlen') ? mb_strlen($text) : strlen($text)) > $maxLength) {
            $errors[] = "{$label} không được dài quá {$maxLength} ký tự.";
        }
        return $text;
    }

    public static function optionalText(mixed $value, string $label, int $maxLength, array &$errors): string
    {
        $text = trim(is_string($value) ? $value : '');
        if ((function_exists('mb_strlen') ? mb_strlen($text) : strlen($text)) > $maxLength) {
            $errors[] = "{$label} không được dài quá {$maxLength} ký tự.";
        }
        return $text;
    }

    public static function dateTimeToUtc(mixed $value, string $label, array &$errors): ?string
    {
        $text = trim(is_string($value) ? $value : '');
        if ($text === '') {
            $errors[] = "{$label} không được để trống.";
            return null;
        }

        $timezone = new DateTimeZone(self::DISPLAY_TIMEZONE);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $text, $timezone);
        $state = DateTimeImmutable::getLastErrors();
        if (!$date || ($state !== false && ($state['warning_count'] > 0 || $state['error_count'] > 0))) {
            $errors[] = "{$label} không đúng định dạng ngày giờ.";
            return null;
        }

        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function positiveInteger(mixed $value, string $label, array &$errors): ?int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validated === false) {
            $errors[] = "{$label} phải là số nguyên dương.";
            return null;
        }
        return (int) $validated;
    }

    public static function orderedRange(?string $start, ?string $end, string $label, array &$errors): void
    {
        if ($start !== null && $end !== null && $start >= $end) {
            $errors[] = "Thời điểm bắt đầu {$label} phải nhỏ hơn thời điểm kết thúc.";
        }
    }
}
