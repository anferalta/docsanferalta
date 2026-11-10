# Instalação, migrações e recuperação completa

## Estado validado

Esquema limpo com 20 tabelas, migrações versionadas com checksums e recuperação integral em bases descartáveis foram ensaiados no MySQL 9.1 e PHP 8.4 de 64 bits. O código exige PHP 8.3 ou superior. O motor suportado nesta edição é MySQL; MariaDB ainda não foi homologado. O esquema usa InnoDB e `utf8mb4_unicode_ci`.

Os comandos seguintes são executados na raiz do projeto, por um operador autorizado. Nunca passar palavras-passe na linha de comandos. `MAIL_PASS_FILE`, `DB_PASS_FILE` e `BACKUP_PASSWORD_FILE` permitem ler ficheiros privados. Variáveis reais do processo prevalecem sobre `.env`.

## Instalação vazia

O assistente recomendado é `php install.php`; ver [guia do instalador](instalador.md). Os passos de baixo nível seguintes continuam disponíveis para operações administradas.

1. Preparar uma base MySQL vazia e um utilizador de instalação com privilégios DDL, DML e `LOCK TABLES` nessa base. Preparar as extensões indicadas no Composer e executar `composer install --no-dev --prefer-dist --optimize-autoloader`.
2. Criar `.env` a partir de `.env.example`, com a identidade desta instalação. Não copiar o `.env`, uploads ou dumps da instalação de desenvolvimento. Configurar uma chave aleatória de backup com pelo menos 32 caracteres num ficheiro privado.
3. Guardar a password inicial do administrador num ficheiro privado, com pelo menos 14 caracteres. Esse ficheiro não deve ser distribuído nem colocado na pasta pública.
4. Executar:

```text
php cli/maintenance.php install --admin-email=administrador@example.org --admin-password-file=/run/secrets/initial_admin_password --admin-name=Administrador
php cli/maintenance.php status
```

O instalador exige uma base vazia. Cria apenas perfis, permissões, estados de referência e o administrador indicado; não inclui pessoas ou documentos da instalação original. Configurar áreas e tipos de documento depois de entrar. Não existe uma password universal. A interface web não executa migrações nem cria automaticamente administradores.

Separar os privilégios do utilizador normal da aplicação dos privilégios do operador de manutenção na implantação definitiva. O ambiente WAMP local ainda usa a conta local já existente; isso não é um modelo de conta para servidores de clientes. Para tarefas de manutenção, fornecer a configuração apropriada por variáveis de processo numa sessão privada.

## Atualização automática e controlada

```text
php cli/maintenance.php status
php cli/maintenance.php migrate
```

`migrate` aplica por ordem apenas versões pendentes. Numa base existente, cria e verifica primeiro um backup completo encriptado. A aplicação fica em manutenção durante a operação e o MySQL usa um bloqueio próprio para impedir duas migrações concorrentes. A segunda execução não reaplica versões.

Os ficheiros SQL publicados são imutáveis: alterações de checksum são recusadas. MySQL faz commit implícito de DDL; por isso uma falha deixa a migração marcada como interrompida e impede continuação silenciosa. Nessa situação, investigar a causa e recuperar o backup num destino vazio, em vez de apagar a marcação para forçar o avanço. Não editar ficheiros de migração que já foram aplicados.

Na instalação local foram aplicadas `0001_initial.sql`, `0002_transactional_tables.sql` e `0003_reference_data.sql`. A primeira mantém tabelas existentes; a segunda converte os motores para InnoDB; a terceira adiciona referências sem substituir dados existentes. Um backup encriptado anterior às alterações encontra-se em `backups/Completos/`; o nome e SHA-256 foram registados pelo comando de manutenção.

## Backup completo

```text
php cli/maintenance.php backup
php cli/maintenance.php verify --archive=/backup/full-YYYYMMDD-HHMMSS-id.zip
```

Usar `cron/backup_completo.php` para o agendador do sistema operativo. O agendamento não é instalado automaticamente. Os antigos comandos de backup só de BD/ficheiros são legados e não substituem o conjunto completo.

O backup completo contém:

- Esquema, índices, contadores e todos os registos de todas as tabelas, incluindo o histórico de migrações.
- Documentos ativos, documentos arquivados, anexos de tramitação, uploads privados e configuração dos agendamentos.
- Salvaguarda dos uploads históricos encontrados nas pastas antigas `public/uploads`, `App/public/uploads` e `App/storage/documentos`, recuperados sob `storage/uploads/legacy/` para nunca reexpor esses ficheiros publicamente. Os carregamentos novos, incluindo AJAX, usam o armazenamento privado centralizado.
- Manifesto de versão, contagens e hashes SHA-256. Todas as entradas, incluindo o manifesto, são encriptadas com ZIP AES-256.

Os dados são exportados em fluxos JSONL com representação binária segura, sem converter valores nulos em strings nem interpretar conteúdo como SQL. Os dados das tabelas são protegidos por bloqueios READ, inclusive quando o backup de uma instalação antiga contém MyISAM; a aplicação e os cron cooperam com um bloqueio de manutenção no armazenamento. A espera por bloqueios SQL é limitada a 30 segundos. Não deve haver escritores externos a alterar os ficheiros privados por fora da aplicação.

O arquivo só é publicado depois de todas as entradas serem desencriptadas, lidas e comparadas com o manifesto. A verificação não precisa de a base original estar disponível. A biblioteca ZIP local foi testada também com tabelas vazias encriptadas.

Views, triggers, eventos e rotinas armazenadas não fazem parte da estrutura atual da aplicação. Se forem acrescentados, o backup recusa a execução até haver suporte explícito: não omite esses objetos silenciosamente.

Guardar uma cópia dos backups fora do servidor e uma cópia de segurança da chave de recuperação em local separado. A chave local ativa é indicada por `BACKUP_PASSWORD_FILE` e fica em `secrets/backup-keys/`, com ficheiro versionado, cópia local de salvaguarda e atributo de leitura. O acesso Windows é restrito ao proprietário e SYSTEM. A chave não é incluída no arquivo; o resultado do backup regista apenas um identificador não secreto da chave. A cópia local não substitui a salvaguarda externa. Perder a chave impede recuperar os backups.

Durante esta preparação, o ficheiro da primeira chave foi substituído acidentalmente pela credencial SMTP. Foi preservado e deixou de ser usado como chave. O arquivo anterior às migrações já não abre com esse ficheiro; só será utilizável se a chave original for recuperada. A base e os documentos permaneceram intactos. Foi criada uma nova chave protegida e um novo backup completo do estado atual, com 21 tabelas e 223 entradas, incluindo uploads históricos; a publicação desse novo arquivo incluiu verificação integral com a nova chave.

O código e as dependências são recuperados pela versão distribuída do produto. As credenciais, certificados, `.env`, logs, sessões e caches não são repostos automaticamente pelo arquivo: preparar a configuração de destino e obter os segredos do seu armazenamento privado. O backup não recolhe tokens de sessões nem substitui a gestão de segredos.

## Restauro completo num destino vazio

Preparar uma instalação da mesma versão, uma base vazia e uma pasta de armazenamento vazia, ainda sem tráfego web. Guardar a configuração da base de destino num ficheiro privado:

```text
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=documentos_recuperados
DB_USER=operador_recuperacao
DB_PASS_FILE=/run/secrets/recovery_database_password
```

Executar com acesso à chave do backup:

```text
php cli/maintenance.php restore --archive=/backup/full-YYYYMMDD-HHMMSS-id.zip --target-config=/run/secrets/recovery.env --target-storage=/srv/documentos-recuperados/storage
```

A base original pode estar indisponível. O destino deve estar vazio; o comando recusa substituir qualquer base com tabelas ou armazenamento com ficheiros. Não apontar primeiro o servidor web para o destino, pois o arranque cria diretórios de runtime e deixa de ser uma pasta vazia.

Antes de gravar na base de destino, o comando autentica/desencripta o arquivo e verifica todos os caminhos, entradas, hashes, esquemas e fluxos de dados. Depois recria a estrutura, importa todos os registos, verifica referências estrangeiras e instala os ficheiros. Rejeita caminhos de traversal, ficheiros inesperados, entradas duplicadas e arquivos sem encriptação.

Durante uma recuperação existe uma marca `.recovery-incomplete`; o arranque recusa servir a instalação enquanto essa marca existir. Uma falha de DDL ou de disco pode deixar a base de destino parcialmente preparada, porque DDL não é transacional. Não apagar a marca para abrir a aplicação. Investigar a falha e repetir num novo destino vazio. A origem e os dados já existentes não são sobrescritos.

Após sucesso, configurar o `.env` da instalação recuperada para a base e o armazenamento correspondentes, ajustar o dono/ACLs para a conta do servidor web, validar login, permissões e documentos, e só então encaminhar tráfego. Rever destinatários de email e tarefas agendadas antes de os ativar no destino.

A importação antiga da interface continua desativada por omissão. `ALLOW_LEGACY_RESTORE` não ativa este mecanismo completo e não deve ser usado para recuperação de desastre.

## Ensaio repetível

```powershell
$env:RUN_MYSQL_INTEGRATION='1'
php scripts/validar_recuperacao_mysql.php
```

O teste cria bases com prefixo `anferalta_verify_` e identificador aleatório, usa apenas dados fictícios e elimina exclusivamente essas bases no final. Inclui tabelas InnoDB/MyISAM, dados binários e nulos, ficheiros ativos/arquivados/tramitação, hashes, chave errada, adulteração, destino ocupado, bloqueios de manutenção e migrações interrompidas/alteradas.

## Rotação SMTP

A credencial antiga foi retirada do código. O responsável confirmou que a revogou no painel do fornecedor; a nova está em `secrets/smtp-password.txt` e o `.env` local aponta `MAIL_PASS_FILE` para esse ficheiro. O teste real de TLS e autenticação passou, sem envio de email. Não colar a palavra-passe no chat nem no código.

```text
php cli/smtp-check.php
```

Este comando verifica TLS e autenticação e termina a ligação sem enviar email. Só marcar a rotação como concluída depois da revogação no fornecedor e deste teste passar. A presença de uma nova password local não demonstra que a antiga foi revogada.
