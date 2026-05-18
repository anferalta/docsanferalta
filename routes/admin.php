<?php

// ============================================================
// ROTAS DO SITE (PÚBLICAS)
// ============================================================
// Página inicial
$router->get('/', 'Site\HomeController@index')->name('site.home');

// Login
$router->get('/login', 'AuthController@login')->name('auth.login');
$router->post('/login', 'AuthController@loginSubmit');

// Registo
$router->get('/registar', 'AuthController@registar')->name('auth.register');
$router->post('/registar', 'AuthController@registarSubmit');

// Recuperação de password
$router->get('/recuperar', 'AuthController@recuperar')->name('auth.recover');
$router->post('/recuperar', 'AuthController@recuperarSubmit');

// Logout (pode ser público, o controller trata da sessão)
$router->get('/logout', 'AuthController@logout')->name('auth.logout');

$router->group([
    'prefix' => '/admin',
    'middleware' => ['auth']
        ], function ($router) {

            // DASHBOARD
            $router->get('/', 'Admin\DashboardAdminController@index')->name('admin.dashboard');
            $router->get('/dashboard', 'Admin\DashboardAdminController@index');

            // UTILIZADORES
            $router->get('/utilizadores', 'Admin\UtilizadoresAdminController@index')->name('admin.users.index');
            $router->get('/utilizadores/criar', 'Admin\UtilizadoresAdminController@criar')->name('admin.users.create');
            $router->post('/utilizadores/criar', 'Admin\UtilizadoresAdminController@criarSubmit');
            $router->get('/utilizadores/editar/{id}', 'Admin\UtilizadoresAdminController@editar')->name('admin.users.edit');
            $router->post('/utilizadores/editar/{id}', 'Admin\UtilizadoresAdminController@editarSubmit');
            $router->get('/utilizadores/eliminar/{id}', 'Admin\UtilizadoresAdminController@eliminar')->name('admin.users.delete');
            // UTILIZADORES PENDENTES
            $router->get('/utilizadores/pendentes', 'Admin\UtilizadoresAdminController@pendentes')
                    ->name('admin.users.pending');
            // APROVAR UTILIZADOR
            $router->post('/utilizadores/aprovar/{id}', 'Admin\UtilizadoresAdminController@aprovar')
                    ->name('admin.users.approve');
            // REJEITAR UTILIZADOR
            $router->post('/utilizadores/rejeitar/{id}', 'Admin\UtilizadoresAdminController@rejeitar')
                    ->name('admin.users.reject');
            // BLOQUEAR UTILIZADOR
            $router->post('/utilizadores/bloquear/{id}', 'Admin\UtilizadoresAdminController@bloquear')
                    ->name('admin.users.block');
            // DESBLOQUEAR UTILIZADOR
            $router->post('/utilizadores/desbloquear/{id}', 'Admin\UtilizadoresAdminController@desbloquear')
                    ->name('admin.users.unblock');
            $router->get('/utilizadores/ativos', 'Admin\UtilizadoresAdminController@ativos')
                    ->name('admin.users.active');
            $router->get('/utilizadores/bloqueados', 'Admin\UtilizadoresAdminController@bloqueados')
                    ->name('admin.users.blocked');
            $router->get('/utilizadores/exportar', 'Admin\UtilizadoresAdminController@exportarCSV')
                    ->name('admin.users.export');

            // PERFIS
            $router->get('/perfis', 'Admin\PerfisAdminController@index')->name('admin.roles.index');
            $router->get('/perfis/criar', 'Admin\PerfisAdminController@criar')->name('admin.roles.create');
            $router->post('/perfis/criar', 'Admin\PerfisAdminController@criarSubmit');
            $router->get('/perfis/editar/{id}', 'Admin\PerfisAdminController@editar')->name('admin.roles.edit');
            $router->post('/perfis/editar/{id}', 'Admin\PerfisAdminController@editarSubmit');
            $router->get('/perfis/permissoes/{id}', 'Admin\PerfisAdminController@permissoes')->name('admin.roles.permissions');
            $router->post('/perfis/permissoes/{id}', 'Admin\PerfisAdminController@permissoesSubmit');
            $router->post('/perfis/apagar/{id}', 'Admin\PerfisAdminController@apagar')->name('admin.roles.delete');

            // DOCUMENTOS
            // LISTA (SEM PARÂMETROS)
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

            // ABRIR DOCUMENTO INLINE OU GOOGLE DOCS
            $router->get('/documentos/abrir/{id:\d+}', 'Admin\DocumentosAdminController@abrir')
                    ->name('admin.documentos.abrir');

            // ABRIR RAW PARA GOOGLE DOCS VIEWER
            $router->get('/documentos/abrir_raw/{id:\d+}', 'Admin\DocumentosAdminController@abrir_raw')
                    ->name('admin.documentos.abrir_raw');

            // VER DOCUMENTO POR ID (DINÂMICA SIMPLES — ANTES DA ROTA POR DATA)
            $router->get('/documentos/ver/{id:\d+}', 'Admin\DocumentosAdminController@ver')
                    ->name('admin.documentos.ver_detalhe');

            // VER FICHEIRO POR DATA (ROTA COMPLEXA — COM REGEX)
            $router->get('/documentos/ver/{ano:\d+}/{mes:\d+}/{dia:\d+}/{ficheiro:.+}',
                    'Admin\DocumentosAdminController@verFicheiro');

            // LISTAR ARQUIVADOS
            $router->get('/documentos/arquivados', 'Admin\DocumentosAdminController@arquivados')
                    ->name('admin.documentos.arquivados');

            // VER DOCUMENTO ARQUIVADO
            $router->get('/documentos/arquivado/{id:\d+}', 'Admin\DocumentosAdminController@verArquivado')
                    ->name('admin.documentos.arquivado.ver');

            // RECUPERAR DOCUMENTO ARQUIVADO
            $router->post('/documentos/arquivado/{id:\d+}/recuperar', 'Admin\DocumentosAdminController@recuperarArquivado')
                    ->name('admin.documentos.arquivado.recuperar');

            // ELIMINAR
            $router->get('/documentos/eliminar/{id:\d+}', 'Admin\DocumentosAdminController@eliminar')
                    ->name('admin.documentos.apagar');

            // DOWNLOAD
            $router->get('/documentos/download/{id:\d+}', 'Admin\DocumentosAdminController@download')
                    ->name('admin.documentos.download');

            // DOWNLOAD MÚLTIPLO
            $router->post('/documentos/download-multiple', 'Admin\DocumentosAdminController@downloadMultiple')
                    ->name('admin.documentos.download_multiple');

            // AJAX — corrigido o prefixo duplicado
            $router->get('/documentos/existentes', 'Admin\DocumentosAdminController@ficheirosExistentes');

            // DOCUMENTO TIPOS
            $router->get('/documento-tipos', 'Admin\DocumentoTiposAdminController@index');
            $router->get('/documento-tipos/criar', 'Admin\DocumentoTiposAdminController@criar');
            $router->post('/documento-tipos/criar', 'Admin\DocumentoTiposAdminController@criarSubmit');

            $router->get('/documento-tipos/editar/{id}', 'Admin\DocumentoTiposAdminController@editar');
            $router->post('/documento-tipos/editar/{id}', 'Admin\DocumentoTiposAdminController@editarSubmit');

            $router->get('/documento-tipos/apagar/{id}', 'Admin\DocumentoTiposAdminController@apagar');

            // ===============================
            // TRAMITAÇÃO DE DOCUMENTOS
            // ===============================
            // Dashboard de tramitação
            $router->get('/tramitacao/dashboard', 'Admin\TramitacaoAdminController@dashboard')
                    ->name('admin.tramitacao.dashboard');

            // Lista de documentos em tramitação
            $router->get('/tramitacao', 'Admin\TramitacaoAdminController@lista')
                    ->name('admin.tramitacao.lista');

            // Gestão de áreas
            $router->get('/tramitacao/areas', 'Admin\DocumentoAreasAdminController@index')
                    ->name('admin.tramitacao.areas');

            // Ver tramitação de um documento (DINÂMICA — SEMPRE NO FIM)
            $router->get('/tramitacao/{documento_id}', 'Admin\TramitacaoAdminController@index')
                    ->name('admin.tramitacao.index');

            // Encaminhar documento
            $router->post('/tramitacao/encaminhar', 'Admin\TramitacaoAdminController@encaminhar')
                    ->name('admin.tramitacao.encaminhar');

            // Adicionar comentário
            $router->post('/tramitacao/comentar', 'Admin\TramitacaoAdminController@comentar')
                    ->name('admin.tramitacao.comentar');

            // Alterar estado
            $router->post('/tramitacao/estado', 'Admin\TramitacaoAdminController@estado')
                    ->name('admin.tramitacao.estado');

            // Ver anexo
            $router->get('/tramitacao/anexo/{historicoId}/{ficheiro}', 'Admin\TramitacaoAdminController@verAnexo')
                    ->name('admin.tramitacao.ver_anexo');

            // AJAX
            $router->post('/documento-tipos/criar-ajax', 'Admin\DocumentoTiposAdminController@criarAjax');
            $router->post('/documento-tipos/editar-ajax/{id}', 'Admin\DocumentoTiposAdminController@editarAjax');
            $router->post('/documento-tipos/apagar-ajax/{id}', 'Admin\DocumentoTiposAdminController@apagarAjax');

            // PERMISSÕES
            $router->get('/permissoes', 'Admin\PermissoesAdminController@index')->name('admin.permissions.index');
            $router->get('/permissoes/criar', 'Admin\PermissoesAdminController@criar')->name('admin.permissions.create');
            $router->post('/permissoes/criar', 'Admin\PermissoesAdminController@criarSubmit');
            $router->get('/permissoes/editar/{id}', 'Admin\PermissoesAdminController@editar')->name('admin.permissions.edit');
            $router->post('/permissoes/editar/{id}', 'Admin\PermissoesAdminController@editarSubmit');
            $router->get('/permissoes/apagar/{id}', 'Admin\PermissoesAdminController@apagar')->name('admin.permissions.delete');
            $router->get('/permissoes/sincronizar', 'Admin\PermissoesAdminController@sincronizar');
            $router->post('/perfis/permissoes/{id}', 'Admin\PerfisAdminController@permissoesSubmit');

            // LOGS
            $router->get('/logs', 'Admin\LogsSistemaAdminController@index')->name('admin.logs.index');
            $router->get('/logs/{id}', 'Admin\LogsSistemaAdminController@detalhes')->name('admin.logs.details');

            // AUDITORIA
            $router->get('/auditoria', 'Admin\AuditoriaAdminController@index')->name('admin.auditoria.index');
            $router->get('/auditoria/exportar', 'Admin\AuditoriaAdminController@exportar')
                    ->name('admin.auditoria.exportar');
            $router->get('/auditoria/dashboard', 'Admin\AuditoriaAdminController@dashboardAuditoria')
                    ->name('admin.auditoria.dashboard');
            $router->get('/auditoria/{id:\d+}', 'Admin\AuditoriaAdminController@detalhes')
                    ->name('admin.auditoria.details');

            $router->get('/admin/auditoria', 'Admin\AuditoriaAdminController@index');
            $router->get('/admin/auditoria/ver/{id}', 'Admin\AuditoriaAdminController@ver');
            $router->get('/admin/auditoria/restaurar/{id}', 'Admin\AuditoriaAdminController@restaurar');
            $router->get('/admin/auditoria/exportar/{id}', 'Admin\AuditoriaAdminController@exportar');

            // ============================================================
            // BACKUPS
            // ============================================================
            // Página principal
            $router->get('/backups', 'Admin\BackupsAdminController@index')->name('admin.backups.index');

            // Dashboard e Logs
            $router->get('/backups/dashboard', 'Admin\BackupsAdminController@dashboard');
            $router->get('/backups/logs', 'Admin\BackupsAdminController@logs');

            // Base de Dados
            $router->get('/backups/bd/criar', 'Admin\BackupsAdminController@criarBD');
            $router->get('/backups/bd/restaurar/{ficheiro:.+}', 'Admin\BackupsAdminController@restaurarConfirmar');
            $router->post('/backups/bd/restaurar/{ficheiro:.+}', 'Admin\BackupsAdminController@restaurarExecutar');
            $router->get('/backups/bd/download/{ficheiro:.+}', 'Admin\BackupsAdminController@download');
            $router->get('/backups/bd/delete/{ficheiro:.+}', 'Admin\BackupsAdminController@delete');

            // Ficheiros
            $router->get('/backups/files/criar', 'Admin\BackupsAdminController@criarFiles');
            $router->get('/backups/files/restaurar/{ficheiro:.+}', 'Admin\BackupsAdminController@restaurarFilesConfirmar');
            $router->post('/backups/files/restaurar/{ficheiro:.+}', 'Admin\BackupsAdminController@restaurarFilesExecutar');
            $router->get('/backups/files/download/{ficheiro:.+}', 'Admin\BackupsAdminController@download');
            $router->get('/backups/files/delete/{ficheiro:.+}', 'Admin\BackupsAdminController@delete');

            // AGENDAMENTOS
            $router->get('/agendamentos', 'Admin\\AgendamentosController@index');
            $router->get('/agendamentos/ver/{ficheiro}', 'Admin\\AgendamentosController@ver');
            $router->get('/agendamentos/editar/{ficheiro}', 'Admin\\AgendamentosController@editar');
            $router->post('/agendamentos/editar/{ficheiro}', 'Admin\\AgendamentosController@editarPost');
            $router->get('/agendamentos/ativar/{ficheiro}', 'Admin\\AgendamentosController@ativar');
            $router->get('/agendamentos/desativar/{ficheiro}', 'Admin\\AgendamentosController@desativar');
            $router->get('/agendamentos/eliminar/{ficheiro}', 'Admin\\AgendamentosController@eliminar');
            $router->get('/agendamentos/criar', 'Admin\\AgendamentosController@criar');
            $router->post('/agendamentos/criar', 'Admin\\AgendamentosController@criarPost');
        });
