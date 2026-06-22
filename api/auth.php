<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

startSession();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'check') {
    jsonResponse([
        'ok' => true,
        'authenticated' => isAuthenticated(),
    ]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = $input['action'] ?? $action;

    if ($action === 'login') {
        $password = (string) ($input['password'] ?? '');

        if ($password === '' || !password_verify($password, ADMIN_PASSWORD_HASH)) {
            jsonResponse(['ok' => false, 'error' => 'Clave incorrecta'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['login_at'] = nowIso();

        jsonResponse(['ok' => true, 'message' => 'Sesión iniciada']);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
        }
        session_destroy();

        jsonResponse(['ok' => true, 'message' => 'Sesión cerrada']);
    }
}

jsonResponse(['ok' => false, 'error' => 'Acción no válida'], 400);
