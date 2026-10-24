<?php

declare(strict_types=1);

date_default_timezone_set('UTC');
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_name('VOLUNTEERHUBSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; style-src 'self'; script-src 'self'");
    header('Referrer-Policy: same-origin');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-VolunteerHub-Environment: ' . (getenv('APP_ENV') === 'test' ? 'test' : 'application'));
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/HttpException.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/DashboardRepository.php';
require_once __DIR__ . '/EventRepository.php';
require_once __DIR__ . '/VolunteerRepository.php';
require_once __DIR__ . '/Allocation/Snapshot.php';
require_once __DIR__ . '/Allocation/EligibilityChecker.php';
require_once __DIR__ . '/Allocation/ScheduleValidator.php';
require_once __DIR__ . '/Allocation/Metrics.php';
require_once __DIR__ . '/Allocation/RegistrationOrderAllocator.php';
require_once __DIR__ . '/Allocation/ScarcityBalancedAllocator.php';
require_once __DIR__ . '/Allocation/ComparisonService.php';
require_once __DIR__ . '/Allocation/SnapshotBuilder.php';
require_once __DIR__ . '/Allocation/AllocationRepository.php';
require_once __DIR__ . '/AssignmentRepository.php';
require_once __DIR__ . '/ReportRepository.php';
require_once __DIR__ . '/CsvValue.php';
require_once __DIR__ . '/Http/ActionController.php';

function config_path(): string
{
    $override = getenv('APP_DB_CONFIG');
    return is_string($override) && $override !== '' ? $override : dirname(__DIR__) . '/.local/db.json';
}

function db(): mysqli
{
    static $connection;
    if (!$connection instanceof mysqli) {
        $connection = (new Database(config_path()))->connection();
    }
    return $connection;
}

function auth(): Auth
{
    static $auth;
    if (!$auth instanceof Auth) {
        $auth = new Auth(db());
    }
    return $auth;
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function route_url(string $page = 'dashboard', array $params = []): string
{
    return '/?' . http_build_query(['page' => $page] + $params);
}

function app_version(): string
{
    static $version;
    if (!is_string($version)) {
        $path = dirname(__DIR__, 2) . '/VERSION';
        $version = is_file($path) ? trim((string) file_get_contents($path)) : 'development';
    }
    return $version;
}

function redirect_to(string $page, array $params = []): never
{
    header('Location: ' . route_url($page, $params), true, 303);
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        throw new HttpException(419, 'Phiên biểu mẫu không hợp lệ. Vui lòng tải lại trang và thử lại.');
    }
}

function set_flash(string $type, string $message, array $old = []): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message, 'old' => $old];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function format_local_datetime(?string $utc): string
{
    if (!$utc) {
        return '';
    }
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone(Validator::DISPLAY_TIMEZONE))
        ->format('H:i, d/m/Y');
}

function format_local_input(?string $utc): string
{
    if (!$utc) {
        return '';
    }
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone(Validator::DISPLAY_TIMEZONE))
        ->format('Y-m-d\TH:i');
}

function selected_skill_ids(mixed $input): array
{
    if (!is_array($input)) {
        return [];
    }
    $ids = [];
    foreach ($input as $value) {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id !== false) {
            $ids[(int) $id] = (int) $id;
        }
    }
    return array_values($ids);
}
