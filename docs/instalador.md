# Instalador da aplicação

Para o procedimento completo, consultar o [guia de instalação passo a passo](guia-instalacao.md).

Numa cópia limpa do produto, executar na raiz:

```text
php install.php
```

O assistente de terminal verifica PHP e extensões, instala as dependências pelo lock se ainda não existirem, recolhe URL, ligação MySQL e administrador, verifica a base vazia e apresenta um plano sem passwords antes de instalar. SMTP é opcional. O instalador não funciona por HTTP.

É necessário ter PHP 8.3+ de 64 bits, Composer, uma base MySQL vazia e uma conta com os privilégios necessários para criar o esquema. O instalador não cria o serviço MySQL, contas de sistema, domínio ou certificado TLS. Em produção, exige HTTPS, utilizador MySQL dedicado e password não vazia. A conta de execução do PHP precisa de acesso aos ficheiros privados e de escrita no armazenamento; ajustar a conta/grupo/ACL conforme o servidor. A configuração do Apache ou Nginx continua a servir exclusivamente `public/`.

Passwords de MySQL e SMTP são indicadas por caminhos de ficheiros privados, sem serem escritas nos argumentos ou apresentadas no resumo. A password inicial do administrador pode ser lida de outro ficheiro ou gerada pelo assistente. Depois da instalação, consultar `secrets/initial-admin-password.txt`, entrar e alterar a password no perfil. A chave de recuperação é um ficheiro separado, `secrets/recovery.key`, com uma cópia `recovery.escrow` e proteção contra escrita. Guardar uma cópia fora do servidor.

## Verificação e automação

```text
php install.php --check
php install.php --config=/caminho/privado/install.json --plan
php install.php --config=/caminho/privado/install.json --yes
```

Usar `install.example.json` como referência, ajustando os caminhos de segredos. No Windows, podem ser usados caminhos como `C:/segredos/database-password.txt`. Não guardar passwords reais no ficheiro JSON nem versionar a configuração privada. Sem `ADMIN_PASSWORD_FILE`, o instalador gera uma password individual.

`--check` não altera ficheiros nem liga à base de dados. `--plan` verifica configuração e base vazia sem gravar nem aplicar migrações; se faltar vendor, executar primeiro Composer. Na instalação normal, se Composer não estiver no PATH, usar `--composer-phar=/caminho/composer.phar`. A instalação de dependências requer acesso aos repositórios ou cache previamente preparada.

## Proteções e falhas

- Recusa uma pasta com `.env` existente e uma base com tabelas. Na instalação atual, usar apenas `--check`; as atualizações usam `php cli/maintenance.php migrate`.
- Cria apenas o esquema, referências mínimas e o administrador indicado. Não importa o dump operacional nem configura credenciais universais.
- Publica `.env` apenas depois de preparar esquema e administrador, sem substituir ficheiros existentes. A publicação exige suporte a hard links no sistema de ficheiros (por exemplo NTFS ou ext4).
- Regista o progresso em `storage/installation-state.json`. Enquanto existir `.installation-incomplete`, a aplicação devolve manutenção. Não apagar a marca para abrir uma instalação incompleta.
- Uma interrupção pode ser retomada com `--config=... --resume --yes`, usando o mesmo destino registado. As migrações concluídas não são repetidas e o administrador tem de corresponder ao da instalação interrompida. Antes de aplicar migrações ainda pendentes num destino já preparado é criado um backup.
- Se houver uma migração DDL interrompida, o histórico bloqueia o avanço. Não limpar esse estado para forçar a execução; resolver a falha e recuperar ou preparar um novo destino vazio conforme o guia de recuperação.

Após instalar, validar HTTPS com `scripts/validar_servidor.ps1` (Windows), testar SMTP com `php cli/smtp-check.php` se configurado e criar/ensaiar um backup completo antes de disponibilizar a aplicação à entidade. O instalador não envia emails.

O instalador passou em 25 verificações MySQL com bases temporárias: instalação inicial, administrador, configuração sem passwords, chaves privadas, recusa de dados existentes e retoma antes/depois da publicação de `.env`. A instalação em uso foi apenas verificada com `--check`.
