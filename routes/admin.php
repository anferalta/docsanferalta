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
$router->post('/registar', 'AuthController@registarSubmit');

// Recuperação de password (pedido)
$router->get('/recuperar', 'AuthController@recuperar')->name('auth.recover');
$router->post('/recuperar', 'AuthController@recuperarSubmit');

// Debug limpar sessão
$router->get('/limpar-sessao', 'DebugController@limparSessao');

// ===============================
//  RECUPERAÇÃO DE PASSWORD (PÚBLICA)
// ===============================

// Formulário de redefinição de password (com token)
$router->get('/reset-password/token/{token}', 'PasswordResetController@formNovaPassword');
// Submissão da nova password
$router->post('/reset-password/token/{token}', 'PasswordResetController@guardarNovaPassword');

// Logout
$router->get('/logout', 'AuthController@logout')->name('auth.logout');

// ============================================================
// DASHBOARD DO UTILIZADOR NORMAL (FORA DO /admin)
// ============================================================

$router->get('/dashboard', 'DashboardUserController@index');

// ============================================================
// DOCUMENTOS DO UTILIZADOR NORMAL (FORA DO /admin)
// ============================================================

// LISTA
$router->get('/documentos', 'DocumentosUserController@index');

// CRIAR
$router->get('/documentos/criar', 'DocumentosUserController@criar');
$router->post('/documentos/criar', 'DocumentosUserController@criarSubmit');

// ANEXOS / DOWNLOAD
$router->get('/documentos/anexo/abrir/{id:\d+}', 'DocumentosUserController@abrir');
$router->get('/documentos/download/{id:\d+}', 'DocumentosUserController@download');

// ROTA GENÉRICA DE ABRIR DOCUMENTO (COM REGEX, NO FIM)
$router->get('/documentos/abrir/{id:\d+}', 'DocumentosUserController@abrir');

// ============================================================
// ROTAS ADMIN (PROTEGIDAS POR AUTH)
// ============================================================

$router->group([
    'prefix' => '/admin',
    'middleware' => ['auth']
], function ($router) {

    // ============================================================
    // DASHBOARD ADMIN
    // ============================================================
    $router->get('/', 'Admin\DashboardAdminController@index')->name('admin.dashboard');
    $router->get('/dashboard', 'Admin\DashboardAdminController@index');

    // ============================================================
    // UTILIZADORES
    // ============================================================
    $router->get('/utilizadores', 'Admin\UtilizadoresAdminController@index')->name('admin.users.index');
    $router->get('/utilizadores/criar', 'Admin\UtilizadoresAdminController@criar')->name('admin.users.create');
    $router->post('/utilizadores/criar', 'Admin\UtilizadoresAdminController@criarSubmit');
    $router->get('/utilizadores/editar/{id:\d+}', 'Admin\UtilizadoresAdminController@editar')->name('admin.users.edit');
    $router->post('/utilizadores/editar/{id:\d+}', 'Admin\UtilizadoresAdminController@editarSubmit');
    $router->get('/utilizadores/eliminar/{id:\d+}', 'Admin\UtilizadoresAdminController@eliminar')->name('admin.users.delete');

    $router->get('/utilizadores/pendentes', 'Admin\UtilizadoresAdminController@pendentes')->name('admin.users.pending');
    $router->post('/utilizadores/aprovar/{id:\d+}', 'Admin\UtilizadoresAdminController@aprovar')->name('admin.users.approve');
    $router->post('/utilizadores/rejeitar/{id:\d+}', 'Admin\UtilizadoresAdminController@rejeitar')->name('admin.users.reject');
    $router->post('/utilizadores/bloquear/{id:\d+}', 'Admin\UtilizadoresAdminController@bloquear')->name('admin.users.block');
    $router->post('/utilizadores/desbloquear/{id:\d+}', 'Admin\UtilizadoresAdminController@desbloquear')->name('admin.users.unblock');

    $router->get('/utilizadores/ativos', 'Admin\UtilizadoresAdminController@ativos')->name('admin.users.active');
    $router->get('/utilizadores/bloqueados', 'Admin\UtilizadoresAdminController@bloqueados')->name('admin.users.blocked');
    $router->get('/utilizadores/exportar', 'Admin\UtilizadoresAdminController@exportarCSV')->name('admin.users.export');

    // ============================================================
    // PERFIS
    // ============================================================
    $router->get('/perfis', 'Admin\PerfisAdminController@index')->name('admin.roles.index');
    $router->get('/perfis/criar', 'Admin\PerfisAdminController@criar')->name('admin.roles.create');
    $router->post('/perfis/criar', 'Admin\PerfisAdminController@criarSubmit');
    $router->get('/perfis/editar/{id:\d+}', 'Admin\PerfisAdminController@editar')->name('admin.roles.edit');
    $router->post('/perfis/editar/{id:\d+}', 'Admin\PerfisAdminController@editarSubmit');
    $router->get('/perfis/permissoes/{id:\d+}', 'Admin\PerfisAdminController@permissoes')->name('admin.roles.permissions');
    $router->post('/perfis/permissoes/{id:\d+}', 'Admin\PerfisAdminController@permissoesSubmit');
    $router->post('/perfis/apagar/{id:\d+}', 'Admin\PerfisAdminController@apagar')->name('admin.roles.delete');

    // ============================================================
    // DOCUMENTOS (ADMIN)
    // ============================================================

    // LISTAGEM
    $router->get('/documentos', 'Admin\DocumentosAdminController@index')
        ->name('admin.documentos.ver');

    // CRIAR
    $router->get('/documentos/criar', 'Admin\DocumentosAdminController@criar')
        ->name('admin.documentos.criar');
    $router->post('/documentos/criar', 'Admin\DocumentosAdminController@criarSubmit')
        ->name('admin.documentos.criar.submit');

    // EDITAR
    $router->get('/documentos/editar/{id:\d+}', 'Admin\DocumentosAdminController@editar')
        ->name('admin.documentos.editar');
    $router->post('/documentos/editar/{id:\d+}', 'Admin\DocumentosAdminController@editarSubmit')
        ->name('admin.documentos.editar.submit');

    // VER DETALHE
    $router->get('/documentos/ver/{id:\d+}', 'Admin\DocumentosAdminController@ver')
        ->name('admin.documentos.ver_detalhe');

    // ANEXOS
$router->get(
    '/documentos/anexo/abrir/{id:\d+}', 'Admin\DocumentosAdminController@abrirAnexo',
    ['auth']
);

$router->get(
    '/admin/documentos/anexo/download/{id:\d+}', 'Admin\DocumentosAdminController@downloadAnexo',
    ['auth']
);

$router->get(
    '/admin/documentos/anexo/ver/{id:\d+}',
    'Admin\DocumentosAdminController@verAnexo',
    ['auth']
);

    // ARQUIVADOS
    $router->get('/documentos/arquivados', 'Admin\DocumentosAdminController@arquivados')
        ->name('admin.documentos.arquivados');
    $router->get('/documentos/arquivado/{id:\d+}', 'Admin\DocumentosAdminController@verArquivado')
        ->name('admin.documentos.arquivado.ver');
    $router->post('/documentos/arquivado/{id:\d+}/recuperar', 'Admin\DocumentosAdminController@recuperarArquivado')
        ->name('admin.documentos.arquivado.recuperar');

    // ELIMINAR
    $router->get('/documentos/eliminar/{id:\d+}', 'Admin\DocumentosAdminController@eliminar')
        ->name('admin.documentos.apagar');

    // DOWNLOAD MÚLTIPLO
    $router->post('/documentos/download-multiple', 'Admin\DocumentosAdminController@downloadMultiple')
        ->name('admin.documentos.download_multiple');

    // FICHEIROS EXISTENTES
    $router->get('/documentos/existentes', 'Admin\DocumentosAdminController@ficheirosExistentes');

    // ROTA GENÉRICA (SEM COLISÕES, NO FIM, COM REGEX)
    $router->post(
    '/admin/documentos/ficheiros/apagar/{id}',
    'Admin\DocumentosAdminController@apagar',
    ['auth', 'permissao:admin.documentos.apagar_anexos']
);

    // ============================================================
    // DOCUMENTO TIPOS
    // ============================================================
    $router->get('/documento-tipos', 'Admin\DocumentoTiposAdminController@index');
    $router->get('/documento-tipos/criar', 'Admin\DocumentoTiposAdminController@criar');
    $router->post('/documento-tipos/criar', 'Admin\DocumentoTiposAdminController@criarSubmit');
    $router->get('/documento-tipos/editar/{id:\d+}', 'Admin\DocumentoTiposAdminController@editar');
    $router->post('/documento-tipos/editar/{id:\d+}', 'Admin\DocumentoTiposAdminController@editarSubmit');
    $router->get('/documento-tipos/apagar/{id:\d+}', 'Admin\DocumentoTiposAdminController@apagar');
    $router->get('/documento-tipos/apagar/{id:\d+}', 'Admin\DocumentoEstadosAdminController@confirmarApagar');
    $router->post('/documento-tipos/apagar/{id:\d+}', 'Admin\DocumentoEstadosAdminController@apagar');

    // ============================================================
    // DOCUMENTO ESTADOS
    // ============================================================
    $router->get('/documento-estados', 'Admin\DocumentoEstadosAdminController@index');
    $router->get('/documento-estados/criar', 'Admin\DocumentoEstadosAdminController@criar');
    $router->post('/documento-estados/criar', 'Admin\DocumentoEstadosAdminController@criarPost');
    $router->get('/documento-estados/editar/{id:\d+}', 'Admin\DocumentoEstadosAdminController@editar');
    $router->post('/documento-estados/editar/{id:\d+}', 'Admin\DocumentoEstadosAdminController@editarPost');
    $router->get('/documento-estados/apagar/{id:\d+}', 'Admin\DocumentoEstadosAdminController@confirmarApagar');
    $router->post('/documento-estados/apagar/{id:\d+}', 'Admin\DocumentoEstadosAdminController@apagar');

    // ============================================================
    // TRAMITAÇÃO
    // ============================================================

    // ============================================================
    // TRAMITAÇÃO (ADMIN)
    // ============================================================

    // Dashboard
$router->get('/tramitacao/dashboard', 'Admin\TramitacaoAdminController@dashboard')
    ->name('admin.tramitacao.dashboard');

    // Listagem de documentos em tramitação
$router->get('/tramitacao', 'Admin\TramitacaoAdminController@lista')
    ->name('admin.tramitacao.lista');

    // Página principal de tramitação do documento (COM REGEX)
$router->get('/tramitacao/{id:\d+}', 'Admin\TramitacaoAdminController@documento')
    ->name('admin.tramitacao.documento');

    // Ações de tramitação
$router->post('/tramitacao/encaminhar', 'Admin\TramitacaoAdminController@encaminhar')
    ->name('admin.tramitacao.encaminhar');

$router->post('/tramitacao/comentar', 'Admin\TramitacaoAdminController@comentar')
    ->name('admin.tramitacao.comentar');

$router->post('/tramitacao/estado', 'Admin\TramitacaoAdminController@estado')
    ->name('admin.tramitacao.estado');

    // Ver anexos da tramitação (COM REGEX)
$router->get('/tramitacao/anexo/{historicoId:\d+}/{ficheiro:.+}', 'Admin\TramitacaoAdminController@verAnexo')
    ->name('admin.tramitacao.ver_anexo');

    // Arquivar documento
$router->post('/tramitacao/arquivar', 'Admin\TramitacaoAdminController@arquivar')
    ->name('admin.tramitacao.arquivar');

$router->post(
    '/admin/documentos/ficheiros/apagar/{id}', 'Admin\DocumentosAdminController@apagar',
    ['auth'] // melhor prática: usar middleware de autenticação
);

    // ============================================================
    // ÁREAS DE DOCUMENTOS
    // ============================================================
    $router->get('/documento-areas', 'Admin\DocumentoAreasAdminController@index')->name('admin.documento_areas.index');
    $router->get('/documento-areas/criar', 'Admin\DocumentoAreasAdminController@criar')->name('admin.documento_areas.criar');
    $router->post('/documento-areas', 'Admin\DocumentoAreasAdminController@store')->name('admin.documento_areas.store');
    $router->get('/documento-areas/editar/{id:\d+}', 'Admin\DocumentoAreasAdminController@editar')->name('admin.documento_areas.editar');
    $router->post('/documento-areas/{id:\d+}', 'Admin\DocumentoAreasAdminController@update')->name('admin.documento_areas.update');
    $router->post('/documento-areas/apagar/{id:\d+}', 'Admin\DocumentoAreasAdminController@apagar')->name('admin.documento_areas.apagar');

    // ============================================================
    // ARQUIVAR DOCUMENTOS
    // ============================================================
    $router->post('/documentos/{id:\d+}/arquivar', 'Admin\DocumentosAdminController@arquivar')
        ->name('admin.documentos.arquivar');

    // ============================================================
    // PERMISSÕES
    // ============================================================
    $router->get('/permissoes', 'Admin\PermissoesAdminController@index')->name('admin.permissions.index');
    $router->get('/permissoes/criar', 'Admin\PermissoesAdminController@criar')->name('admin.permissions.create');
    $router->post('/permissoes/criar', 'Admin\PermissoesAdminController@criarSubmit');
    $router->get('/permissoes/editar/{id:\d+}', 'Admin\PermissoesAdminController@editar')->name('admin.permissions.edit');
    $router->post('/permissoes/editar/{id:\d+}', 'Admin\PermissoesAdminController@editarSubmit');
    $router->get('/permissoes/apagar/{id:\d+}', 'Admin\PermissoesAdminController@apagar')->name('admin.permissions.delete');
    $router->get('/permissoes/sincronizar', 'Admin\PermissoesAdminController@sincronizar');

    // ============================================================
    // LOGS
    // ============================================================
    $router->get('/logs', 'Admin\LogsSistemaAdminController@index')->name('admin.logs.index');
    $router->get('/logs/{id:\d+}', 'Admin\LogsSistemaAdminController@detalhes')->name('admin.logs.details');

    // ============================================================
    // AUDITORIA
    // ============================================================
    $router->get('/auditoria', 'Admin\AuditoriaAdminController@index')->name('admin.auditoria.index');
    $router->get('/auditoria/exportar', 'Admin\AuditoriaAdminController@exportar')->name('admin.auditoria.exportar');
    $router->get('/auditoria/dashboard', 'Admin\AuditoriaAdminController@dashboardAuditoria')->name('admin.auditoria.dashboard');
    $router->get('/auditoria/{id:\d+}', 'Admin\AuditoriaAdminController@detalhes')->name('admin.auditoria.details');

    // ============================================================
    // BACKUPS
    // ============================================================
    $router->get('/backups', 'Admin\BackupsAdminController@index')->name('admin.backups.index');
    $router->get('/backups/dashboard', 'Admin\BackupsAdminController@dashboard');
    $router->get('/backups/logs', 'Admin\BackupsAdminController@logs');

    // Backup BD
    $router->get('/backups/bd/criar', 'Admin\BackupsAdminController@criarBD')->name('admin.backups.bd.criar');
    $router->get('/backups/bd/restaurar-reiniciar/{ficheiro:.+}', 'Admin\BackupsAdminController@restaurarEReiniciar')->name('admin.backups.bd.restaurar.reiniciar');
    $router->get('/backups/bd/restaurar/{ficheiro:.+}', 'Admin\BackupsAdminController@restaurarConfirmar')->name('admin.backups.bd.restaurar.confirmar');
    $router->post('/backups/bd/restaurar/{ficheiro:.+}', 'Admin\BackupsAdminController@restaurarExecutar')->name('admin.backups.bd.restaurar.executar');
    $router->get('/backups/bd/download/{ficheiro:.+}', 'Admin\BackupsAdminController@download')->name('admin.backups.bd.download');
    $router->get('/backups/bd/delete/{ficheiro:.+}', 'Admin\BackupsAdminController@delete')->name('admin.backups.bd.apagar');

    // Backup Ficheiros
    $router->get('/backups/files/criar', 'Admin\BackupsAdminController@criarFiles')->name('admin.backups.files.criar');
    $router->get('/backups/files/restaurar/{ficheiro:.+}', 'Admin\BackupsAdminController@restaurarFilesConfirmar')->name('admin.backups.files.restaurar.confirmar');
    $router->post('/backups/files/restaurar/{ficheiro:.+}', 'Admin\BackupsAdminController@restaurarFilesExecutar')->name('admin.backups.files.restaurar.executar');
    $router->get('/backups/files/download/{ficheiro:.+}', 'Admin\BackupsAdminController@download')->name('admin.backups.files.download');
    $router->get('/backups/files/delete/{ficheiro:.+}', 'Admin\BackupsAdminController@delete')->name('admin.backups.files.apagar');

    // Relatórios
    $router->get('/relatorios', 'Admin\\RelatoriosAdminController@index');
    $router->get('/relatorios/exportar/pdf', 'Admin\\RelatoriosAdminController@exportarPDF');
    $router->get('/relatorios/exportar/excel', 'Admin\\RelatoriosAdminController@exportarExcel');
});
