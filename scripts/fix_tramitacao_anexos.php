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
require __DIR__ . '/../App/Models/DocumentoTramitacaoAnexo.php';

use App\Models\DocumentoTramitacaoAnexo;

echo "\n=== CORREÇÃO DE ANEXOS DA TRAMITAÇÃO ===\n\n";

if (!defined('STORAGE_TRAMITACAO')) {
    echo "ERRO: STORAGE_TRAMITACAO não está definida.\n";
    exit;
}

$base = rtrim(STORAGE_TRAMITACAO, '/');

// 5. Testar ligação à BD
try {
    \App\Core\Conexao::getInstancia();
    echo "Ligação à BD OK.\n\n";
} catch (\Exception $e) {
    echo "ERRO BD: " . $e->getMessage() . "\n";
    exit;
}

$anexos = DocumentoTramitacaoAnexo::all();

$total   = count($anexos);
$fixes   = 0;
$missing = 0;
$moved   = 0;

foreach ($anexos as $a) {

    $id       = $a->id;
    $caminho  = trim($a->caminho, '/');
    $ficheiro = $a->ficheiro;

    $path = "{$base}/{$caminho}/{$ficheiro}";

    echo "Anexo #{$id}: {$path}\n";

    if ($caminho === '') {
        echo " - Caminho vazio → corrigindo\n";
        $novo = date('Y/m/d');
        $a->update(['caminho' => $novo]);
        $caminho = $novo;
        $path = "{$base}/{$caminho}/{$ficheiro}";
        $fixes++;
    }

    if (!is_dir("{$base}/{$caminho}")) {
        echo " - Pasta inexistente → criando\n";
        mkdir("{$base}/{$caminho}", 0777, true);
        $fixes++;
    }

    if (file_exists($path)) {
        echo " - OK\n\n";
        continue;
    }

    echo " - Ficheiro não encontrado\n";

    $found = false;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getFilename() === $ficheiro) {
            $origem  = $file->getPathname();
            $destino = "{$base}/{$caminho}/{$ficheiro}";
            echo " - Encontrado em: {$origem}\n";
            echo " - Movendo para: {$destino}\n";
            copy($origem, $destino);
            $moved++;
            $found = true;
            break;
        }
    }

    if (!$found) {
        echo " - ORFÃO\n";
        $missing++;
    }

    echo "\n";
}

echo "\n=== RESUMO ===\n";
echo "Total: {$total}\n";
echo "Caminhos corrigidos: {$fixes}\n";
echo "Movidos: {$moved}\n";
echo "Órfãos: {$missing}\n\n";
