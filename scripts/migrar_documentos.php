<?php

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Models\Documento;

$documentos = Documento::all();

foreach ($documentos as $doc) {

    if (!empty($doc->caminho)) {
        continue;
    }

    $timestamp = strtotime($doc->criado_em ?? 'now');
    $ano = date('Y', $timestamp);
    $mes = date('m', $timestamp);
    $dia = date('d', $timestamp);

    $subpasta = "$ano/$mes/$dia/";

    $origem = __DIR__ . '/../storage/documentos/' . $doc->ficheiro;
    $destinoBase = __DIR__ . '/../storage/documentos/' . $subpasta;

    if (!is_dir($destinoBase)) {
        mkdir($destinoBase, 0777, true);
    }

    $destino = $destinoBase . $doc->ficheiro;

    if (file_exists($origem)) {
        rename($origem, $destino);
    }

    $doc->caminho = $subpasta;
    $doc->save();

    echo "Migrado: {$doc->ficheiro} → {$subpasta}\n";
}

echo "Migração concluída.\n";