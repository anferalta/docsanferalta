-- Preparado para aplicação manual após backup, em janela de manutenção.
-- Confirmar os motores em produção. Não é executado pela aplicação.
-- Auditoria e auditoria_eliminacoes devem igualmente estar em InnoDB (migração da Auditoria).
ALTER TABLE utilizadores ENGINE = InnoDB;
ALTER TABLE utilizadores_permissoes ENGINE = InnoDB;
ALTER TABLE password_resets ENGINE = InnoDB;
-- Registo aditivo: não apaga permissões nem as atribui automaticamente.
INSERT INTO permissoes (codigo,descricao)
SELECT 'admin.utilizadores.aprovar','Aprovar utilizadores'
WHERE NOT EXISTS (SELECT 1 FROM permissoes WHERE codigo='admin.utilizadores.aprovar');
