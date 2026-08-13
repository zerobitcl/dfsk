<?php
declare(strict_types=1);

const APP_TIMEZONE = 'America/Santiago';
const SESSION_NAME = 'dfsk_crm_session';
const SESSION_LIFETIME = 86400; // 24 horas

// Cambiar en producción: genera un hash con password_hash('tu_clave', PASSWORD_DEFAULT)
const ADMIN_PASSWORD_HASH = '$2y$12$h0K41.ide6R06TOIxEJrjunom/iKR6.hdlxHVgO2T0l0X4EIHDMvC'; // admin123

// Mismo ID que js/site-config.js → pixelId. El token solo aplica a Conversions API (servidor).
const META_PIXEL_ID = '1508041677677153';
const META_CAPI_ACCESS_TOKEN = '';

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

function metaNormalizePhone(?string $telefono): ?string
{
    if ($telefono === null) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $telefono) ?? '';
    if ($digits === '') {
        return null;
    }

    if (str_starts_with($digits, '569') && strlen($digits) >= 11) {
        return $digits;
    }
    if (str_starts_with($digits, '09') && strlen($digits) === 10) {
        return '56' . substr($digits, 1);
    }
    if (str_starts_with($digits, '9') && strlen($digits) === 9) {
        return '56' . $digits;
    }

    return $digits;
}

function sendMetaCapiLead(?string $telefono, ?string $eventId, ?string $sourceUrl, ?string $modelo): void
{
    if (META_PIXEL_ID === '' || META_CAPI_ACCESS_TOKEN === '') {
        return;
    }

    $phone = metaNormalizePhone($telefono);
    $userData = [
        'client_user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
    ];

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (is_string($ip) && $ip !== '') {
        $userData['client_ip_address'] = trim(explode(',', $ip)[0]);
    }
    if ($phone) {
        $userData['ph'] = [hash('sha256', $phone)];
    }

    $payload = [
        'access_token' => META_CAPI_ACCESS_TOKEN,
        'data' => [[
            'event_name' => 'Lead',
            'event_time' => time(),
            'event_id' => $eventId ?: ('lead_' . bin2hex(random_bytes(8))),
            'action_source' => 'website',
            'event_source_url' => $sourceUrl ?: (windowOriginFallback()),
            'user_data' => $userData,
            'custom_data' => [
                'content_name' => $modelo ?: 'DFSK',
                'content_category' => 'Camioneta DFSK',
            ],
        ]],
    ];

    $url = 'https://graph.facebook.com/v21.0/' . rawurlencode(META_PIXEL_ID) . '/events';
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
        ]);
        curl_exec($ch);
        curl_close($ch);
        return;
    }

    @file_get_contents($url, false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $body,
            'timeout' => 4,
            'ignore_errors' => true,
        ],
    ]));
}

function windowOriginFallback(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $host = $_SERVER['HTTP_HOST'] ?? 'www.felipecallegari.cl';
    return ($https ? 'https://' : 'http://') . $host . ($_SERVER['REQUEST_URI'] ?? '/');
}
