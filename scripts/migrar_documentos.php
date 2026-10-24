<?php

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/../App/Core/Env.php';
\App\Core\Env::load();
require __DIR__ . '/../App/Config/paths.php';

use App\Models\Documento;

$aplicar = in_array('--apply', $argv ?? [], true);
$base = rtrim(STORAGE_DOCUMENTOS, '/\\');
$documentos = Documento::all();
$planeados = 0;
$migrados = 0;
$ignorados = 0;
$erros = 0;

echo $aplicar
    ? "=== MIGRAÇÃO CONFIRMADA (--apply) ===\n"
    : "=== SIMULAÇÃO — nenhum ficheiro será movido ===\nPara aplicar, execute: php scripts/migrar_documentos.php --apply\n";

foreach ($documentos as $doc) {
    if (!empty(trim((string) $doc->caminho, '/\\'))) {
        continue;
    }

    $nome = basename((string) $doc->ficheiro);
    if ($nome === '' || $nome === '.') {
        echo "Documento #{$doc->id}: nome de ficheiro inválido; ignorado.\n";
        $erros++;
        continue;
    }

    $timestamp = strtotime($doc->criado_em ?: 'now');
    $subpasta = date('Y/m/d', $timestamp) . '/';
    $origem = $base . DIRECTORY_SEPARATOR . $nome;
    $destinoBase = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($subpasta, '/'));
    $destino = $destinoBase . DIRECTORY_SEPARATOR . $nome;

    if (!is_file($origem)) {
        echo "Documento #{$doc->id}: origem não encontrada; mantido sem alterar a BD.\n";
        $ignorados++;
        continue;
    }
    if (file_exists($destino)) {
        echo "Documento #{$doc->id}: destino já existe; conflito, nada alterado.\n";
        $erros++;
        continue;
    }

    echo "Documento #{$doc->id}: {$origem} -> {$destino}\n";
    $planeados++;
    if (!$aplicar) {
        continue;
    }

    if (!is_dir($destinoBase) && !mkdir($destinoBase, 0777, true) && !is_dir($destinoBase)) {
        echo "  ERRO: não foi possível criar a pasta de destino.\n";
        $erros++;
        continue;
    }

    if (!copy($origem, $destino)
        || filesize($origem) !== filesize($destino)
        || hash_file('sha256', $origem) !== hash_file('sha256', $destino)) {
        if (is_file($destino)) {
            @unlink($destino);
        }
        echo "  ERRO: cópia não validada; origem mantida.\n";
        $erros++;
        continue;
    }

    $caminhoAnterior = $doc->caminho;
    $doc->caminho = $subpasta;
    try {
        if (!$doc->save()) {
            $doc->caminho = $caminhoAnterior;
            @unlink($destino);
            echo "  ERRO: BD não atualizada; origem mantida.\n";
            $erros++;
            continue;
        }
    } catch (Throwable $e) {
        $doc->caminho = $caminhoAnterior;
        @unlink($destino);
        echo "  ERRO: BD não atualizada; origem mantida.\n";
        $erros++;
        continue;
    }

    if (!unlink($origem)) {
        echo "  AVISO: migração concluída; a cópia original permaneceu na raiz.\n";
    }
    $migrados++;
}

echo "Resumo: planeados={$planeados}; migrados={$migrados}; ignorados={$ignorados}; erros={$erros}\n";
