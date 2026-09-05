-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 01-Set-2026 às 11:42
-- Versão do servidor: 9.1.0
-- versão do PHP: 8.4.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `anferaltadocs`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `auditoria`
--

DROP TABLE IF EXISTS `auditoria`;
CREATE TABLE IF NOT EXISTS `auditoria` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilizador_id` int DEFAULT NULL,
  `acao` varchar(255) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `detalhes` text,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_auditoria_user` (`utilizador_id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Extraindo dados da tabela `auditoria`
--

INSERT INTO `auditoria` (`id`, `utilizador_id`, `acao`, `ip`, `detalhes`, `criado_em`) VALUES
(1, 87, 'logout', '127.0.0.1', NULL, '2026-09-01 12:16:47'),
(2, 87, 'login', '127.0.0.1', NULL, '2026-09-01 12:17:16'),
(3, 3, 'logout', '127.0.0.1', NULL, '2026-09-01 12:17:28'),
(4, 3, 'login', '127.0.0.1', NULL, '2026-09-01 12:17:46'),
(5, 3, 'logout', '127.0.0.1', NULL, '2026-09-01 12:40:58');

-- --------------------------------------------------------

--
-- Estrutura da tabela `auditoria_eliminacoes`
--

DROP TABLE IF EXISTS `auditoria_eliminacoes`;
CREATE TABLE IF NOT EXISTS `auditoria_eliminacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tabela` varchar(100) NOT NULL,
  `acao` varchar(50) DEFAULT NULL,
  `registo_id` int NOT NULL,
  `dados` json NOT NULL,
  `apagado_por` int DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `documentos`
--

DROP TABLE IF EXISTS `documentos`;
CREATE TABLE IF NOT EXISTS `documentos` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ficheiro` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ficheiro_original` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int DEFAULT NULL,
  `hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_por` int NOT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `estado` tinyint(1) DEFAULT '1',
  `estado_atual` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'novo',
  `area_atual_id` int DEFAULT NULL,
  `arquivado_em` datetime DEFAULT NULL,
  `arquivado_por_id` int DEFAULT NULL,
  `area_atual_desde` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_documentos_utilizador` (`criado_por`),
  KEY `tipo_id` (`tipo_id`),
  KEY `fk_documentos_area_atual` (`area_atual_id`),
  KEY `fk_documentos_arquivado_por` (`arquivado_por_id`),
  KEY `fk_documentos_estado` (`estado_atual`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `documentos`
--

INSERT INTO `documentos` (`id`, `tipo_id`, `titulo`, `ficheiro`, `caminho`, `ficheiro_original`, `mime_type`, `tamanho`, `hash`, `criado_por`, `criado_em`, `estado`, `estado_atual`, `area_atual_id`, `arquivado_em`, `arquivado_por_id`, `area_atual_desde`) VALUES
(1, 1, 'Teste a BD', '', NULL, NULL, NULL, NULL, NULL, 87, '2026-09-01 12:19:18', 1, 'analise', 4, NULL, NULL, '2026-09-01 11:33:15');

-- --------------------------------------------------------

--
-- Estrutura da tabela `documentos_logs`
--

DROP TABLE IF EXISTS `documentos_logs`;
CREATE TABLE IF NOT EXISTS `documentos_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `documento_id` int NOT NULL,
  `utilizador_id` int NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `documento_areas`
--

DROP TABLE IF EXISTS `documento_areas`;
CREATE TABLE IF NOT EXISTS `documento_areas` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `prazo_resposta` int DEFAULT '3',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Extraindo dados da tabela `documento_areas`
--

INSERT INTO `documento_areas` (`id`, `nome`, `codigo`, `descricao`, `ativo`, `criado_em`, `atualizado_em`, `prazo_resposta`) VALUES
(1, 'Secretaria', 'SEC', 'Receção e distribuição de documentos', 1, '2026-04-18 21:00:15', '2026-06-04 22:08:35', 1),
(2, 'Direção', 'DIR', 'Direção geral', 1, '2026-04-18 21:00:15', '2026-06-06 13:34:26', 5),
(3, 'Financeira', 'FIN', 'Departamento financeiro', 1, '2026-04-18 21:00:15', '2026-06-04 22:08:11', 2),
(4, 'Recursos Humanos', 'RH', 'Gestão de pessoal', 1, '2026-04-18 21:00:15', '2026-06-04 22:09:18', 2),
(5, 'Arquivo', 'ARQ', 'Arquivo e documentação', 1, '2026-04-18 21:00:15', '2026-07-22 19:55:44', 3),
(8, 'Gabinete Jurídico ', 'JUR', 'Gabinete jurídico ', 1, '2026-05-23 14:17:30', '2026-06-04 22:09:45', 2);

-- --------------------------------------------------------

--
-- Estrutura da tabela `documento_estados`
--

DROP TABLE IF EXISTS `documento_estados`;
CREATE TABLE IF NOT EXISTS `documento_estados` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int UNSIGNED NOT NULL DEFAULT '1',
  `final` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `documento_estados`
--

INSERT INTO `documento_estados` (`id`, `codigo`, `nome`, `ordem`, `final`, `ativo`) VALUES
(1, 'pendente', 'Pendente', 1, 0, 1),
(2, 'em_tramitacao', 'Em Tramitação', 2, 0, 1),
(3, 'analise', 'Em Análise', 3, 0, 1),
(4, 'concluido', 'Concluído', 4, 0, 1),
(5, 'devolvido', 'Devolvido', 5, 0, 1),
(6, 'arquivado', 'Arquivado', 6, 1, 1),
(13, 'novo', 'Novo', 0, 0, 1);

-- --------------------------------------------------------

--
-- Estrutura da tabela `documento_ficheiros`
--

DROP TABLE IF EXISTS `documento_ficheiros`;
CREATE TABLE IF NOT EXISTS `documento_ficheiros` (
  `id` int NOT NULL AUTO_INCREMENT,
  `documento_id` int NOT NULL,
  `ficheiro` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ficheiro_original` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caminho` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int DEFAULT NULL,
  `mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `documento_id` (`documento_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `documento_ficheiros`
--

INSERT INTO `documento_ficheiros` (`id`, `documento_id`, `ficheiro`, `ficheiro_original`, `caminho`, `tamanho`, `mime`, `criado_em`) VALUES
(1, 1, '6a96b4b650e53_relatorio1_sla.pdf', 'relatorio1_sla.pdf', '2026/09/01/', 21696, 'application/pdf', '2026-09-01 11:19:18');

-- --------------------------------------------------------

--
-- Estrutura da tabela `documento_tipos`
--

DROP TABLE IF EXISTS `documento_tipos`;
CREATE TABLE IF NOT EXISTS `documento_tipos` (
  `tipo_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  PRIMARY KEY (`tipo_id`)
) ENGINE=MyISAM AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Extraindo dados da tabela `documento_tipos`
--

INSERT INTO `documento_tipos` (`tipo_id`, `nome`) VALUES
(1, 'Carta'),
(2, 'Ofício'),
(3, 'Pedido'),
(4, 'Relatório'),
(5, 'Memorando'),
(6, 'Circular'),
(29, 'Exposiçao'),
(35, 'Finanças');

-- --------------------------------------------------------

--
-- Estrutura da tabela `documento_tramitacao`
--

DROP TABLE IF EXISTS `documento_tramitacao`;
CREATE TABLE IF NOT EXISTS `documento_tramitacao` (
  `id` int NOT NULL AUTO_INCREMENT,
  `documento_id` int NOT NULL,
  `utilizador_id` int NOT NULL,
  `acao` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `area_id` int DEFAULT NULL,
  `comentario` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `estado` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `documento_id` (`documento_id`),
  KEY `utilizador_id` (`utilizador_id`),
  KEY `area_id` (`area_id`),
  KEY `estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `documento_tramitacao`
--

INSERT INTO `documento_tramitacao` (`id`, `documento_id`, `utilizador_id`, `acao`, `area_id`, `comentario`, `estado`, `criado_em`) VALUES
(1, 1, 3, 'COMENTARIO', NULL, 'Teste a BD', 'novo', '2026-09-01 11:32:52'),
(2, 1, 3, 'ENCAMINHADO', 4, 'Teste a BD.', 'em_tramitacao', '2026-09-01 11:33:15'),
(3, 1, 3, 'ESTADO', 4, 'Teste a BD.', 'analise', '2026-09-01 11:33:37');

-- --------------------------------------------------------

--
-- Estrutura da tabela `documento_tramitacao_anexos`
--

DROP TABLE IF EXISTS `documento_tramitacao_anexos`;
CREATE TABLE IF NOT EXISTS `documento_tramitacao_anexos` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `tramitacao_id` int UNSIGNED NOT NULL,
  `ficheiro` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ficheiro_original` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caminho` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamanho` int DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tramitacao_id` (`tramitacao_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `logs_sistema`
--

DROP TABLE IF EXISTS `logs_sistema`;
CREATE TABLE IF NOT EXISTS `logs_sistema` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) NOT NULL,
  `mensagem` text NOT NULL,
  `ficheiro` varchar(255) DEFAULT NULL,
  `linha` int DEFAULT NULL,
  `detalhes` text,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `metodo` varchar(10) DEFAULT NULL,
  `utilizador_id` int DEFAULT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `notificacoes`
--

DROP TABLE IF EXISTS `notificacoes`;
CREATE TABLE IF NOT EXISTS `notificacoes` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` int UNSIGNED NOT NULL,
  `documento_id` bigint UNSIGNED DEFAULT NULL,
  `tipo` varchar(50) NOT NULL,
  `mensagem` text NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `lida` tinyint(1) NOT NULL DEFAULT '0',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Extraindo dados da tabela `notificacoes`
--

INSERT INTO `notificacoes` (`id`, `utilizador_id`, `documento_id`, `tipo`, `mensagem`, `url`, `lida`, `criado_em`) VALUES
(1, 87, 1, 'comentario', 'O documento \'Teste a BD\' recebeu um comentário.', '/admin/tramitacao/1', 0, '2026-09-01 11:32:52'),
(2, 87, 1, 'estado', 'O estado do documento \'Teste a BD\' foi alterado para \'analise\'.', '/admin/tramitacao/1', 0, '2026-09-01 11:33:37');

-- --------------------------------------------------------

--
-- Estrutura da tabela `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`(250)),
  KEY `token` (`token`(250))
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `perfis`
--

DROP TABLE IF EXISTS `perfis`;
CREATE TABLE IF NOT EXISTS `perfis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Extraindo dados da tabela `perfis`
--

INSERT INTO `perfis` (`id`, `nome`, `descricao`, `ativo`, `criado_em`) VALUES
(1, 'admin', 'Administrador do sistema', 1, '2026-02-17 18:03:49'),
(3, 'Gestor', 'Gestor de Sistema', 1, '2026-02-24 20:23:10'),
(4, 'Utilizador', 'Utilizador de Sistema', 1, '2026-02-24 20:36:31'),
(9, 'Supervisor', 'Supervisor de Sistema', 1, '2026-02-25 14:00:35');

-- --------------------------------------------------------

--
-- Estrutura da tabela `perfis_permissoes`
--

DROP TABLE IF EXISTS `perfis_permissoes`;
CREATE TABLE IF NOT EXISTS `perfis_permissoes` (
  `perfil_id` int NOT NULL,
  `permissao_id` int NOT NULL,
  PRIMARY KEY (`perfil_id`,`permissao_id`),
  KEY `fk_permissao` (`permissao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Extraindo dados da tabela `perfis_permissoes`
--

INSERT INTO `perfis_permissoes` (`perfil_id`, `permissao_id`) VALUES
(1, 1183),
(3, 1183),
(9, 1183),
(1, 1184),
(9, 1184),
(1, 1185),
(1, 1186),
(1, 1187),
(1, 1188),
(1, 1189),
(1, 1190),
(1, 1191),
(1, 1192),
(1, 1193),
(1, 1194),
(1, 1195),
(1, 1196),
(1, 1197),
(1, 1198),
(9, 1198),
(1, 1199),
(3, 1199),
(9, 1199),
(1, 1200),
(1, 1201),
(1, 1202),
(1, 1203),
(1, 1204),
(1, 1205),
(9, 1205),
(1, 1206),
(3, 1206),
(9, 1206),
(1, 1207),
(1, 1208),
(1, 1209),
(9, 1209),
(1, 1210),
(1, 1211),
(3, 1211),
(4, 1211),
(9, 1211),
(1, 1212),
(4, 1212),
(1, 1213),
(4, 1213),
(9, 1213),
(1, 1214),
(4, 1214),
(1, 1215),
(4, 1215),
(9, 1215),
(1, 1216),
(1, 1217),
(9, 1217),
(1, 1218),
(1, 1219),
(1, 1220),
(3, 1220),
(9, 1220),
(1, 1221),
(1, 1222),
(3, 1222),
(9, 1222),
(1, 1223),
(1, 1224),
(1, 1225),
(9, 1225),
(1, 1226),
(1, 1227),
(9, 1227),
(1, 1228),
(1, 1229),
(9, 1229),
(1, 1236),
(9, 1236),
(1, 1237),
(9, 1237),
(1, 1267),
(3, 1267),
(9, 1267),
(1, 1268),
(3, 1268),
(9, 1268),
(1, 1269),
(1, 1270),
(1, 1271),
(1, 1272),
(1, 1274),
(3, 1274),
(9, 1274),
(1, 1276),
(3, 1276),
(9, 1276),
(1, 1277),
(3, 1277),
(9, 1277),
(1, 1278),
(3, 1278),
(9, 1278),
(1, 1280),
(3, 1280),
(9, 1280),
(9, 1281),
(1, 1286),
(1, 1288),
(3, 1288),
(9, 1288);

-- --------------------------------------------------------

--
-- Estrutura da tabela `permissoes`
--

DROP TABLE IF EXISTS `permissoes`;
CREATE TABLE IF NOT EXISTS `permissoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(100) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=1291 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Extraindo dados da tabela `permissoes`
--

INSERT INTO `permissoes` (`id`, `codigo`, `descricao`) VALUES
(1183, 'admin.dashboard.ver', 'Aceder ao dashboard'),
(1184, 'admin.utilizadores.ver', 'Listar utilizadores'),
(1185, 'admin.utilizadores.criar', 'Criar utilizadores'),
(1186, 'admin.utilizadores.editar', 'Editar utilizadores'),
(1187, 'admin.utilizadores.apagar', 'Apagar utilizadores'),
(1188, 'admin.perfis.ver', 'Listar perfis'),
(1189, 'admin.perfis.criar', 'Criar perfis'),
(1190, 'admin.perfis.editar', 'Editar perfis'),
(1191, 'admin.perfis.apagar', 'Apagar perfis'),
(1192, 'admin.perfis.permissoes', 'Gerir permissões do perfil'),
(1193, 'admin.permissoes.ver', 'Listar permissões'),
(1194, 'admin.permissoes.criar', 'Criar permissões'),
(1195, 'admin.permissoes.editar', 'Editar permissões'),
(1196, 'admin.permissoes.apagar', 'Apagar permissões'),
(1197, 'admin.permissoes.sincronizar', 'Sincronizar permissões'),
(1198, 'admin.backups.bd.ver', 'Ver backups da base de dados'),
(1199, 'admin.backups.bd.criar', 'Criar backup da base de dados'),
(1200, 'admin.backups.bd.download', 'Descarregar backup da base de dados'),
(1201, 'admin.backups.bd.apagar', 'Apagar backup da base de dados'),
(1202, 'admin.backups.bd.restaurar', 'Restaurar backup da base de dados'),
(1203, 'admin.backups.bd.restaurar.confirmar', 'Confirmar restauro da base de dados'),
(1204, 'admin.backups.bd.restaurar.executar', 'Executar restauro da base de dados'),
(1205, 'admin.backups.files.ver', 'Ver backups de ficheiros'),
(1206, 'admin.backups.files.criar', 'Criar backup de ficheiros'),
(1207, 'admin.backups.files.download', 'Descarregar backup de ficheiros'),
(1208, 'admin.backups.files.apagar', 'Apagar backup de ficheiros'),
(1209, 'admin.backups.agendamentos.ver', 'Ver agendamentos de backups'),
(1210, 'admin.backups.agendamentos.editar', 'Editar agendamentos de backups'),
(1211, 'admin.documentos.ver', 'Listar documentos'),
(1212, 'admin.documentos.criar', 'Criar documentos'),
(1213, 'admin.documentos.editar', 'Editar documentos'),
(1214, 'admin.documentos.apagar', 'Apagar documentos'),
(1215, 'admin.documentos.download', 'Descarregar documentos'),
(1216, 'admin.documento-tipos.ver', 'Listar tipos de documento'),
(1217, 'admin.documento-tipos.criar', 'Criar tipos de documento'),
(1218, 'admin.documento-tipos.editar', 'Editar tipos de documento'),
(1219, 'admin.documento-tipos.apagar', 'Apagar tipos de documento'),
(1220, 'admin.logs.ver', 'Ver logs do sistema'),
(1221, 'admin.logs.apagar', 'Apagar logs do sistema'),
(1222, 'admin.auditoria.ver', 'Ver auditoria do sistema'),
(1223, 'admin.config.ver', 'Ver configurações'),
(1224, 'admin.config.editar', 'Editar configurações'),
(1225, 'documentos.ver', 'Ver documentos'),
(1226, 'documentos.criar', 'Criar documentos'),
(1227, 'documentos.editar', 'Editar documentos'),
(1228, 'documentos.apagar', 'Apagar documentos'),
(1229, 'documentos.download', 'Descarregar documentos'),
(1236, 'admin.utilizadores.aprovar', 'Aprovar utilizadores'),
(1237, 'admin.utilizadores.bloquear', 'Bloquear utilizadores'),
(1267, 'admin.tramitacao.ver', 'Aceder à tramitação'),
(1268, 'admin.tramitacao.dashboard', 'Ver dashboard de tramitação'),
(1269, 'admin.tramitacao.areas.ver', 'Listar áreas de tramitação'),
(1270, 'admin.tramitacao.areas.criar', 'Criar áreas de tramitação'),
(1271, 'admin.tramitacao.areas.editar', 'Editar áreas de tramitação'),
(1272, 'admin.tramitacao.areas.apagar', 'Apagar áreas de tramitação'),
(1274, 'admin.documentos.ver_todos', 'Ver todos documentos'),
(1276, 'admin.tramitacao.encaminhar', 'Encaminhar Documento'),
(1277, 'admin.tramitacao.comentar', 'Comentar Documento'),
(1278, 'admin.tramitacao.estado', 'Estado do Documento'),
(1280, 'admin.documentos.arquivados.ver', 'Ver documentos arquivados'),
(1281, 'admin.documentos.arquivados.recuperar', 'Recuperar documentos arquivados'),
(1286, 'admin.documentos.arquivar', 'Arquivar documentos'),
(1288, 'admin.relatorios.ver', 'Relatórios');

-- --------------------------------------------------------

--
-- Estrutura da tabela `recuperacao_password`
--

DROP TABLE IF EXISTS `recuperacao_password`;
CREATE TABLE IF NOT EXISTS `recuperacao_password` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` int UNSIGNED NOT NULL,
  `token` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expira_em` datetime NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `utilizador_id` (`utilizador_id`),
  KEY `token` (`token`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `utilizadores`
--

DROP TABLE IF EXISTS `utilizadores`;
CREATE TABLE IF NOT EXISTS `utilizadores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `perfil_id` int NOT NULL,
  `ultimo_login` datetime DEFAULT NULL,
  `tentativas_falhadas` int DEFAULT '0',
  `ativo` tinyint(1) DEFAULT '1',
  `aprovado_por` int DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Extraindo dados da tabela `utilizadores`
--

INSERT INTO `utilizadores` (`id`, `nome`, `email`, `password`, `perfil_id`, `ultimo_login`, `tentativas_falhadas`, `ativo`, `aprovado_por`, `aprovado_em`, `criado_em`) VALUES
(2, 'Administrador', 'admin@admin.com', '$2y$12$jPzCzZNPBbd5m33N6Xb4yeX5LVDZVXwp0rTC6aOHwTqAklgymf0.i', 1, '2026-04-06 20:01:05', 0, 1, NULL, '2026-04-07 17:10:28', '2026-02-06 21:53:40'),
(3, 'Tony Almeida ', 'tony.almeida@anferalta.com', '$2y$10$d3qcMm482tKiuS98CtBPW.gf1E3fJyB0bUY149NLBXnJ1RKVEDb.y', 1, '2026-04-06 19:04:43', 0, 1, NULL, '2026-04-07 17:10:18', '2026-02-07 18:42:41'),
(4, 'Antonio', 'anferalta@gmail.com', '$2y$12$9GNk59my7060OVJX4Q7DTOPW5TR1UW0VQ06DBhx96r0y1cRRPYKfu', 1, NULL, 0, 0, NULL, '2026-04-07 17:11:08', '2026-02-15 19:44:41'),
(90, 'Daniel Almeida', 'daniel.almeida@anferalta.com', '$2y$12$nmB7euHRgNnUiZpLXht9geq39fUvEHiPfaPf7ToMYx3M1BQ1tLSZ6', 3, NULL, 0, 1, 3, '2026-08-20 11:59:47', '2026-08-20 11:50:03'),
(91, 'António Almeida', 'antonio.almeida@anferalta.com', '$2y$12$LotyBUyH5mmIVB6RgDre/O9f9GSHmHYZ3KeTPmO15Dg3MdWM9yhui', 9, NULL, 0, 1, 3, '2026-08-21 18:43:39', '2026-08-21 18:32:31'),
(87, 'Gisela Freitas', 'gisela.freita@anferalta.com', '$2y$12$pIly1.7oK0STG5jt.e.2OeOZPcz5ZZ.HEIQg0HpcBNilZIfF3dxGe', 4, NULL, 0, 1, 3, '2026-07-13 21:25:01', '2026-07-13 21:22:36'),
(88, 'Gisela Freitas', 'gisela.freitas@anferalta.com', '$2y$12$0r3jvOchHIw17c/zidQUFukcL.pS2r5vq9qCYXJhfYnGPI6.IDM7u', 1, NULL, 0, 0, NULL, NULL, '2026-07-23 21:15:44');

-- --------------------------------------------------------

--
-- Estrutura da tabela `utilizadores_permissoes`
--

DROP TABLE IF EXISTS `utilizadores_permissoes`;
CREATE TABLE IF NOT EXISTS `utilizadores_permissoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilizador_id` int NOT NULL,
  `permissao_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `utilizador_id` (`utilizador_id`),
  KEY `permissao_id` (`permissao_id`)
) ENGINE=MyISAM AUTO_INCREMENT=178 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Extraindo dados da tabela `utilizadores_permissoes`
--

INSERT INTO `utilizadores_permissoes` (`id`, `utilizador_id`, `permissao_id`) VALUES
(165, 2, 0),
(166, 2, 0),
(167, 2, 0),
(168, 2, 0),
(169, 2, 12),
(170, 2, 13),
(171, 3, 12),
(172, 3, 13),
(173, 1, 1288),
(174, 2, 1276),
(175, 2, 1277),
(176, 2, 1278),
(177, 2, 1286);

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `logs_sistema`
--
ALTER TABLE `logs_sistema` ADD FULLTEXT KEY `mensagem` (`mensagem`,`detalhes`);

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `perfis_permissoes`
--
ALTER TABLE `perfis_permissoes`
  ADD CONSTRAINT `fk_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `perfis` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_permissao` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
