<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function getDb(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    initDb($pdo);

    return $pdo;
}

function initDb(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS leads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            telefono TEXT NOT NULL,
            modelo TEXT,
            pie TEXT,
            fuente TEXT NOT NULL DEFAULT "formulario",
            estado TEXT NOT NULL DEFAULT "nuevo",
            notas TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_leads_estado ON leads(estado)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_leads_created ON leads(created_at DESC)');
}

function leadToArray(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'nombre' => $row['nombre'],
        'telefono' => $row['telefono'],
        'modelo' => $row['modelo'],
        'pie' => $row['pie'],
        'fuente' => $row['fuente'],
        'estado' => $row['estado'],
        'notas' => $row['notas'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ];
}
