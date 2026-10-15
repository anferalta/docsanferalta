<?php

require __DIR__ . '/../vendor/autoload.php';

// 1. Carregar Env
require __DIR__ . '/../App/Core/Env.php';
\App\Core\Env::load();

// 2. Carregar paths.php (define STORAGE_DOCUMENTOS)
require __DIR__ . '/../App/Config/paths.php';

// 3. Carregar ORM
require __DIR__ . '/../App/Core/Conexao.php';
require __DIR__ . '/../App/Core/Model.php';

// 4. Carregar modelo dos anexos do documento
require __DIR__ . '/../App/Models/DocumentoFicheiro.php';

use App\Models\DocumentoFicheiro;

echo "\n=== CORREÇÃO DE ANEXOS DO DOCUMENTO ===\n\n";

if (!defined('STORAGE_DOCUMENTOS')) {
    echo "ERRO: STORAGE_DOCUMENTOS não está definida.\n";
    exit;
}

$base = rtrim(STORAGE_DOCUMENTOS, '/');

// 5. Testar ligação à BD
try {
    \App\Core\Conexao::getInstancia();
    echo "Ligação à BD OK.\n\n";
} catch (\Exception $e) {
    echo "ERRO BD: " . $e->getMessage() . "\n";
    exit;
}

// 6. Obter todos os anexos
$anexos = DocumentoFicheiro::all();

$total   = count($anexos);
$fixes   = 0;
$moved   = 0;
$missing = 0;

foreach ($anexos as $a) {

    $id       = $a->id;
    $caminho  = trim($a->caminho, '/');
    $ficheiro = $a->ficheiro;

    $path = "{$base}/{$caminho}/{$ficheiro}";

    echo "Anexo #{$id}: {$path}\n";

    // 7. Caminho vazio → corrigir automaticamente
    if ($caminho === '') {
        echo " - Caminho vazio → corrigindo para data atual\n";

        $novo = date('Y') . '/' . date('m') . '/' . date('d');
        $a->update(['caminho' => $novo]);

        $caminho = $novo;
        $path = "{$base}/{$caminho}/{$ficheiro}";
        $fixes++;
    }

    // 8. Criar pasta se não existir
    if (!is_dir("{$base}/{$caminho}")) {
        echo " - Pasta inexistente → criando {$base}/{$caminho}\n";
        mkdir("{$base}/{$caminho}", 0777, true);
        $fixes++;
    }

    // 9. Ficheiro existe → OK
    if (file_exists($path)) {
        echo " - OK\n\n";
        continue;
    }

    echo " - Ficheiro não encontrado\n";

    // 10. Procurar ficheiro noutras pastas
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

    // 11. Ficheiro órfão
    if (!$found) {
        echo " - ORFÃO\n";
        $missing++;
    }

    echo "\n";
}

// 12. Resumo final
echo "\n=== RESUMO ===\n";
echo "Total: {$total}\n";
echo "Caminhos corrigidos: {$fixes}\n";
echo "Movidos: {$moved}\n";
echo "Órfãos: {$missing}\n\n";

echo "Concluído.\n";
