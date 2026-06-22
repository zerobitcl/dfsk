<?php
declare(strict_types=1);

const APP_TIMEZONE = 'America/Santiago';
const SESSION_NAME = 'dfsk_crm_session';
const SESSION_LIFETIME = 86400; // 24 horas

// Cambiar en producción: genera un hash con password_hash('tu_clave', PASSWORD_DEFAULT)
const ADMIN_PASSWORD_HASH = '$2y$12$h0K41.ide6R06TOIxEJrjunom/iKR6.hdlxHVgO2T0l0X4EIHDMvC'; // admin123

date_default_timezone_set(APP_TIMEZONE);

$dataDir = dirname(__DIR__) . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

define('DB_PATH', $dataDir . '/leads.db');

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function isAuthenticated(): bool
{
    startSession();
    return !empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function requireAuth(): void
{
    if (!isAuthenticated()) {
        jsonResponse(['ok' => false, 'error' => 'No autorizado'], 401);
    }
}

function sanitizeText(?string $value, int $max = 500): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim(strip_tags($value));
    if ($value === '') {
        return null;
    }

    return mb_substr($value, 0, $max);
}

function nowIso(): string
{
    return date('Y-m-d H:i:s');
}
