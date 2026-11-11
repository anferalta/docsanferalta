# Guia de instalação do AnferaltaDocs

Para uma máquina Windows sem PHP, MySQL ou servidor web, consultar o [assistente EXE de instalação de raiz](instalador-windows-exe.md). O procedimento manual abaixo pressupõe requisitos preparados. O EXE de raiz encontra-se em fase de validação, conforme indicado no seu guia.

Este guia destina-se ao administrador que vai instalar a aplicação nos servidores de uma entidade. O procedimento usa uma cópia limpa do produto e uma base MySQL vazia. Para atualizar a instalação já existente, consultar a secção 10.

## 1. O que preparar

| Requisito | Preparação |
| --- | --- |
| PHP | Versão 8.3 ou superior, de 64 bits, disponível no terminal e no servidor web. |
| Extensões PHP | `pdo_mysql`, `fileinfo`, `zip` com AES-256, `openssl`, `dom`, `xml`, `xmlreader`, `xmlwriter`, `gd` e `iconv`; os restantes requisitos são verificados pelo Composer. |
| Execução CLI | `proc_open` disponível para o instalador e Composer. O PHP CLI pode ter configuração diferente do PHP web. |
| Composer | Instalado e disponível no PATH, ou um ficheiro `composer.phar` cujo caminho seja conhecido. |
| MySQL | Servidor disponível, base vazia e conta dedicada. A versão ensaiada foi MySQL 9.1 com PHP 8.4. MariaDB ainda não foi homologado. |
| Servidor web | Apache ou Nginx, domínio definido e certificado HTTPS confiável nos computadores da entidade. |
| Disco | Espaço para aplicação, documentos, temporários e backups. O sistema de ficheiros deve permitir hard links, usados na publicação de `.env` — por exemplo, NTFS ou ext4. |
| Acessos | Operador autorizado para preparar ficheiros, base de dados e configuração do servidor web. |

O instalador prepara a aplicação; não instala o serviço MySQL, o PHP, o servidor web nem o certificado HTTPS. Não envia emails durante a instalação.

## 2. Obter e extrair o pacote

Usar o pacote de fonte limpo gerado para distribuição. Não copiar integralmente a instalação de desenvolvimento, o seu `.env`, chaves, documentos, backups ou histórico Git.

Extrair para uma pasta nova. Exemplos usados neste guia:

- Windows: `C:\Sites\AnferaltaDocs`.
- Linux: `/srv/anferaltadocs`.

A pasta deve conter `install.php`, `composer.json`, `composer.lock`, `App/`, `database/migrations/` e `public/`.

Abrir um terminal nessa pasta:

```powershell
# Windows / PowerShell
Set-Location 'C:\Sites\AnferaltaDocs'
php --version
composer --version
```

```bash
# Linux
cd /srv/anferaltadocs
php --version
composer --version
```

Todos os comandos seguintes são executados na raiz da aplicação, salvo indicação contrária.

## 3. Preparar a base de dados

Pedir ao administrador MySQL uma base vazia, com charset `utf8mb4`, e credenciais exclusivas desta entidade. Exemplo de criação da base, numa sessão administrativa MySQL:

```sql
CREATE DATABASE documentos
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Preparar duas contas:

| Conta | Finalidade |
| --- | --- |
| Instalação/manutenção | Criar e alterar o esquema e executar backups: privilégios necessários de DDL, DML, índices, referências e `LOCK TABLES`, limitados à base da aplicação. |
| Aplicação | Operação normal: leitura, inserção, atualização e eliminação dos dados necessários, sem administração global do MySQL. |

O instalador usa a conta de instalação indicada e grava-a na configuração inicial. Antes de abrir o serviço aos utilizadores, trocar essa configuração para a conta de aplicação, conforme a secção 7. Guardar as credenciais da conta de manutenção separadamente para atualizações e backups.

Não usar a conta `root` na instalação de produção. O instalador recusa-a. Também recusa uma base que já contenha tabelas; não apaga dados para preparar o destino.

## 4. Preparar os ficheiros de passwords

Guardar a password MySQL num ficheiro de texto privado, fora de `public/`, contendo apenas a password. Não incluir `DB_PASS=`, aspas adicionais ou instruções. Não colocar passwords na linha de comandos, no JSON de exemplo ou no chat.

Exemplos de caminhos para os ficheiros de entrada:

| Informação | Windows | Linux |
| --- | --- | --- |
| Password MySQL de instalação | `C:/Segredos/database-password.txt` | `/run/secrets/database_password` |
| Password inicial do administrador — opcional | `C:/Segredos/initial-admin-password.txt` | `/run/secrets/initial_admin_password` |
| Password SMTP — se configurar email | `C:/Segredos/smtp-password.txt` | `/run/secrets/smtp_password` |

Restringir a leitura destes ficheiros à conta responsável. A password inicial do administrador deve ter pelo menos 14 caracteres. Se não for fornecida, o assistente gera uma password própria desta instalação.

**A password SMTP e a chave de recuperação são ficheiros diferentes. Não substituir a chave de recuperação para configurar email.**

## 5. Verificar os requisitos e instalar

Executar:

```text
php install.php --check
```

Este comando não altera ficheiros nem liga à base. Se indicar que falta `vendor/autoload.php`, preparar as dependências:

```text
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts --no-plugins
php install.php --check
```

Na execução normal, o instalador também tenta instalar as dependências pelo lock quando `vendor/` não existe. Se Composer não estiver no PATH, indicar o ficheiro PHAR:

```text
php install.php --composer-phar=/caminho/composer.phar
```

Para iniciar o modo guiado:

```text
php install.php
```

O assistente pede:

1. URL HTTPS na raiz do domínio, por exemplo `https://documentos.entidade.pt`. Não usar um caminho como `/documentos`.
2. Fuso horário, por exemplo `Europe/Lisbon`.
3. Servidor, porta, base vazia, utilizador MySQL e caminho do ficheiro da password.
4. Nome e email do administrador, e opcionalmente o ficheiro da sua password inicial.
5. Configuração SMTP, se já estiver disponível.

Rever o plano apresentado, que não mostra passwords, e confirmar a execução. O instalador prepara as pastas, chaves, esquema e referências mínimas, cria o administrador e publica `.env` no final. Não importa utilizadores ou documentos de outra instalação.

### Instalação automática

Copiar `install.example.json` para uma localização privada e ajustar os valores. Exemplo Windows:

```json
{
  "APP_URL": "https://documentos.entidade.pt",
  "APP_TIMEZONE": "Europe/Lisbon",
  "DB_HOST": "127.0.0.1",
  "DB_PORT": 3306,
  "DB_NAME": "documentos",
  "DB_USER": "documentos_instalacao",
  "DB_PASS_FILE": "C:/Segredos/database-password.txt",
  "ADMIN_EMAIL": "administrador@entidade.pt",
  "ADMIN_NAME": "Administrador"
}
```

Sem `ADMIN_PASSWORD_FILE`, será gerada uma password inicial. Para Linux, substituir os caminhos pelos correspondentes no servidor.

Primeiro simular; depois instalar:

```text
php install.php --config=C:/Segredos/install.json --plan
php install.php --config=C:/Segredos/install.json --yes
```

`--plan` verifica configuração e base vazia sem gravar nem executar migrações. As dependências devem estar preparadas previamente. `--yes` dispensa a confirmação interativa e deve ser usado depois de rever o plano.

## 6. Configurar o servidor web

O DocumentRoot deve apontar **exclusivamente para a pasta `public/`**, nunca para a raiz do projeto.

| Sistema | DocumentRoot do exemplo |
| --- | --- |
| Windows | `C:/Sites/AnferaltaDocs/public` |
| Linux | `/srv/anferaltadocs/public` |

No Apache, ativar `mod_rewrite`, permitir as regras `.htaccess` da pasta pública e definir `DirectoryIndex index.php`. Configurar o virtual host HTTPS com o certificado do domínio e redirecionar HTTP para HTTPS. Não criar aliases para `storage/`, `secrets/`, `backups/`, `vendor/` ou código interno.

Em Nginx, configurar as regras equivalentes: os `.htaccess` não são interpretados. Permitir a execução PHP apenas pelo front controller `public/index.php` e manter os ficheiros privados fora da raiz servida.

A configuração `deploy/apache-vhosts-local.conf` é específica do WAMP de desenvolvimento. Não a aplicar diretamente num servidor de cliente: contém caminhos e domínios locais. O auxiliar `scripts/aplicar_servidor_local.ps1` também é exclusivo desse ambiente.

Antes de reiniciar ou recarregar o servidor web, validar a configuração pelo comando próprio da instalação de Apache/Nginx. Confirmar que o PHP web satisfaz os mesmos requisitos do PHP CLI.

## 7. Ajustar permissões e a conta da aplicação

O instalador protege os segredos com permissões privadas. No Windows, prepara ACL para o operador e SYSTEM. Se o PHP correr noutra conta, o administrador do servidor deve conceder-lhe os acessos necessários. No Linux, ajustar dono e grupo à conta real do PHP.

| Local | Acesso necessário |
| --- | --- |
| Código e dependências | Leitura pelo PHP; escrita apenas pelo processo de implantação. |
| `.env` | Leitura pela conta PHP e pelo operador autorizado. |
| Ficheiros da password MySQL e SMTP | Leitura apenas pelas contas que os utilizam. |
| `storage/` | Leitura e escrita pela aplicação e manutenção. |
| `backups/` | Escrita pelo operador/tarefa de backup; acesso privado. |
| Chaves de recuperação e password inicial | Acesso administrativo; não conceder leitura pública nem escrita à aplicação. |

Não usar permissões `0777` para resolver erros de acesso.

Depois de instalar, guardar a password da conta MySQL de aplicação num ficheiro privado próprio e editar em `.env` apenas os parâmetros correspondentes:

```dotenv
DB_USER="documentos_app"
DB_PASS_FILE="/caminho/privado/application-database-password.txt"
```

Usar o caminho Windows correspondente quando aplicável. A conta deve estar preparada e ter os privilégios necessários antes da mudança. Confirmar o login após a alteração. Nas operações de manutenção, fornecer as credenciais da conta de manutenção numa sessão administrativa própria, por variáveis de processo; não voltar a deixar essa conta privilegiada no processo web.

## 8. Primeiro acesso e email

Abrir a URL HTTPS configurada, seguida de `/login`. Usar o email escolhido e a password do ficheiro privado indicado pelo instalador:

```text
secrets/initial-admin-password.txt
```

Alterar a password no perfil depois de entrar. Configurar os tipos de documento, áreas, perfis e utilizadores da entidade.

Se SMTP foi configurado, executar:

```text
php cli/smtp-check.php
```

O resultado esperado confirma TLS e autenticação, sem enviar email. Se o fornecedor usar uma CA privada ou o PHP não tiver a cadeia de confiança adequada, configurar `MAIL_CA_FILE` com o ficheiro CA confiável. Não desativar a verificação dos certificados para contornar o erro.

Rever `ADMIN_EMAILS` e `SLA_REPORT_EMAILS` antes de ativar notificações ou relatórios agendados. O instalador preenche estes destinatários com o administrador escolhido.

## 9. Verificações antes de disponibilizar o serviço

- HTTPS abre sem avisos de certificado e HTTP redireciona para HTTPS.
- O administrador consegue iniciar e terminar sessão; um utilizador sem permissão não obtém documentos de terceiros.
- Um documento de teste pode ser carregado, consultado e descarregado.
- Pedidos a `/.env`, `/.git/config`, `/storage/`, `/secrets/` e `/backups/` devolvem 403 ou 404.
- A conta PHP tem os acessos necessários, mas não controla serviços do sistema nem escreve no código.
- SMTP passa no teste, se o email fizer parte da instalação.
- Existe um backup completo verificado e um ensaio de restauro num destino isolado.

No Windows, pode executar o validador de servidor com a URL da entidade:

```powershell
./scripts/validar_servidor.ps1 -BaseUrl 'https://documentos.entidade.pt'
```

Para criar o backup, usar as credenciais administrativas de manutenção e executar:

```text
php cli/maintenance.php backup
php cli/maintenance.php verify --archive=/caminho/do/backup-completo.zip
```

A chave está em `secrets/recovery.key` e a cópia local em `secrets/recovery.escrow`. Guardar uma cópia segura da chave e uma cópia do backup fora do servidor, em locais separados. A cópia local da chave não substitui essa salvaguarda externa.

O agendamento não é criado pelo instalador. Configurar no cron ou no Agendador de Tarefas a execução de `cron/backup_completo.php` e confirmar o resultado de uma execução real.

## 10. Atualizações, interrupções e recuperação

Para atualizar uma instalação existente, não executar novamente a instalação inicial:

```text
php cli/maintenance.php status
php cli/maintenance.php migrate
```

Usar a conta de manutenção numa sessão privada. As migrações pendentes são aplicadas por ordem e, numa base existente, é criado primeiro um backup completo. As variáveis reais do processo prevalecem sobre `.env`.

Se a instalação inicial foi interrompida, usar o mesmo destino e configuração:

```text
php install.php --config=/caminho/privado/install.json --resume --yes
```

A aplicação permanece em manutenção enquanto existir `storage/.installation-incomplete`. Não apagar essa marca para abrir uma instalação incompleta. O estado encontra-se em `storage/installation-state.json`.

| Situação | Ação |
| --- | --- |
| Requisitos PHP em falta | Ativar as extensões no PHP CLI correto e repetir `--check`. |
| Composer não encontrado | Instalar Composer ou indicar `--composer-phar`. |
| `.env` já existe | Numa instalação concluída, usar o comando de migração. Não apagar a configuração para forçar instalação. |
| Base contém tabelas | Selecionar uma base vazia; não limpar dados existentes para ultrapassar o bloqueio. |
| Erro de permissões | Rever a conta do PHP, dono/grupo e ACLs; não abrir acesso geral aos segredos. |
| Instalação interrompida | Usar `--resume` com a configuração original e consultar o estado privado. |
| Migração DDL interrompida | Não alterar checksums ou apagar o histórico. Investigar e recuperar num novo destino vazio. |
| Erro SMTP | Confirmar ficheiro da password, host, porta, modo TLS e CA, sem mostrar a password. |
| Falha na publicação de `.env` | Confirmar espaço, permissões e suporte a hard links; só depois retomar. |

Para recuperar a aplicação, preparar uma base e um armazenamento vazios e seguir o [guia de recuperação completa](recuperacao-e-instalacao.md). Não usar a importação legada da interface como recuperação de desastre.

## Referências do produto

- [Detalhes do instalador e opções](instalador.md).
- [Configuração automática de exemplo](../install.example.json).
- [Migrações, backups e recuperação completa](recuperacao-e-instalacao.md).
- [Validação do servidor](validacao-servidor.md).

O instalador foi ensaiado com 25 verificações em bases temporárias, incluindo criação do administrador, proteção da configuração, recusa de dados existentes e retoma após interrupção. Antes de entrar em produção, repetir as verificações na infraestrutura da entidade.
