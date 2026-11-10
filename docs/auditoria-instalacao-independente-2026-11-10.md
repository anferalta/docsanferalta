# Auditoria para instalação independente

Data: 10 de novembro de 2026. Âmbito: código e configuração local disponível do AnferaltaDocs.

## Segunda etapa: recuperação, instalação e servidor

Esta secção atualiza os estados inicialmente abertos na tabela da etapa anterior.

- **A08 — recuperação completa implementada e ensaiada:** backup encriptado com todas as tabelas, documentos ativos, arquivo, tramitação, uploads e agendamentos. Verificação integral por manifesto, hashes, contagens e autenticação AES; restauro completo apenas para BD e armazenamento vazios. O mecanismo legado da interface permanece bloqueado. A verificação do arquivo e o restauro não dependem de a base original estar disponível.
- **A12 — esquema e migrações implementados e aplicados localmente:** esquema limpo de 20 tabelas, sem registos operacionais, obtido da estrutura real atual; instalação CLI com administrador explícito; três migrações registadas com checksum. Antes de migrar a base local foi criado e verificado um backup completo encriptado das 20 tabelas e 195 entradas. Reexecução não reaplica migrações.
- **A04/A14 — servidor local configurado e validado:** Apache passou a servir `public/` em HTTPS, com redirecionamento HTTP e sem aliases privados. Certificado para os domínios locais, assinado pela CA de desenvolvimento existente. Dezasseis verificações por pedidos reais passaram, incluindo cookies e recusa de configuração, Git, vendor, backups, segredos e diagnósticos. O reinício exigiu elevação Windows; o auxiliar elevado reiniciou apenas o Apache. Não foi reiniciado o MySQL.
- **A02/A03 — rotação SMTP concluída nesta etapa:** o responsável confirmou a revogação da credencial antiga no painel. A nova está em `secrets/smtp-password.txt`, com acesso restrito e leitura por ficheiro. O teste real de TLS e autenticação passou, sem envio de email. Foi corrigida e testada a correspondência entre a opção SMTPS e a constante exigida pelo PHPMailer; a validação de certificados permanece ativa. O PHP local usa um conjunto de certificados públicos já confiáveis no Windows, sem exportação de chaves privadas nem instalação de novas raízes.

O backup e a chave local foram guardados em locais privados separados; o relatório não contém os seus valores. A chave inicial foi depois substituída pela credencial SMTP, conforme confirmado pelo responsável: o primeiro arquivo fica indisponível sem recuperar a chave original. Foi criada uma nova chave versionada, com cópia de salvaguarda e atributo de leitura, e um novo backup verificado do estado atual (21 tabelas, 223 entradas). Os dados de origem não foram perdidos ou substituídos. Guardar cópia externa do backup e cópia segura da chave. A conta de BD e a CA de desenvolvimento do WAMP local não devem ser reutilizadas como política de produção de clientes.

Verificação final da segunda etapa: 176 ficheiros PHP sem erros de sintaxe; oito scripts de regressão com 1 059 verificações, ensaio MySQL com 30 verificações e servidor com 16 verificações HTTP/TLS, sem falhas (1 105 verificações no total). As 20 tabelas originais estão em InnoDB e as três migrações estão aplicadas; uma nova execução não reaplicou nada. O mecanismo de recuperação foi testado também com tabelas MyISAM, dados binários, NULL, ficheiros legados, arquivos adulterados e bloqueios de manutenção. O novo backup completo foi novamente verificado com a chave protegida, sem ligação à base de origem.

Procedimentos atuais: [recuperacao-e-instalacao.md](recuperacao-e-instalacao.md) e [validacao-servidor.md](validacao-servidor.md). A homologação de um servidor externo exige executar os mesmos passos nesse destino.

## Atualização após as correções

As evidências A01–A15 abaixo descrevem a fotografia inicial. Os ficheiros foram entretanto corrigidos onde indicado nesta tabela; as referências de linha iniciais não representam necessariamente a versão atual.

| Ponto | Estado após esta etapa |
| --- | --- |
| A01 | Corrigido no código: rota antiga exige autenticação e reutiliza a verificação de proprietário dos documentos privados. Não existe nova partilha pública implícita. |
| A02 | Mitigado para exportação: `.gitignore`, `.env.example` e pacote de fonte por lista permitida. Credencial literal removida. Ficheiros já versionados e histórico continuam a exigir saneamento separado; a password precisa de rotação no fornecedor. |
| A03 | Corrigido no serviço de email: validação TLS ativa, CA configurável e parâmetros SMTP explícitos, sem credencial embutida. Não foi enviado email real. |
| A04 | Regras locais `.htaccess` protegem o projeto e os documentos e bloqueiam PHP auxiliar na pasta pública. A alteração do DocumentRoot/aliases e a verificação HTTP no Apache efetivo continuam pendentes. |
| A05 | Corrigido o arranque web/CLI autocontido, sem depender do ficheiro da pasta mãe. O esquema limpo para instalação inicial pertence ao trabalho ainda aberto em A12. |
| A06 | Corrigido: rotas únicas, rejeição de duplicados, ações existentes, restrições de parâmetros respeitadas e controlador de perfil válido. Loader legado e loader canónico verificados. |
| A07 | Corrigido: criação de backups via POST com CSRF, navegação para confirmação de restauro e retirada da rota/código que reiniciava Apache. |
| A08 | **Aberto:** recuperação integral ainda não implementada nem demonstrada. Importação legada bloqueada por omissão e texto enganador da interface corrigido. O backup de ficheiros continua a abranger apenas documentos ativos. |
| A09 | Corrigido o bootstrap dos cron, bloqueio por tarefa, recusa de HTTP e saída de erro. Comandos de agendamento usam o caminho da instalação; tarefas reais e respetivos resultados ainda precisam de validação operacional. |
| A10 | Corrigidos destinatários/domínios fixos nos serviços de relatório/backup e links de recuperação; conexão exige parâmetros explícitos. Configurar APP_URL e SMTP/destinatários antes de usar esses fluxos. Não foram alteradas as credenciais reais. |
| A11 | Política comum de extensão, MIME, estrutura Office e dimensão integrada em todos os pontos `move_uploaded_file` encontrados. Pré-validação antes de criar documentos nos fluxos principais. A consistência transacional entre ficheiros e BD ainda requer testes integrados. |
| A12 | **Aberto:** esquema inicial limpo, migrações automáticas e ensaios de instalação/atualização. Não foi aplicada nenhuma migração à base real. |
| A13 | Acrescentado limitador local atómico por origem e por conta/origem. MFA e política de registo empresarial continuam pendentes; limitação partilhada necessária se houver vários servidores. |
| A14 | Debug condicionado ao ambiente no novo arranque; scripts públicos de diagnóstico devolvem 404; removida ativação de erros no upload AJAX; permissões de criação reduzidas para 0770. ACLs existentes, HTTPS/proxy, retenção e separação completa dos volumes continuam por validar. |
| A15 | Composer válido, requisitos diretos declarados, versões limitadas e dependências corrigidas com lock atualizado. PHP mínimo 8.3 de 64 bits; verificado em 8.4. Empacotador de fonte e guia adicionados. Docker/instalador e lançamento final ainda não preparados. |

### Verificação das correções

- `validar_instalacao.php`: 493 verificações sem falhas, incluindo tabela de rotas, CSRF nas operações POST, ausência de reinício por rota, restrições de parâmetros, uploads, limitação de login, bloqueio do restauro legado e sintaxe Twig.
- Verificações existentes sem falhas: CSRF (19), utilizadores (121), menu/dashboard (85), relatórios SLA (67, incluindo PDF/Excel e emails simulados), auditoria (85), perfis (98) e logs (86). SQLite em memória e ficheiros temporários; não foi usada a base de dados real.
- Pedidos simulados pelo front controller em processos PHP isolados: login 200 com formulário; documento anónimo 302 sem conteúdo; criação de backup via GET 404; diagnóstico `/test` 404; login sem CSRF 303. Não constituem prova da configuração Apache/TLS. A tentativa de ligação ao servidor HTTP local não ficou acessível neste ambiente e o processo de teste foi terminado.
- A consulta Composer inicialmente bloqueada foi concluída com acesso de rede: 33 avisos em três dependências antes da atualização; nenhum aviso conhecido reportado depois. Twig 3.30.0, Dompdf 3.1.6 e PhpSpreadsheet 1.30.8, com dependências transitivas compatíveis atualizadas. Isto não equivale a uma garantia de ausência de vulnerabilidades.
- `composer validate --strict` passou e `check-platform-reqs --lock --no-dev` passou no PHP CLI 8.4 local.
- Sintaxe dos 168 ficheiros PHP verificada sem falhas; `git diff --check` sem erros. O conjunto dos oito scripts executados totalizou 1 054 verificações sem falhas.
- O pacote de fonte foi inspecionado: sem caminhos privados proibidos, sem erros CRC e com hashes do manifesto correspondentes. O pacote não é uma edição pronta a instalar.
- Permanece um aviso do PHP local ao tentar carregar Xdebug de uma unidade antiga; a configuração do PHP fora do projeto não foi modificada.

Guia operacional: [instalacao-independente.md](instalacao-independente.md). A auditoria e as correções verificadas desta etapa estão registadas; **o piloto externo continua bloqueado** pelos pontos abertos, rotação dos segredos e validação do servidor. Nenhum dado real, ficheiro privado versionado ou histórico Git foi apagado.

## Conclusão inicial

A aplicação ainda não está pronta para ser distribuída a terceiros. Os bloqueios principais são acesso público a documentos, segredos no código/repositório, dependência de um ponto de entrada fora do projeto, rotas duplicadas e recuperação incompleta dos dados.

A fase inicial desta auditoria apenas acrescentou este relatório. A etapa de correção posterior alterou o código conforme a tabela acima. Não foram enviados emails reais, executados backups/restauros reais, alteradas credenciais ou reiniciados serviços.

Prioridades: P0 = resolver antes de qualquer distribuição e avaliar na instalação existente; P1 = resolver antes do piloto externo; P2 = completar antes da disponibilização geral. A prioridade considera confidencialidade, integridade e capacidade de operar autonomamente, não corresponde a uma pontuação CVSS.

## Evidências e ações

### A01 — P0 — Documentos disponíveis sem autorização

- Evidência: `routes/admin.php:68` regista `GET /public/documentos/{id}` fora do grupo autenticado. `App/Controllers/DocumentosPublicController.php:11` procura o anexo pelo ID e entrega o ficheiro sem sessão, permissão ou validação de partilha pública. Também procura cópias arquivadas.
- Consequência: conhecendo ou percorrendo IDs, um visitante pode obter documentos que o código não distingue como públicos ou privados. A existência do caminho no código está confirmada; não foi descarregado nenhum documento para testar a exposição por HTTP.
- Ação: exigir autenticação e autorização sobre o documento. Se houver necessidade de partilha externa, implementar uma opção explícita com token imprevisível, expiração e revogação, incluindo política para documentos arquivados.
- Aceitação: visitante anónimo e utilizador sem acesso não conseguem obter documentos ativos nem arquivados; as partilhas autorizadas obedecem à política definida.

### A02 — P0 — Credenciais e dados operacionais incluídos no Git

- Evidência: `git ls-files` inclui `.env`, `anferaltadocs.sql`, backups de base de dados, documentos, logs e ficheiros em `storage`. `.gitignore:1` exclui apenas `/vendor/`. `App/Services/EmailService.php:40` contém uma palavra-passe SMTP literal como fallback; o valor foi omitido deste relatório.
- Consequência: entregar um clone ou um pacote indiscriminado pode entregar configurações privadas e dados da instalação existente. Excluir um ficheiro num commit futuro não o elimina do histórico.
- Ação: criar uma distribuição com lista explícita de ficheiros permitidos; retirar segredos e dados operacionais do versionamento corrente; disponibilizar `.env.example` sem valores privados. Tratar a credencial embutida como exposta e substituí-la no serviço respetivo. Avaliar separadamente a limpeza do histórico e cópias já partilhadas.
- Aceitação: pacote e checkout de distribuição sem segredos, documentos, dumps, backups ou logs reais. Novas credenciais exclusivas de cada instalação. A rotação e qualquer reescrita do histórico não foram executadas nesta auditoria.

### A03 — P0 — Validação TLS do SMTP desativada

- Evidência: `App/Services/EmailService.php:49` define `verify_peer=false`, `verify_peer_name=false` e `allow_self_signed=true`.
- Consequência: a ligação cifrada não autentica corretamente o servidor de email, expondo credenciais e mensagens à interceção.
- Ação: validar certificados por omissão; permitir configurar uma CA privada de confiança para empresas que precisem dela. Tornar host, porta, remetente e modo TLS configuráveis e obrigar à configuração antes de enviar.
- Aceitação: certificado válido é aceite; certificado inválido ou identidade incorreta são rejeitados.

### A04 — P0 — Limite público do servidor incompatível com o isolamento dos dados

- Evidência local: `C:/wamp/bin/apache/apache2.4.62.1/conf/extra/httpd-vhosts.conf` aponta o DocumentRoot para `C:/wamp/www/anferalta`, acima do projeto, com `Require all granted`. Também expõe um alias `/storage/documentos` com `Options Indexes FollowSymLinks`. `storage/.htaccess` nega acesso, mas `storage/documentos/.htaccess` contém `Options +Indexes`, `Require all granted` e `AllowOverride All`.
- Consequência: o isolamento depende de regras externas e contraditórias; o alias tenta tornar os documentos diretamente acessíveis. A diretiva `AllowOverride` dentro de `.htaccess` também deve ser corrigida por não pertencer a esse contexto. O resultado HTTP efetivo não foi validado; pode variar entre bloqueio, erro e exposição conforme a configuração carregada.
- Ação: DocumentRoot limitado a `public/`; remover aliases para dados privados, bloquear listagem e execução de uploads e servir documentos apenas por controladores autorizados.
- Aceitação: pedidos a configuração, Git, código interno, logs, backups e armazenamento privado são recusados; só os recursos públicos previstos são servidos. Revalidar no servidor de destino: a configuração observada é local, não prova a configuração de produção.

### A05 — P1 — A instalação depende de ficheiros fora do repositório

- Evidência: o ponto de entrada local está em `C:/wamp/www/anferalta/index.php`, fora desta raiz. Carrega caminhos, autoload e ambiente a partir de uma subpasta fixa. `public/index.php:4` procura `public/vendor/autoload.php`, inexistente, e carrega `public/routes/*.php`, também inexistente. Não carrega `App/Config/paths.php`. `App/bootstrap/app.php` referencia `App/config.php`, inexistente, e usa uma assinatura de registo de middleware incompatível com `App/Core/Middleware.php`.
- Consequência: copiar este projeto e apontar o servidor para `public/` não reproduz a instalação existente.
- Ação: um bootstrap comum para web e CLI, raízes resolvidas a partir do projeto, validação de configuração obrigatória e front controller autocontido em `public/index.php`.
- Aceitação: instalação limpa num caminho diferente e sem ficheiros da pasta mãe; pedidos web e comandos CLI usam a mesma configuração validada.

### A06 — P1 — Rotas repetidas, ações inexistentes e padrões ignorados

- Evidência: ambos os pontos de entrada usam `glob(routes/*.php)`; `routes/index.php` volta a incluir `public.php` e `admin.php`. A inspeção do registo resultou em **317 entradas para 143 pares método/caminho distintos**. `App/Core/Router.php` executa a primeira correspondência. Ações sem classe/método resolúvel: `PerfilController@index`, `Admin\\BackupsAdminController@dashboard`, `Admin\\BackupsAdminController@logs`, `AuthController@recuperar`, `AuthController@recuperarSubmit`, `AuthController@loginPost`, `AuthController@recuperarPost`.
- `App/Controllers/PerfilController.php` contém texto de classes fora de um bloco PHP: passa no lint, mas o autoload imprime texto e não define a classe esperada. Isto foi observado ao verificar as ações sem instanciar controladores.
- O Router substitui qualquer expressão `{...}` por `([^/]+)`, descartando restrições como `:dígitos` e `:.+`. Caminhos de backup com barras podem não corresponder às rotas previstas.
- Ação: carregar cada conjunto uma vez, rejeitar duplicados, validar ações e tratar corretamente parâmetros. Remover caminhos legados depois de verificar os consumidores.
- Aceitação: nenhum par duplicado, todas as ações resolúveis, todos os placeholders testados e middleware definido de forma inequívoca. Não se conclui que todos os POST estejam sem CSRF: há verificações nos controladores e nas primeiras rotas registadas.

### A07 — P1 — Restauro e criação de backups por GET

- Evidência: `routes/admin.php:227-228,235` regista criação de backups e restauro com reinício via GET. `BackupsAdminController::restaurarEReiniciar()` chama efetivamente o restauro e comandos de serviço. Existe autorização no controlador, mas não validação CSRF nesse caminho GET.
- Consequência: uma navegação pode provocar operações de escrita ou interrupção de serviço na sessão de um utilizador autorizado.
- Ação: GET apenas para consulta/confirmação; operações por POST com CSRF, permissão específica e confirmação explícita para restauro. Retirar o reinício do servidor web do processo PHP.
- Aceitação: GET não modifica dados nem cria backups; POST inválido ou sem permissão é recusado.

### A08 — P1 — Backups incompletos e restauro sem equivalência ao estado guardado

- Evidência: `App/Services/FileBackupService.php` inclui só `STORAGE_DOCUMENTOS`, excluindo `STORAGE_TRAMITACAO`, `STORAGE_ARQUIVADOS` e outras configurações necessárias. `DatabaseBackupService::gerarDumpPDO()` percorre tabelas sem snapshot consistente, usa `fetchAll()` e constrói todo o dump em memória. Não cobre explicitamente rotinas, eventos e triggers.
- `RestoreService::dividirSQL()` ignora CREATE/DROP/ALTER e converte INSERT em INSERT IGNORE. Não recria uma base vazia nem substitui registos existentes com o mesmo identificador. O restauro de ficheiros sobrepõe o destino e não remove ficheiros posteriores ao backup.
- A encriptação SQL em ZIP é opcional; o serviço de restauro não chama `setPassword()` antes da extração. `BackupIntegrityService` considera encriptado apenas o sufixo `.enc`, diferente do ZIP AES produzido. Os temporários de restauro não são limpos com `finally` em todos os caminhos de erro.
- Consequência: um ZIP válido não garante recuperação completa; podem perder-se anexos, manter-se dados posteriores e falhar backups encriptados. O restauro atual comporta-se em parte como uma importação aditiva.
- Ação: definir um conjunto completo e consistente de recuperação, formatos compatíveis, manifesto de versão/hashes, política de chaves, cópia externa e restauro isolado antes de substituir o ambiente. Validar limites e entradas dos ZIPs e limpar temporários em sucesso e erro.
- Aceitação: recuperar num ambiente vazio e comparar registos, permissões, documentos ativos, arquivo e anexos de tramitação. Testar falha intermédia e backup encriptado. Nenhum restauro foi executado nesta auditoria.

### A09 — P1 — Tarefas automáticas sem arranque comum e tratamento operacional suficiente

- Evidência: `cron/backup_db.php`, `cron/backup_files.php` e `cron/sla.php` carregam apenas o autoload, sem ambiente nem constantes de caminhos. O autoload Composer inclui apenas `App/helpers.php`. Os relatórios SLA usam outro carregador (`Env::load`). Os cron de backup capturam exceções sem devolver explicitamente código de falha. `cron.php` referencia `BackupFiles.php`, inexistente; `public/cron_backup.php` referencia `BackupService`, inexistente.
- `SchedulerService.php:13,26-27` contém caminhos Linux/WAMP fixos. Não foi identificado bloqueio contra execuções concorrentes nestes pontos de entrada.
- Consequência: tarefas podem falhar, usar defaults incorretos ou parecer bem-sucedidas ao agendador. Registar um agendamento em JSON não comprova que um serviço o executa.
- Ação: bootstrap CLI comum, recusa de acesso HTTP aos comandos, códigos de saída, bloqueio de concorrência, timezone definido e instruções verificadas para o agendador suportado.
- Aceitação: executar cada comando num ambiente limpo com variáveis próprias, verificar falhas observáveis e duas execuções simultâneas. Nesta auditoria não se executaram tarefas com efeitos.

### A10 — P1 — Configuração e comunicações ligadas à instalação original

- Evidência: `RelatorioSLAEmailService.php:41` envia o relatório para um destinatário fixo da entidade original. `BackupLogger.php` tem o mesmo domínio como fallback. `PasswordResetController.php:79` constrói o link com domínio local fixo. `Helpers::baseUrl()` tem domínios fixos. `App/Config/database.php` e `Conexao.php` têm defaults de utilizador root e password vazia.
- Consequência: dados de outra empresa podem ser enviados ao destinatário original; links podem apontar para o ambiente errado; falta de configuração pode passar despercebida.
- Ação: configuração explícita e validada de entidade, URL, email e base de dados; ausência de parâmetros obrigatórios deve impedir a operação com uma mensagem administrativa clara. Conta de base de dados dedicada e com privilégios limitados.
- Aceitação: instalação com identidade de teste não contém links nem envia mensagens para a entidade original. SMTP pode ser desativado de forma explícita para operação sem email.

### A11 — P1 — Uploads sem política uniforme de tipo e dimensão

- Evidência: `DocumentosUserController::criarSubmit()` e `TramitacaoFileService::guardarAnexos()` movem ficheiros sem uma lista de tipos e limites de dimensão equivalente à de `DocumentoUploader`. Nesse uploader, o MIME é detetado e guardado, mas não comparado com uma lista permitida. O controlador de utilizador pode servir tipos detetados com disposição inline.
- Consequência: as regras dependem do caminho usado; ficheiros ativos ou excessivos podem chegar ao armazenamento. HTML entregue inline na mesma origem representa risco adicional para a sessão. Não foi enviado qualquer ficheiro de teste.
- Ação: serviço único de validação antes da persistência, limites por ficheiro e pedido, identificação de conteúdo, tratamento de falhas e proibição de execução. Conteúdo ativo deve ser bloqueado ou servido como download/origem isolada.
- Aceitação: a mesma política aplica-se a todos os uploads; extensão/MIME incoerentes, ficheiros ativos e limites excedidos são recusados.

### A12 — P1 — Instalação de base de dados e atualizações não reproduzíveis

- Evidência: existem três migrações SQL manuais em `scripts/migrations/`; não foi encontrado um executor com histórico de versões aplicadas. `anferaltadocs.sql` contém INSERTs operacionais, motores mistos MyISAM/InnoDB e collations `utf8mb4_0900_ai_ci`. As migrações recentes convertem algumas tabelas para InnoDB, mas isso não comprova os motores efetivos da instalação atual.
- Consequência: o dump não é uma base limpa de distribuição, e compatibilidade entre MySQL/MariaDB não pode ser presumida. Atualizações dependem de passos manuais e algumas operações podem não ter rollback transacional.
- Ação: definir motor/versão suportados, esquema inicial limpo, sementes mínimas sem pessoas reais, criação segura do primeiro administrador e migrações versionadas. Separar utilizador de migração do utilizador normal quando possível.
- Aceitação: instalar do zero e atualizar a partir de uma versão anterior num ambiente descartável, preservando dados e permissões. Definir recuperação para alterações de esquema que não possam ser revertidas automaticamente.

### A13 — P1 — Autenticação sem limitação de tentativas demonstrada

- Evidência: `Auth::attempt()` verifica password e estado, mas não limita frequência nem incrementa/bloqueia tentativas falhadas. `tentativas_falhadas` existe no modelo e é reposto no login; isso não constitui proteção efetiva. Existem classes de dois fatores, mas o fluxo de login analisado estabelece logo a sessão e não foi encontrada integração dessas classes nas rotas carregadas.
- Ação: limitação por conta e origem, política de passwords consistente e possibilidade de exigir MFA administrativo. Não apresentar MFA como disponível até o fluxo ser integrado e verificado. Tornar o registo público configurável para instalações empresariais fechadas.
- Aceitação: ataques repetidos são limitados sem permitir bloqueio trivial de terceiros; MFA, se ativado, é obrigatório antes de obter sessão plenamente autorizada. Confirmar eventuais proteções externas no servidor de destino.

### A14 — P2 — Depuração, permissões de escrita e portabilidade operacional

- Evidência: `public/index.php:25` ativa erros e imprime mensagens, ficheiro e stack trace em exceções; `BaseController.php:35` ativa debug Twig. Há scripts públicos de teste de sessão/email, e `/test` é registada sem restrição de ambiente. O script de sessão imprime o identificador e o conteúdo da sessão. Estes caminhos não são necessariamente usados pelo front controller local externo.
- `App/Config/paths.php:50` e diversos serviços pedem permissões `0777`; a permissão final depende de umask/ACL. Logs, backups e temporários são escritos em diferentes locais dentro da árvore do código. `BackupsAdminController` usa comandos Windows para gerir Apache.
- Ação: produção sem exposição de erros ou diagnósticos; logs privados e com retenção; separar código imutável e volumes graváveis; definir dono/grupo e privilégios mínimos. Delegar serviços ao sistema operativo/orquestrador.
- Aceitação: processo web não precisa de escrever no código ou gerir serviços; produção não devolve stack traces nem disponibiliza scripts de diagnóstico.

### A15 — P2 — Contrato de dependências e pacote de distribuição incompletos

- Evidência: `composer.json` não declara versão de PHP nem extensões usadas diretamente, como `pdo_mysql`; `phpoffice/phpspreadsheet` usa `*`. Há `composer.lock`, que fixa versões para instalação, mas a próxima atualização com `*` não tem limite superior. Não foram encontrados Dockerfile, Compose, `.env.example` ou guia de instalação na raiz analisada. LibreOffice é uma dependência externa opcional do preview e deve ter comportamento suportado documentado.
- `LICENSE` contém MIT. Uma futura distribuição comercial deve respeitar a licença existente e as de terceiros; esta auditoria não altera a licença nem presume exclusividade comercial sobre cópias já licenciadas.
- Ação: declarar requisitos diretos, intervalos de versões suportados, instalação a partir do lock, inventário/licenças de terceiros e pipeline de criação/verificação do pacote. Documentar requisitos, backup, atualização, recuperação e diagnóstico sem segredos.
- Aceitação: pacote instala sem ferramentas ou ficheiros específicos da máquina de desenvolvimento, com requisitos verificados antes do arranque.

## Verificações iniciais e limites

| Verificação | Resultado |
| --- | --- |
| Sintaxe dos PHP em App, routes, public, cron, cli e scripts | 163 ficheiros, zero falhas em PHP CLI 8.4.0 |
| Registo de rotas sem dispatch e sem instanciar controladores | 317 entradas, 143 pares distintos; ações não resolúveis descritas em A06 |
| Composer validate --strict --no-check-publish | Avisos sobre licença e dependência sem limite; metadados de publicação ausentes. Estes últimos não são por si um bloqueio para uma aplicação privada |
| Composer check-platform-reqs --lock --no-dev | Requisitos declarados pelo lock satisfeitos no PHP CLI local; não comprova requisitos diretos omitidos nem a configuração do PHP web |
| Composer audit --locked --no-dev | Inconclusivo: falha curl/DNS ao contactar repo.packagist.org; não se pode afirmar ausência de vulnerabilidades |
| Ambiente de ferramentas | Composer 2.7.7 em PHP 8.4 emite avisos de depreciação; PHP tenta carregar Xdebug de um caminho E:/wamp64 inexistente |
| Git e configuração Apache local | Inspeção em leitura; ficheiros privados identificados sem reproduzir valores neste relatório |
| Base de dados e ambiente de produção | Não ligados nem alterados; motores, privilégios, volumes, proxies, TLS e backups reais precisam de validação no destino |
| Exploração HTTP, envio de email, upload, backup e restauro | Não executados |

Sintaxe válida não comprova comportamento válido: o controlador de perfil fora de PHP é um exemplo observado. Os scripts de validação existentes não foram executados indiscriminadamente porque alguns podem ligar à base de dados ou alterar o ambiente.

Pontos favoráveis observados: passwords verificadas com `password_verify`, rotação do identificador de sessão no login, cookies HttpOnly/SameSite, mecanismo CSRF, autorização e verificação de proprietário em vários controladores, armazenamento privado centralizado por constantes e validação de ZIP no serviço de backup de ficheiros. Estas medidas devem ser preservadas e tornadas uniformes.

## Sequência de correção proposta

1. Fechar a entrega pública não autorizada, corrigir fronteira web e SMTP e preparar rotação dos segredos expostos (A01–A04).
2. Consolidar bootstrap, rotas e operações de escrita por POST, mantendo os acessos legítimos (A05–A07).
3. Validar recuperação completa e tarefas autónomas; eliminar destinatários e domínios fixos (A08–A10).
4. Uniformizar uploads e autenticação; preparar esquema limpo e migrações (A11–A13).
5. Consolidar configuração de produção, requisitos e distribuição; executar instalação, atualização e recuperação num ambiente descartável (A14–A15).

Condição para piloto externo: P0 e P1 resolvidos, auditoria de dependências concluída com acesso à fonte de advisories e instalação/atualização/restauro completos demonstrados num ambiente isolado. O estado de cada correção após esta etapa encontra-se na tabela de atualização no início deste relatório.
