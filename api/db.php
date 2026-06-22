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
            origen TEXT NOT NULL DEFAULT "manual",
            estado TEXT NOT NULL DEFAULT "ingresado",
            notas TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    migrateLeadsTable($pdo);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_leads_estado ON leads(estado)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_leads_origen ON leads(origen)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_leads_created ON leads(created_at DESC)');
}

function migrateLeadsTable(PDO $pdo): void
{
    $columns = $pdo->query('PRAGMA table_info(leads)')->fetchAll();
    $names = array_column($columns, 'name');

    if (!in_array('origen', $names, true)) {
        $pdo->exec('ALTER TABLE leads ADD COLUMN origen TEXT NOT NULL DEFAULT "manual"');
    }

    $pdo->exec(
        'UPDATE leads SET origen = "SEO_Organico"
         WHERE origen = "manual" AND fuente = "formulario"'
    );
    $pdo->exec(
        'UPDATE leads SET origen = "Campana_MetaAds"
         WHERE origen = "manual" AND fuente = "landing"'
    );
    $pdo->exec(
        'UPDATE leads SET origen = "whatsapp"
         WHERE origen = "manual" AND fuente = "whatsapp"'
    );

    $pdo->exec(
        'UPDATE leads SET estado = "ingresado" WHERE estado IN ("nuevo", "")'
    );
    $pdo->exec(
        'UPDATE leads SET estado = "cotizacion_enviada" WHERE estado = "cotizado"'
    );
    $pdo->exec(
        'UPDATE leads SET estado = "concreto" WHERE estado = "vendido"'
    );
    $pdo->exec(
        'UPDATE leads SET estado = "perdido" WHERE estado = "descartado"'
    );
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
        'origen' => $row['origen'] ?? 'manual',
        'estado' => $row['estado'],
        'notas' => $row['notas'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ];
}

function origenLabel(string $origen): string
{
    return match ($origen) {
        'SEO_Organico' => 'Orgánico',
        'Campana_MetaAds' => 'Pagado',
        'whatsapp' => 'WhatsApp',
        'manual' => 'Manual',
        default => $origen,
    };
}

function estadoLabel(string $estado): string
{
    return match ($estado) {
        'ingresado' => 'Ingresado',
        'contactado' => 'Contactado',
        'en_veremos' => 'En veremos',
        'reunion_agendada' => 'Reunión agendada',
        'cotizacion_enviada' => 'Cotización enviada',
        'concreto' => 'Concreto',
        'perdido' => 'Perdido',
        default => $estado,
    };
}
