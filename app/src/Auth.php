<?php

declare(strict_types=1);

final class Auth
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const ATTEMPT_WINDOW_SECONDS = 900;
    private const IDLE_TIMEOUT_SECONDS = 1800;

    public function __construct(private readonly mysqli $db)
    {
    }

    public function attempt(string $username, string $password): bool
    {
        $identityHash = hash('sha256', strtolower(trim($username)));
        $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'local'));
        $this->purgeOldAttempts();
        if ($this->failedAttemptCount($identityHash, $ipHash) >= self::MAX_FAILED_ATTEMPTS) {
            throw new HttpException(429, 'Đăng nhập tạm khóa do có quá nhiều lần thử sai. Vui lòng thử lại sau 15 phút.');
        }

        $statement = $this->db->prepare(
            'SELECT id, username, password_hash, full_name, role, active FROM users WHERE username = ? LIMIT 1'
        );
        $statement->bind_param('s', $username);
        $statement->execute();
        $user = $statement->get_result()->fetch_assoc();

        if (!$user || (int) $user['active'] !== 1 || !password_verify($password, $user['password_hash'])) {
            $this->recordFailedAttempt($identityHash, $ipHash);
            return false;
        }

        $clear = $this->db->prepare('DELETE FROM login_attempts WHERE identity_hash = ? AND ip_hash = ?');
        $clear->bind_param('ss', $identityHash, $ipHash);
        $clear->execute();
        session_regenerate_id(true);
        unset($_SESSION['csrf_token']);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
        ];
        $_SESSION['last_activity_at'] = time();
        return true;
    }

    public function user(): ?array
    {
        $user = $_SESSION['user'] ?? null;
        if (!is_array($user) || !isset($user['id'])) {
            return null;
        }
        $lastActivity = (int) ($_SESSION['last_activity_at'] ?? time());
        if (time() - $lastActivity > self::IDLE_TIMEOUT_SECONDS) {
            unset($_SESSION['user'], $_SESSION['last_activity_at'], $_SESSION['csrf_token']);
            return null;
        }

        $statement = $this->db->prepare(
            'SELECT id, username, full_name, role, active FROM users WHERE id = ? LIMIT 1'
        );
        $id = (int) $user['id'];
        $statement->bind_param('i', $id);
        $statement->execute();
        $fresh = $statement->get_result()->fetch_assoc();
        if (!$fresh || (int) $fresh['active'] !== 1) {
            unset($_SESSION['user'], $_SESSION['last_activity_at'], $_SESSION['csrf_token']);
            return null;
        }
        $_SESSION['user'] = [
            'id' => (int) $fresh['id'],
            'username' => $fresh['username'],
            'full_name' => $fresh['full_name'],
            'role' => $fresh['role'],
        ];
        $_SESSION['last_activity_at'] = time();
        return $_SESSION['user'];
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public function requireLogin(): array
    {
        $user = $this->user();
        if ($user === null) {
            throw new HttpException(401, 'Bạn cần đăng nhập để thực hiện thao tác này.');
        }
        return $user;
    }

    public function requireRole(string $role): array
    {
        $user = $this->requireLogin();
        if (($user['role'] ?? '') !== $role) {
            throw new HttpException(403, 'Bạn không có quyền thực hiện thao tác này.');
        }
        return $user;
    }

    private function purgeOldAttempts(): void
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::ATTEMPT_WINDOW_SECONDS);
        $statement = $this->db->prepare('DELETE FROM login_attempts WHERE attempted_at < ?');
        $statement->bind_param('s', $cutoff);
        $statement->execute();
    }

    private function failedAttemptCount(string $identityHash, string $ipHash): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::ATTEMPT_WINDOW_SECONDS);
        $statement = $this->db->prepare(
            'SELECT COUNT(*) AS total FROM login_attempts WHERE identity_hash = ? AND ip_hash = ? AND attempted_at >= ?'
        );
        $statement->bind_param('sss', $identityHash, $ipHash, $cutoff);
        $statement->execute();
        return (int) $statement->get_result()->fetch_assoc()['total'];
    }

    private function recordFailedAttempt(string $identityHash, string $ipHash): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO login_attempts (identity_hash, ip_hash, attempted_at) VALUES (?, ?, UTC_TIMESTAMP())'
        );
        $statement->bind_param('ss', $identityHash, $ipHash);
        $statement->execute();
    }
}
