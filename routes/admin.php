<?php

// ============================================================
// ROTAS DO SITE (PÚBLICAS)
// ============================================================

// Página inicial
$router->get('/', 'Site\HomeController@index')->name('site.home');

// Login
$router->get('/login', 'AuthController@login')->name('auth.login');
$router->post('/login', 'AuthController@loginSubmit', ['csrf']);

// Registo
$router->get('/registar', 'AuthController@registar')->name('auth.register');
$router->post('/registar', 'AuthController@registarSubmit', ['csrf']);

// ============================================================
// RECUPERAÇÃO DE PASSWORD — FLUXO ÚNICO (CORRIGIDO)
// ============================================================

// 1️⃣ Pedir email
$router->get('/recuperar', 'PasswordResetController@solicitar');
$router->post('/recuperar', 'PasswordResetController@enviarLink', ['csrf']);

// 2️⃣ Formulário de nova password (GET)
$router->get('/reset-password/token/{token}', 'PasswordResetController@formNovaPassword');

// 3️⃣ Guardar nova password (POST)
$router->post('/reset-password/guardar/{token}', 'PasswordResetController@guardarNovaPassword', ['csrf']);

// Logout (POST seguro)
$router->post('/logout', 'AuthController@logout', ['csrf'])->name('auth.logout');

// ============================================================
// DASHBOARD UTILIZADOR NORMAL
// ============================================================

$router->get('/dashboard', 'DashboardUserController@index');

// ============================================================
// DOCUMENTOS DO UTILIZADOR NORMAL
// ============================================================

// Listagem
$router->get('/documentos', 'DocumentosUserController@index');

// Criar
$router->get('/documentos/criar', 'DocumentosUserController@criar');
$router->post('/documentos/criar', 'DocumentosUserController@criarSubmit', ['csrf']);

// Anexos / Download
$router->get('/documentos/anexo/abrir/{id:\d+}', 'DocumentosUserController@abrir');
$router->get('/documentos/download/{id:\d+}', 'DocumentosUserController@download');

// Abrir documento
$router->get('/documentos/abrir/{id:\d+}', 'DocumentosUserController@abrir');

// ============================================================
// ROTAS ADMIN (PROTEGIDAS POR AUTH)
// ============================================================

$router->group([
    'prefix' => '/admin',
    'middleware' => ['auth']
], function ($router) {

    // Dashboard
    $router->get('/', 'Admin\DashboardAdminController@index')->name('admin.dashboard');
    $router->get('/dashboard', 'Admin\DashboardAdminController@index');

    // Utilizadores
    $router->get('/utilizadores', 'Admin\UtilizadoresAdminController@index')->name('admin.users.index');
    $router->get('/utilizadores/criar', 'Admin\UtilizadoresAdminController@criar')->name('admin.users.create');
    $router->post('/utilizadores/criar', 'Admin\UtilizadoresAdminController@criarSubmit', ['csrf']);
    $router->get('/utilizadores/editar/{id:\d+}', 'Admin\UtilizadoresAdminController@editar')->name('admin.users.edit');
    $router->post('/utilizadores/editar/{id:\d+}', 'Admin\UtilizadoresAdminController@editarSubmit', ['csrf']);
    $router->post('/utilizadores/eliminar/{id:\d+}', 'Admin\UtilizadoresAdminController@eliminar', ['csrf']);

    $router->get('/utilizadores/pendentes', 'Admin\UtilizadoresAdminController@pendentes')->name('admin.users.pending');
    $router->post('/utilizadores/aprovar/{id:\d+}', 'Admin\UtilizadoresAdminController@aprovar', ['csrf']);
    $router->post('/utilizadores/rejeitar/{id:\d+}', 'Admin\UtilizadoresAdminController@rejeitar', ['csrf']);
    $router->post('/utilizadores/bloquear/{id:\d+}', 'Admin\UtilizadoresAdminController@bloquear', ['csrf']);
    $router->post('/utilizadores/desbloquear/{id:\d+}', 'Admin\UtilizadoresAdminController@desbloquear', ['csrf']);

    $router->get('/utilizadores/ativos', 'Admin\UtilizadoresAdminController@ativos')->name('admin.users.active');
    $router->get('/utilizadores/bloqueados', 'Admin\UtilizadoresAdminController@bloqueados')->name('admin.users.blocked');
    $router->get('/utilizadores/exportar', 'Admin\UtilizadoresAdminController@exportarCSV')->name('admin.users.export');

    // Perfis
    $router->get('/perfis', 'Admin\PerfisAdminController@index')->name('admin.roles.index');
    $router->get('/perfis/criar', 'Admin\PerfisAdminController@criar')->name('admin.roles.create');
    $router->post('/perfis/criar', 'Admin\PerfisAdminController@crriarSubmit', ['csrf']);
    $router->get('/perfis/editar/{id:\d+}', 'Admin\PerfisAdminController@editar')->name('admin.roles.edit');
    $router->post('/perfis/editar/{id:\d+}', 'Admin\PerfisAdminController@editarSubmit', ['csrf']);
    $router->get('/perfis/permissoes/{id:\d+}', 'Admin\PerfisAdminController@permissoes')->name('admin.roles.permissions');
    $router->post('/perfis/permissoes/{id:\d+}', 'Admin\PerfisAdminController@permissoesSubmit', ['csrf']);
    $router->post('/perfis/apagar/{id:\d+}', 'Admin\PerfisAdminController@apagar', ['csrf']);

    // Documentos Admin
    $router->get('/documentos', 'Admin\DocumentosAdminController@index')->name('admin.documentos.ver');
    $router->get('/documentos/criar', 'Admin\DocumentosAdminController@criar')->name('admin.documentos.criar');
    $router->post('/documentos/criar', 'Admin\DocumentosAdminController@criarSubmit', ['csrf'])->name('admin.documentos.criar.submit');
    $router->get('/documentos/editar/{id:\d+}', 'Admin\DocumentosAdminController@editar')->name('admin.documentos.editar');
    $router->post('/documentos/editar/{id:\d+}', 'Admin\DocumentosAdminController@editarSubmit', ['csrf'])->name('admin.documentos.editar.submit');
    $router->get('/documentos/ver/{id:\d+}', 'Admin\DocumentosAdminController@ver')->name('admin.documentos.ver_detalhe');

    // Anexos
    $router->get('/documentos/anexo/abrir/{id:\d+}', 'Admin\DocumentosAdminController@abrirAnexo');
    $router->get('/documentos/anexo/download/{id:\d+}', 'Admin\DocumentosAdminController@downloadAnexo');
    $router->get('/documentos/anexo/ver/{id:\d+}', 'Admin\DocumentosAdminController@verAnexo');

    // Arquivados
    $router->get('/documentos/arquivados', 'Admin\DocumentosAdminController@arquivados')->name('admin.documentos.arquivados');
    $router->get('/documentos/arquivado/{id:\d+}', 'Admin\DocumentosAdminController@verArquivado')->name('admin.documentos.arquivado.ver');
    $router->post('/documentos/arquivado/{id:\d+}/recuperar', 'Admin\DocumentosAdminController@recuperarArquivado', ['csrf'])->name('admin.documentos.arquivados.recuperar');

    // Eliminar
    $router->post('/documentos/eliminar/{id:\d+}', 'Admin\DocumentosAdminController@eliminar', ['csrf'])->name('admin.documentos.apagar');

    // Download múltiplo
    $router->post('/documentos/download-multiple', 'Admin\DocumentosAdminController@downloadMultiple', ['csrf'])->name('admin.documentos.download_multiple');

    // Ficheiros existentes
    $router->get('/documentos/existentes', 'Admin\DocumentosAdminController@ficheirosExistentes');

    // Apagar ficheiro
    $router->post('/documentos/ficheiros/apagar/{id:\d+}', 'Admin\DocumentosAdminController@apagar', ['csrf'])->name('admin.documentos.apagar_anexo');

    // Documento Áreas
    $router->get('/documento-areas', 'Admin\DocumentoAreasAdminController@index')->name('admin.documento_areas.index');
    $router->get('/documento-areas/criar', 'Admin\DocumentoAreasAdminController@criar')->name('admin.documento_areas.criar');
    $router->post('/documento-areas', 'Admin\DocumentoAreasAdminController@store', ['csrf'])->name('admin.documento_areas.store');
    $router->get('/documento-areas/editar/{id}', 'Admin\DocumentoAreasAdminController@editar')->name('admin.documento_areas.editar');
    $router->post('/documento-areas/{id}', 'Admin\DocumentoAreasAdminController@update', ['csrf'])->name('admin.documento_areas.update');
    $router->post('/documento-areas/apagar/{id}', 'Admin\DocumentoAreasAdminController@apagar', ['csrf'])->name('admin.documento_areas.apagar');

    // Documento Tipos
    $router->get('/documento-tipos', 'Admin\DocumentoTiposAdminController@index');
    $router->get('/documento-tipos/criar', 'Admin\DocumentoTiposAdminController@criar');
    $router->post('/documento-tipos/criar', 'Admin\DocumentoTiposAdminController@criarSubmit', ['csrf']);
    $router->get('/documento-tipos/editar/{id:\d+}', 'Admin\DocumentoTiposAdminController@editar');
    $router->post('/documento-tipos/editar/{id:\d+}', 'Admin\DocumentoTiposAdminController@editarSubmit', ['csrf']);
    $router->post('/documento-tipos/apagar/{id:\d+}', 'Admin\DocumentoTiposAdminController@apagar', ['csrf']);

    // Documento Estados
    $router->get('/documento-estados', 'Admin\DocumentoEstadosAdminController@index');
    $router->get('/documento-estados/criar', 'Admin\DocumentoEstadosAdminController@criar');
    $router->post('/documento-estados/criar', 'Admin\DocumentoEstadosAdminController@criarPost', ['csrf']);
    $router->get('/documento-estados/editar/{id:\d+}', 'Admin\DocumentoEstadosAdminController@editar');
    $router->post('/documento-estados/editar/{id:\d+}', 'Admin\DocumentoEstadosAdminController@editarPost', ['csrf']);
    $router->post('/documento-estados/apagar/{id:\d+}', 'Admin\DocumentoEstadosAdminController@apagar', ['csrf']);

    // Tramitação
    $router->get('/tramitacao/dashboard', 'Admin\TramitacaoAdminController@dashboard')->name('admin.tramitacao.dashboard');
    $router->get('/tramitacao', 'Admin\TramitacaoAdminController@lista')->name('admin.tramitacao.lista');
    $router->get('/tramitacao/{id:\d+}', 'Admin\TramitacaoAdminController@documento')->name('admin.tramitacao.documento');

    $router->post('/tramitacao/encaminhar', 'Admin\TramitacaoAdminController@encaminhar', ['csrf'])->name('admin.tramitacao.encaminhar');
    $router->post('/tramitacao/comentar', 'Admin\TramitacaoAdminController@comentar', ['csrf'])->name('admin.tramitacao.comentar');
    $router->post('/tramitacao/estado', 'Admin\TramitacaoAdminController@estado', ['csrf'])->name('admin.tramitacao.estado');

    $router->get('/tramitacao/anexo/{historicoId:\d+}/{ficheiro:.+}', 'Admin\TramitacaoAdminController@verAnexo')->name('admin.tramitacao.ver_anexo');

    $router->post('/tramitacao/arquivar', 'Admin\TramitacaoAdminController@arquivar', ['csrf'])->name('admin.tramitacao.arquivar');

    // Permissões
    $router->get('/permissoes', 'Admin\PermissoesAdminController@index')->name('admin.permissions.index');
    $router->get('/permissoes/criar', 'Admin\PermissoesAdminController@criar')->name('admin.permissions.create');
    $router->post('/permissoes/criar', 'Admin\PermissoesAdminController@criarSubmit', ['csrf']);
    $router->get('/permissoes/editar/{id:\d+}', 'Admin\PermissoesAdminController@editar')->name('admin.permissions.edit');
    $router->post('/permissoes/editar/{id:\d+}', 'Admin\PermissoesAdminController@editarSubmit', ['csrf']);
    $router->post('/permissoes/apagar/{id:\d+}', 'Admin\PermissoesAdminController@apagar', ['csrf'])->name('admin.permissions.delete');

    // Logs
    $router->get('/logs', 'Admin\LogsSistemaAdminController@index')->name('admin.logs.index');
    $router->get('/logs/{id:\d+}', 'Admin\LogsSistemaAdminController@detalhes')->name('admin.logs.details');

    // Auditoria
    $router->get('/auditoria', 'Admin\AuditoriaAdminController@index')->name('admin.auditoria.index');
    $router->get('/auditoria/exportar', 'Admin\AuditoriaAdminController@exportar')->name('admin.auditoria.exportar');
    $router->get('/auditoria/dashboard', 'Admin\AuditoriaAdminController@dashboardAuditoria')->name('admin.auditoria.dashboard');
    $router->get('/auditoria/{id:\d+}', 'Admin\AuditoriaAdminController@detalhes')->name('admin.auditoria.details');

    // Backups
    $router->get('/backups', 'Admin\BackupsAdminController@index')->name('admin.backups.index');
    $router->get('/backups/dashboard', 'Admin\BackupsAdminController@dashboard');
    $router->get('/backups/logs', 'Admin\BackupsAdminController@logs');

    $router->get('/backups/bd/criar', 'Admin\BackupsAdminController@criarBD')->name('admin.backups.bd.criar');
    $router->get('/backups/bd/restaurar-reiniciar/{ficheiro:.+}', 'Admin\BackupsAdminController@restaurarEReiniciar')->name('admin.backups.bd.restaurar.reiniciar');
    $router->get('/backups/bd/restaurar/{ficheiro:.+}', 'Admin\BackupsAdminController@restaurarConfirmar')->name('admin.backups.bd.restaurar.confirmar');
    $router->post('/backups/bd/restaurar/{ficheiro:.+}', 'Admin\BackupsAdminController@restaurarExecutar', ['csrf'])->name('admin.backups.bd.restaurar.executar');
    $router->get('/backups/bd/download/{ficheiro:.+}', 'Admin\BackupsAdminController@download')->name('admin.backups.bd.download');
    $router->post('/backups/bd/delete/{ficheiro:.+}', 'Admin\BackupsAdminController@delete', ['csrf'])->name('admin.backups.bd.apagar');

    // Backup Ficheiros
    $router->get('/backups/files/criar', 'Admin\BackupsAdminController@criarFiles')->name('admin.backups.files.criar');
    $router->get('/backups/files/restaurar/{ficheiro:.+}', 'Admin\BackupsAdminController@restaurarFilesConfirmar')->name('admin.backups.files.restaurar.confirmar');
    $router->post('/backups/files/restaurar/{ficheiro:.+}', 'Admin\BackupsAdminController@restaurarFilesExecutar', ['csrf'])->name('admin.backups.files.restaurar.executar');
    $router->get('/backups/files/download/{ficheiro:.+}', 'Admin\BackupsAdminController@download')->name('admin.backups.files.download');
    $router->post('/backups/files/delete/{ficheiro:.+}', 'Admin\BackupsAdminController@delete', ['csrf'])->name('admin.backups.files.apagar');

    // Agendamentos
    $router->get('/agendamentos', 'Admin\\AgendamentosController@index');
    $router->get('/agendamentos/criar', 'Admin\\AgendamentosController@criar');
    $router->post('/agendamentos/criar', 'Admin\\AgendamentosController@criarPost', ['csrf']);

    $router->get('/agendamentos/ver/{nome}', 'Admin\\AgendamentosController@ver');
    $router->get('/agendamentos/editar/{nome}', 'Admin\\AgendamentosController@editar');
    $router->post('/agendamentos/editar/{nome}', 'Admin\\AgendamentosController@editarPost', ['csrf']);

    $router->post('/agendamentos/ativar/{nome}', 'Admin\\AgendamentosController@ativar', ['csrf']);
    $router->post('/agendamentos/desativar/{nome}', 'Admin\\AgendamentosController@desativar', ['csrf']);
    $router->post('/agendamentos/eliminar/{nome}', 'Admin\\AgendamentosController@eliminar', ['csrf']);

    // Relatórios
    $router->get('/relatorios', 'Admin\RelatoriosAdminController@index');
    $router->get('/relatorios/exportar/pdf', 'Admin\RelatoriosAdminController@exportarPDF');
    $router->get('/relatorios/exportar/excel', 'Admin\RelatoriosAdminController@exportarExcel');
});
