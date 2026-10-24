<?php

require __DIR__ . '/../vendor/autoload.php';

// 1. Carregar Env
require __DIR__ . '/../App/Core/Env.php';
\App\Core\Env::load();

// 2. Carregar paths.php
require __DIR__ . '/../App/Config/paths.php';

// 3. Carregar ORM
require __DIR__ . '/../App/Core/Conexao.php';
require __DIR__ . '/../App/Core/Model.php';

// 4. Carregar modelo
require __DIR__ . '/../App/Models/DocumentoFicheiro.php';

use App\Models\DocumentoFicheiro;

echo "\n=== REMOÇÃO DE ANEXOS ÓRFÃOS DO DOCUMENTO ===\n\n";

$aplicar = in_array('--apply', $argv ?? [], true);
if (!$aplicar) {
    echo "SIMULAÇÃO: nenhum registo será removido. Para aplicar, execute: php scripts/delete_documento_orfaos.php --apply\n\n";
}

// Confirmar storage
if (!defined('STORAGE_DOCUMENTOS')) {
    echo "ERRO: STORAGE_DOCUMENTOS não está definida.\n";
    exit;
}

$base = rtrim(STORAGE_DOCUMENTOS, '/\\');

// Testar BD
try {
    \App\Core\Conexao::getInstancia();
    echo "Ligação à BD OK.\n\n";
} catch (\Exception $e) {
    echo "ERRO BD: " . $e->getMessage() . "\n";
    exit;
}

// Obter anexos
$anexos = DocumentoFicheiro::all();

$total   = count($anexos);
$apagados = 0;

foreach ($anexos as $a) {

    $id       = $a->id;
    $caminho  = trim((string) $a->caminho, '/\\');
    $ficheiro = basename((string) $a->ficheiro);

    $path = $base . DIRECTORY_SEPARATOR
        . ($caminho !== '' ? str_replace('/', DIRECTORY_SEPARATOR, $caminho) . DIRECTORY_SEPARATOR : '')
        . $ficheiro;

    echo "Anexo #{$id}: {$path}\n";

    // Se o ficheiro existe → não apagar
    if (is_file($path)) {
        echo " - OK (mantido)\n\n";
        continue;
    }

    // Este comando remove apenas o registo da BD; nunca apaga ficheiros físicos.
    if (!$aplicar) {
        echo " - sem ficheiro físico; registo seria removido da BD (simulação)\n\n";
        continue;
    }

    echo " - sem ficheiro físico; a remover apenas o registo da BD\n";

    $a->delete();
    $apagados++;

    echo "\n";
}

echo "\n=== RESUMO ===\n";
echo "Total analisados: {$total}\n";
echo "Órfãos apagados: {$apagados}\n\n";

echo "Concluído.\n";
