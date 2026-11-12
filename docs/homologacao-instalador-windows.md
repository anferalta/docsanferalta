# Ensaio do instalador Windows pela equipa de TI

Esta versão é um **candidato de validação**. Os testes de desenvolvimento usam diretórios e uma instância MySQL isolados; ainda não homologam a preparação integral do Windows. Executar os passos seguintes numa VM descartável, com snapshot, antes de instalar em produção.

## Preparar o ensaio

1. Usar Windows 11 Pro/Enterprise/Education x64 ou Windows Server 2019+ com ambiente gráfico. Concluir as atualizações e reinícios pendentes.
2. Confirmar ausência de IIS, Apache/WAMP e MySQL/MariaDB. Reservar pelo menos 10 GiB livres em NTFS, além do espaço necessário para os dados e backups da entidade.
3. Preparar uma conta administrativa, Internet e um IP estável. Configurar o DNS de teste para o IP da VM.
4. Obter um PFX de teste empresarial, com chave privada e SAN correspondente ao domínio. A CA deve ser confiável no servidor e nos clientes; os pontos de consulta de revogação devem estar acessíveis. Não usar certificados ou passwords de produção num ensaio descartável.
5. Comparar o SHA-256 do EXE com o ficheiro distribuído. A compilação candidata está sem assinatura Authenticode; esse estado deve ser registado, não confundido com o certificado HTTPS da aplicação.

## Critérios de aceitação

| Ensaio | Resultado esperado |
| --- | --- |
| Iniciar o EXE | Solicita elevação UAC; mostra domínio, redes autorizadas, administrador e PFX. |
| Certificado inválido, expirado, não confiável ou para outro nome | Recusa antes de preparar serviços. Nenhuma CA raiz é adicionada. |
| Windows incompatível, reinício pendente, porta ocupada ou serviços existentes | Recusa a preparação de raiz; não para nem substitui serviços alheios. |
| Instalação normal | IIS/FastCGI, PHP, MySQL, esquema e administrador preparados; resultado final aponta para a URL HTTPS. |
| Requisito pede reinício | Comunica a necessidade e termina sem reiniciar o Windows automaticamente. |
| Retomar após reinício/falha | Aceita apenas a mesma pasta e dados; verifica ficheiros e etapas registadas. Inicialização MySQL parcial exige revisão, sem apagar o diretório de dados. |
| Falha de Internet ou pacote alterado | A instalação para; nenhum pacote com SHA-256 incorreto é executado ou extraído. |
| Repetir sobre instalação concluída | Recusa substituir a instalação; indica manutenção. |
| Serviço MySQL | `AnferaltaDocsMySQL` arranca automaticamente após reiniciar e usa LocalService com o SID do serviço. |
| Conta web | Application pool `AnferaltaDocs` usa identidade própria; apenas a pasta `app/public` é publicada. |
| ACL efetivas | Conta web consegue escrever em `app/storage` e ler a credencial de aplicação; não consegue alterar o código nem ler `bootstrap`, a chave de recuperação ou os backups completos. Serviço MySQL escreve nos seus dados e não lê credenciais administrativas. |
| Base de dados | Conta da aplicação permite CRUD e recusa DDL, privilégios globais e operações reservadas à manutenção. MySQL escuta apenas em loopback. |
| HTTPS local | `/login` devolve o formulário com certificado válido para o domínio. |
| Ficheiros privados | `/.env`, `/secrets/database-password.txt`, `/vendor/autoload.php`, `/storage/installation-state.json` e `/web.config` devolvem 403/404. |
| Acesso na rede | Outra máquina autorizada abre a URL HTTPS, autentica e consegue criar, consultar e descarregar um documento de teste. |
| Restrições de rede | TCP 443 disponível apenas conforme as redes/perfis aprovados; MySQL indisponível remotamente. Rever também regras/GPO preexistentes, pois permissões de firewall são cumulativas. |
| Reinício final | Site, HTTPS, sessões e base continuam funcionais, sem executar novamente o instalador. |
| Recuperação | Primeiro backup completo existe, é verificável e permite restauro integral numa segunda instância isolada e vazia. |

Não alterar o DNS público nem utilizar documentos reais para este ensaio. O acesso é diretamente por HTTPS: esta versão não instala redirecionamento HTTP. O SMTP, o agendamento de backups, a retenção e a cópia externa seguem a política da entidade e são configurados após a instalação.

## Registo e entrega

Registar versão/build do Windows, SHA-256 do EXE, momento do teste, domínio de teste, resultados e eventuais falhas. Os diagnósticos ficam na pasta privada `bootstrap`; podem conter dados de configuração. Não anexar a pasta inteira, PFX, passwords ou chaves a tickets. Partilhar apenas uma descrição e excertos revistos sem segredos.

Depois de todos os critérios passarem, a equipa responsável pode homologar a versão e aplicar a assinatura empresarial ao executável. A assinatura altera o SHA-256: regenerar o ficheiro de integridade e o pacote de distribuição após assinar.

Consultar o [guia do EXE](instalador-windows-exe.md) para a operação do assistente e o [guia de recuperação](recuperacao-e-instalacao.md) para o restauro.
