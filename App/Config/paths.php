<?php

/**
 * ============================================================
 * PATHS — DEFINIÇÃO DE CAMINHOS BASE DO PROJETO
 * ============================================================
 */

define('BASE_PATH', dirname(__DIR__, 2));

/**
 * ============================================================
 * STORAGE — PASTA REAL DO PROJETO
 * ============================================================
 */

define('STORAGE_PATH', BASE_PATH . '/storage');

/**
 * Subpastas do storage (melhores práticas)
 */
define('STORAGE_DOCUMENTOS', STORAGE_PATH . '/documentos');
define('STORAGE_ARQUIVADOS', STORAGE_PATH . '/documentos_arquivados');
define('STORAGE_TEMP', STORAGE_PATH . '/temp');
define('STORAGE_UPLOADS', STORAGE_PATH . '/uploads');
define('STORAGE_LOGS', STORAGE_PATH . '/logs');
define('STORAGE_SESSIONS', STORAGE_PATH . '/sessions');
define('STORAGE_TRAMITACAO', STORAGE_PATH . '/tramitacao');
define('STORAGE_BACKUPS', STORAGE_PATH . '/backups');   // ← ADICIONADO

/**
 * ============================================================
 * GARANTIR QUE AS PASTAS EXISTEM
 * ============================================================
 */

$pastasObrigatorias = [
    STORAGE_DOCUMENTOS,
    STORAGE_ARQUIVADOS,
    STORAGE_TEMP,
    STORAGE_UPLOADS,
    STORAGE_LOGS,
    STORAGE_TRAMITACAO,
    STORAGE_SESSIONS,
    STORAGE_BACKUPS   // ← ADICIONADO
];

foreach ($pastasObrigatorias as $pasta) {
    if (!is_dir($pasta)) {
        mkdir($pasta, 0777, true);
    }
}
