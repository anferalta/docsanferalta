<?php

return [

    // ============================================
    // DASHBOARD
    // ============================================
    ['codigo' => 'admin.dashboard.ver', 'descricao' => 'Aceder ao dashboard'],


    // ============================================
    // UTILIZADORES
    // ============================================
    ['codigo' => 'admin.utilizadores.ver', 'descricao' => 'Listar utilizadores'],
    ['codigo' => 'admin.utilizadores.criar', 'descricao' => 'Criar utilizadores'],
    ['codigo' => 'admin.utilizadores.editar', 'descricao' => 'Editar utilizadores'],
    ['codigo' => 'admin.utilizadores.apagar', 'descricao' => 'Apagar utilizadores'],


    // ============================================
    // PERFIS
    // ============================================
    ['codigo' => 'admin.perfis.ver', 'descricao' => 'Listar perfis'],
    ['codigo' => 'admin.perfis.criar', 'descricao' => 'Criar perfis'],
    ['codigo' => 'admin.perfis.editar', 'descricao' => 'Editar perfis'],
    ['codigo' => 'admin.perfis.apagar', 'descricao' => 'Apagar perfis'],
    ['codigo' => 'admin.perfis.permissoes', 'descricao' => 'Gerir permissões do perfil'],


    // ============================================
    // PERMISSÕES
    // ============================================
    ['codigo' => 'admin.permissoes.ver', 'descricao' => 'Listar permissões'],
    ['codigo' => 'admin.permissoes.criar', 'descricao' => 'Criar permissões'],
    ['codigo' => 'admin.permissoes.editar', 'descricao' => 'Editar permissões'],
    ['codigo' => 'admin.permissoes.apagar', 'descricao' => 'Apagar permissões'],
    ['codigo' => 'admin.permissoes.sincronizar', 'descricao' => 'Sincronizar permissões'],


    // ============================================
    // BACKUPS - BASE DE DADOS
    // ============================================
    ['codigo' => 'admin.backups.bd.ver', 'descricao' => 'Ver backups da base de dados'],
    ['codigo' => 'admin.backups.bd.criar', 'descricao' => 'Criar backup da base de dados'],
    ['codigo' => 'admin.backups.bd.download', 'descricao' => 'Descarregar backup da base de dados'],
    ['codigo' => 'admin.backups.bd.apagar', 'descricao' => 'Apagar backup da base de dados'],
    ['codigo' => 'admin.backups.bd.restaurar', 'descricao' => 'Restaurar backup da base de dados'],
    ['codigo' => 'admin.backups.bd.restaurar.confirmar', 'descricao' => 'Confirmar restauro da base de dados'],
    ['codigo' => 'admin.backups.bd.restaurar.executar', 'descricao' => 'Executar restauro da base de dados'],


    // ============================================
    // BACKUPS - FICHEIROS
    // ============================================
    ['codigo' => 'admin.backups.files.ver', 'descricao' => 'Ver backups de ficheiros'],
    ['codigo' => 'admin.backups.files.criar', 'descricao' => 'Criar backup de ficheiros'],
    ['codigo' => 'admin.backups.files.download', 'descricao' => 'Descarregar backup de ficheiros'],
    ['codigo' => 'admin.backups.files.apagar', 'descricao' => 'Apagar backup de ficheiros'],


    // ============================================
    // BACKUPS - AGENDAMENTOS
    // ============================================
    ['codigo' => 'admin.backups.agendamentos.ver', 'descricao' => 'Ver agendamentos de backups'],
    ['codigo' => 'admin.backups.agendamentos.editar', 'descricao' => 'Editar agendamentos de backups'],


    // ============================================
    // DOCUMENTOS
    // ============================================
    ['codigo' => 'admin.documentos.ver', 'descricao' => 'Listar documentos'],
    ['codigo' => 'admin.documentos.criar', 'descricao' => 'Criar documentos'],
    ['codigo' => 'admin.documentos.editar', 'descricao' => 'Editar documentos'],
    ['codigo' => 'admin.documentos.apagar', 'descricao' => 'Apagar documentos'],
    ['codigo' => 'admin.documentos.download', 'descricao' => 'Descarregar documentos'],

    // ⭐ NOVA PERMISSÃO — necessária para abrir a página com abas
    ['codigo' => 'admin.documentos.ver_detalhe', 'descricao' => 'Ver detalhe do documento'],


    // ============================================
    // TIPOS DE DOCUMENTO
    // ============================================
    ['codigo' => 'admin.documento-tipos.ver', 'descricao' => 'Listar tipos de documento'],
    ['codigo' => 'admin.documento-tipos.criar', 'descricao' => 'Criar tipos de documento'],
    ['codigo' => 'admin.documento-tipos.editar', 'descricao' => 'Editar tipos de documento'],
    ['codigo' => 'admin.documento-tipos.apagar', 'descricao' => 'Apagar tipos de documento'],


    // ============================================
    // LOGS
    // ============================================
    ['codigo' => 'admin.logs.ver', 'descricao' => 'Ver logs do sistema'],
    ['codigo' => 'admin.logs.apagar', 'descricao' => 'Apagar logs do sistema'],


    // ============================================
    // AUDITORIA
    // ============================================
    ['codigo' => 'admin.auditoria.ver', 'descricao' => 'Ver auditoria do sistema'],


    // ============================================
    // TRAMITAÇÃO — DOCUMENTOS
    // ============================================
    ['codigo' => 'admin.tramitacao.ver', 'descricao' => 'Ver tramitação dos documentos'],
    ['codigo' => 'admin.tramitacao.encaminhar', 'descricao' => 'Encaminhar documentos para áreas'],
    ['codigo' => 'admin.tramitacao.comentar', 'descricao' => 'Adicionar comentários na tramitação'],
    ['codigo' => 'admin.tramitacao.mudar_estado', 'descricao' => 'Alterar estado dos documentos'],
    ['codigo' => 'admin.tramitacao.arquivar', 'descricao' => 'Arquivar documentos'],

    // ⭐ NOVA PERMISSÃO — para o dashboard de tramitação
    ['codigo' => 'admin.tramitacao.dashboard', 'descricao' => 'Ver dashboard de tramitação'],


    // ============================================
    // TRAMITAÇÃO — ÁREAS
    // ============================================
    ['codigo' => 'admin.tramitacao.areas.ver', 'descricao' => 'Listar áreas de tramitação'],
    ['codigo' => 'admin.tramitacao.areas.criar', 'descricao' => 'Criar áreas de tramitação'],
    ['codigo' => 'admin.tramitacao.areas.editar', 'descricao' => 'Editar áreas de tramitação'],
    ['codigo' => 'admin.tramitacao.areas.apagar', 'descricao' => 'Apagar áreas de tramitação'],

];
