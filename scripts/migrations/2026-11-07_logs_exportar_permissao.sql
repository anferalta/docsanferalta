-- Registar a permissão de exportação sem apagar permissões ou atribuições existentes.
-- Executar uma única vez em produção, após backup, se o código ainda não existir.
-- Não concede a permissão a nenhum perfil: a atribuição continua a ser feita em Perfis.
INSERT INTO permissoes (codigo, descricao)
SELECT 'admin.logs.exportar', 'Exportar logs do sistema'
WHERE NOT EXISTS (SELECT 1 FROM permissoes WHERE codigo = 'admin.logs.exportar');
