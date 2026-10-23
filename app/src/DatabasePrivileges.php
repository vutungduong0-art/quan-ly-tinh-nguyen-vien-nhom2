<?php

declare(strict_types=1);

final class DatabasePrivileges
{
    private const REQUIRED = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];
    private const FORBIDDEN = ['CREATE', 'ALTER', 'DROP', 'INDEX', 'TRIGGER', 'EVENT', 'EXECUTE', 'FILE', 'PROCESS', 'SUPER'];

    public static function inspect(mysqli $db): array
    {
        $rows = $db->query('SHOW GRANTS FOR CURRENT_USER()')->fetch_all(MYSQLI_NUM);
        $text = strtoupper(implode("\n", array_map(static fn (array $row): string => (string) ($row[0] ?? ''), $rows)));
        $missing = array_values(array_filter(self::REQUIRED, static fn (string $privilege): bool => !str_contains($text, $privilege)));
        $excessive = str_contains($text, 'ALL PRIVILEGES');
        foreach (self::FORBIDDEN as $privilege) {
            if (preg_match('/(?:^|[ ,])' . preg_quote($privilege, '/') . '(?:[ ,]|$)/', $text)) {
                $excessive = true;
            }
        }
        return ['missing' => $missing, 'excessive' => $excessive, 'grants' => $rows];
    }
}
