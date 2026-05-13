<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Conexao;

$permissoes = [

    // ===============================
    // UTILIZADORES
    // ===============================
    ['utilizadores.ver', 'Ver utilizadores', 'Utilizadores'],
    ['utilizadores.criar', 'Criar utilizadores', 'Utilizadores'],
    ['utilizadores.editar', 'Editar utilizadores', 'Utilizadores'],
    ['utilizadores.eliminar', 'Eliminar utilizadores', 'Utilizadores'],

    // ===============================
    // PERFIS
    // ===============================
    ['perfis.ver', 'Ver perfis', 'Perfis'],
    ['perfis.criar', 'Criar perfis', 'Perfis'],
    ['perfis.editar', 'Editar perfis', 'Perfis'],
    ['perfis.eliminar', 'Eliminar perfis', 'Perfis'],

    // ===============================
    // PERMISSÕES
    // ===============================
    ['permissoes.ver', 'Ver permissões', 'Permissões'],
    ['permissoes.criar', 'Criar permissões', 'Permissões'],
    ['permissoes.editar', 'Editar permissões', 'Permissões'],
    ['permissoes.eliminar', 'Eliminar permissões', 'Permissões'],

    // ===============================
    // DOCUMENTOS
    // ===============================
    ['documentos.ver', 'Ver documentos', 'Documentos'],
    ['documentos.criar', 'Carregar documentos', 'Documentos'],
    ['documentos.editar', 'Editar documentos', 'Documentos'],
    ['documentos.eliminar', 'Eliminar documentos', 'Documentos'],
    ['documentos.download', 'Download de documentos', 'Documentos'],

    // ===============================
    // TRAMITAÇÃO — DOCUMENTOS
    // ===============================
    ['tramitacao.ver', 'Ver tramitação dos documentos', 'Tramitação'],
    ['tramitacao.encaminhar', 'Encaminhar documentos para áreas', 'Tramitação'],
    ['tramitacao.comentar', 'Adicionar comentários na tramitação', 'Tramitação'],
    ['tramitacao.mudar_estado', 'Alterar estado dos documentos', 'Tramitação'],
    ['tramitacao.arquivar', 'Arquivar documentos', 'Tramitação'],

    // ===============================
    // TRAMITAÇÃO — ÁREAS
    // ===============================
    ['tramitacao.areas.ver', 'Ver áreas de tramitação', 'Tramitação - Áreas'],
    ['tramitacao.areas.criar', 'Criar áreas de tramitação', 'Tramitação - Áreas'],
    ['tramitacao.areas.editar', 'Editar áreas de tramitação', 'Tramitação - Áreas'],
    ['tramitacao.areas.apagar', 'Apagar áreas de tramitação', 'Tramitação - Áreas'],
];

$db = Conexao::getInstancia();

foreach ($permissoes as $p) {
    [$chave, $nome, $categoria] = $p;

    $stmt = $db->prepare("
        INSERT IGNORE INTO permissoes (chave, nome, categoria)
        VALUES (:chave, :nome, :categoria)
    ");

    $stmt->execute([
        ':chave'     => $chave,
        ':nome'      => $nome,
        ':categoria' => $categoria,
    ]);

    echo "Criada permissão: $chave\n";
}

echo "\nPermissões geradas com sucesso.\n";
