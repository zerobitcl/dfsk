<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = getDb();

$allowedEstados = [
    'ingresado',
    'contactado',
    'en_veremos',
    'reunion_agendada',
    'cotizacion_enviada',
    'concreto',
    'perdido',
];

$allowedOrigenes = [
    'SEO_Organico',
    'Campana_MetaAds',
    'whatsapp',
    'manual',
];

$allowedFuentes = ['formulario', 'whatsapp', 'landing', 'manual'];

if ($method === 'GET') {
    requireAuth();

    $estado = sanitizeText($_GET['estado'] ?? null, 40);
    $origen = sanitizeText($_GET['origen'] ?? null, 40);
    $q = sanitizeText($_GET['q'] ?? null, 80);

    $sql = 'SELECT * FROM leads WHERE 1=1';
    $params = [];

    if ($estado && in_array($estado, $allowedEstados, true)) {
        $sql .= ' AND estado = :estado';
        $params['estado'] = $estado;
    }

    if ($origen && in_array($origen, $allowedOrigenes, true)) {
        $sql .= ' AND origen = :origen';
        $params['origen'] = $origen;
    }

    if ($q) {
        $sql .= ' AND (nombre LIKE :q OR telefono LIKE :q OR modelo LIKE :q OR notas LIKE :q OR origen LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }

    $sql .= ' ORDER BY datetime(created_at) DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $statsStmt = $pdo->query(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN estado = "ingresado" THEN 1 ELSE 0 END) AS ingresados,
            SUM(CASE WHEN estado = "contactado" THEN 1 ELSE 0 END) AS contactados,
            SUM(CASE WHEN estado = "cotizacion_enviada" THEN 1 ELSE 0 END) AS cotizaciones,
            SUM(CASE WHEN estado = "concreto" THEN 1 ELSE 0 END) AS concretos,
            SUM(CASE WHEN origen = "SEO_Organico" THEN 1 ELSE 0 END) AS organicos,
            SUM(CASE WHEN origen = "Campana_MetaAds" THEN 1 ELSE 0 END) AS pagados
         FROM leads'
    );
    $stats = $statsStmt->fetch() ?: [];

    $leads = array_map(function (array $row) {
        $lead = leadToArray($row);
        $lead['origen_label'] = origenLabel($lead['origen']);
        $lead['estado_label'] = estadoLabel($lead['estado']);
        return $lead;
    }, $rows);

    jsonResponse([
        'ok' => true,
        'leads' => $leads,
        'stats' => [
            'total' => (int) ($stats['total'] ?? 0),
            'ingresados' => (int) ($stats['ingresados'] ?? 0),
            'contactados' => (int) ($stats['contactados'] ?? 0),
            'cotizaciones' => (int) ($stats['cotizaciones'] ?? 0),
            'concretos' => (int) ($stats['concretos'] ?? 0),
            'organicos' => (int) ($stats['organicos'] ?? 0),
            'pagados' => (int) ($stats['pagados'] ?? 0),
        ],
    ]);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}

if ($method === 'POST') {
    $nombre = sanitizeText($input['nombre'] ?? null, 120);
    $telefono = sanitizeText($input['telefono'] ?? null, 40);
    $modelo = sanitizeText($input['modelo'] ?? null, 120);
    $pie = sanitizeText($input['pie'] ?? null, 120);
    $notas = sanitizeText($input['notas'] ?? null, 2000);
    $fuente = sanitizeText($input['fuente'] ?? 'formulario', 30) ?? 'formulario';
    $origen = sanitizeText($input['origen'] ?? $input['origen_lead'] ?? null, 40);

    if (!$nombre || !$telefono) {
        jsonResponse(['ok' => false, 'error' => 'Nombre y teléfono son obligatorios'], 422);
    }

    if (!in_array($fuente, $allowedFuentes, true)) {
        $fuente = 'formulario';
    }

    if (!$origen || !in_array($origen, $allowedOrigenes, true)) {
        $origen = match ($fuente) {
            'landing' => 'Campana_MetaAds',
            'whatsapp' => 'whatsapp',
            default => 'SEO_Organico',
        };
    }

    $estado = sanitizeText($input['estado'] ?? 'ingresado', 40) ?? 'ingresado';
    if (!in_array($estado, $allowedEstados, true)) {
        $estado = 'ingresado';
    }

    $now = nowIso();

    $stmt = $pdo->prepare(
        'INSERT INTO leads (nombre, telefono, modelo, pie, fuente, origen, estado, notas, created_at, updated_at)
         VALUES (:nombre, :telefono, :modelo, :pie, :fuente, :origen, :estado, :notas, :created_at, :updated_at)'
    );

    $stmt->execute([
        'nombre' => $nombre,
        'telefono' => $telefono,
        'modelo' => $modelo,
        'pie' => $pie,
        'fuente' => $fuente,
        'origen' => $origen,
        'estado' => $estado,
        'notas' => $notas,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    jsonResponse([
        'ok' => true,
        'message' => 'Lead registrado',
        'id' => (int) $pdo->lastInsertId(),
    ], 201);
}

if ($method === 'PUT' || $method === 'PATCH') {
    requireAuth();

    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['ok' => false, 'error' => 'ID inválido'], 422);
    }

    $existing = $pdo->prepare('SELECT * FROM leads WHERE id = :id');
    $existing->execute(['id' => $id]);
    $lead = $existing->fetch();

    if (!$lead) {
        jsonResponse(['ok' => false, 'error' => 'Lead no encontrado'], 404);
    }

    $nombre = array_key_exists('nombre', $input) ? sanitizeText($input['nombre'], 120) : $lead['nombre'];
    $telefono = array_key_exists('telefono', $input) ? sanitizeText($input['telefono'], 40) : $lead['telefono'];
    $modelo = array_key_exists('modelo', $input) ? sanitizeText($input['modelo'], 120) : $lead['modelo'];
    $pie = array_key_exists('pie', $input) ? sanitizeText($input['pie'], 120) : $lead['pie'];
    $notas = array_key_exists('notas', $input) ? sanitizeText($input['notas'], 2000) : $lead['notas'];
    $estado = array_key_exists('estado', $input) ? sanitizeText($input['estado'], 40) : $lead['estado'];
    $fuente = array_key_exists('fuente', $input) ? sanitizeText($input['fuente'], 30) : $lead['fuente'];
    $origen = array_key_exists('origen', $input) ? sanitizeText($input['origen'], 40) : ($lead['origen'] ?? 'manual');

    if (!$nombre || !$telefono) {
        jsonResponse(['ok' => false, 'error' => 'Nombre y teléfono son obligatorios'], 422);
    }

    if ($estado && !in_array($estado, $allowedEstados, true)) {
        jsonResponse(['ok' => false, 'error' => 'Estado no válido'], 422);
    }

    if ($fuente && !in_array($fuente, $allowedFuentes, true)) {
        jsonResponse(['ok' => false, 'error' => 'Fuente no válida'], 422);
    }

    if ($origen && !in_array($origen, $allowedOrigenes, true)) {
        jsonResponse(['ok' => false, 'error' => 'Origen no válido'], 422);
    }

    $stmt = $pdo->prepare(
        'UPDATE leads SET
            nombre = :nombre,
            telefono = :telefono,
            modelo = :modelo,
            pie = :pie,
            fuente = :fuente,
            origen = :origen,
            estado = :estado,
            notas = :notas,
            updated_at = :updated_at
         WHERE id = :id'
    );

    $stmt->execute([
        'id' => $id,
        'nombre' => $nombre,
        'telefono' => $telefono,
        'modelo' => $modelo,
        'pie' => $pie,
        'fuente' => $fuente,
        'origen' => $origen,
        'estado' => $estado,
        'notas' => $notas,
        'updated_at' => nowIso(),
    ]);

    jsonResponse(['ok' => true, 'message' => 'Lead actualizado']);
}

if ($method === 'DELETE') {
    requireAuth();

    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['ok' => false, 'error' => 'ID inválido'], 422);
    }

    $stmt = $pdo->prepare('DELETE FROM leads WHERE id = :id');
    $stmt->execute(['id' => $id]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['ok' => false, 'error' => 'Lead no encontrado'], 404);
    }

    jsonResponse(['ok' => true, 'message' => 'Lead eliminado']);
}

jsonResponse(['ok' => false, 'error' => 'Método no permitido'], 405);
