-- Auditoria: migração preparada para execução manual numa janela de manutenção.
-- Não é executada pela aplicação. Fazer backup antes de aplicar em produção.
-- A conversão pode bloquear as tabelas durante a reconstrução e requer espaço livre.
-- Confirmar previamente com SHOW TABLE STATUS os motores efetivos em produção.
-- InnoDB é necessário para rollback conjunto das alterações e da sua auditoria.
ALTER TABLE auditoria ENGINE = InnoDB;
ALTER TABLE auditoria_eliminacoes ENGINE = InnoDB;
