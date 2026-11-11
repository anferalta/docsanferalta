# Instalador Windows EXE

O `AnferaltaDocs-Setup.exe` abre o assistente de **instalação de raiz para a rede da empresa**: IIS/FastCGI, PHP, MySQL, aplicação e HTTPS. A aplicação e as bibliotecas vêm incluídas; os requisitos são descarregados de fontes oficiais, com versões e SHA-256 fixados.

**Estado: candidato de validação, sem assinatura Authenticode.** A compilação e os testes isolados não substituem a homologação numa máquina Windows limpa. Não utilizar ainda como uma versão homologada para produção.

## Antes de executar

- Windows 11 Pro/Enterprise/Education x64 ou Windows Server 2019+ com ambiente gráfico; Windows atualizado, sem reinícios pendentes e sem IIS, Apache, WAMP ou MySQL existentes. Windows Home e Server Core não fazem parte deste assistente.
- Conta de administrador, PowerShell do Windows de 64 bits, NTFS e pelo menos 10 GiB livres inicialmente. O espaço para documentos e backups deve ser dimensionado à parte.
- Internet para descarregar cerca de 330 MB de requisitos, ativar funções Windows e verificar certificados. Não é um instalador offline.
- Domínio completo da aplicação, por exemplo `documentos.empresa.pt`, e um IP estável para o servidor.
- Certificado `.pfx`/`.p12` com chave privada e cadeia HTTPS válida para esse domínio, mais a sua palavra-passe. A confiança na CA empresarial deve ser distribuída pela equipa de infraestrutura/GPO. O instalador não adiciona uma CA raiz nem emite certificados empresariais.

## Passos do assistente

1. Executar o EXE e aceitar a elevação UAC do Windows.
2. Escolher uma pasta nova, por exemplo `C:\AnferaltaDocs`.
3. Indicar o domínio sem `https://` e as redes autorizadas: `LocalSubnet` ou redes CIDR separadas por vírgulas.
4. Definir o nome, email e palavra-passe inicial do administrador, com pelo menos 14 caracteres.
5. Escolher o PFX e introduzir a sua palavra-passe no campo protegido.
6. Clicar em **Verificar e instalar**. As verificações iniciais recusam conflitos antes de preparar os serviços. O certificado é validado com a confiança do Windows, incluindo nome DNS, validade, utilização de servidor e revogação.
7. Se algum requisito exigir reinício, reiniciar quando conveniente e executar novamente o mesmo EXE, com a mesma pasta e os mesmos dados. Não há reinício automático.
8. No DNS da empresa, criar o registo que aponta o domínio para o IP deste servidor. Esta alteração é feita pela equipa que gere o DNS; o EXE não modifica o DNS nem o ficheiro `hosts`.
9. Testar `https://dominio-da-entidade/login` a partir de outra máquina autorizada. Usar explicitamente HTTPS: este candidato não configura um site HTTP nem redirecionamento na porta 80.
10. Alterar a palavra-passe inicial, guardar a chave de recuperação fora do servidor e ensaiar um restauro numa instalação isolada.

A instalação pode parar para revisão quando encontra uma inicialização MySQL incompleta ou um recurso existente diferente do registado. Não apaga diretórios de dados para repetir uma etapa. Não é um desinstalador nem um mecanismo de atualização de instalações concluídas.

## Estrutura e acessos

| Caminho | Utilização |
| --- | --- |
| `app/public` | Única raiz publicada pelo IIS. |
| `app/storage` | Armazenamento com escrita pela identidade do application pool. |
| `app/secrets` | Chave de recuperação e credencial da aplicação; apenas a credencial MySQL é concedida ao processo web. |
| `app/backups/Completos` | Primeiro backup completo encriptado e verificado. Acesso administrativo. |
| `bootstrap` | Estado de preparação e credenciais de root/manutenção MySQL; apenas Administradores e SYSTEM. Não enviar esta pasta para suporte. |
| `runtime` | PHP e MySQL instalados pelo assistente. |
| `data/mysql` | Dados MySQL; escrita apenas pela conta de serviço, Administradores e SYSTEM. |

O MySQL escuta apenas em `127.0.0.1:3306`. O serviço `AnferaltaDocsMySQL` usa LocalService com permissões concedidas ao SID do próprio serviço. A conta web `anferalta_app` recebe apenas SELECT/INSERT/UPDATE/DELETE na base da aplicação; a conta `anferalta_maintenance` recebe os privilégios de manutenção nessa base, sem privilégios globais ou GRANT OPTION.

O IIS usa o application pool `AnferaltaDocs`, com identidade própria, e o mesmo nome de site. A regra `AnferaltaDocs-HTTPS-LAN` abre TCP 443 nos perfis Domain/Private apenas para as redes indicadas. Políticas corporativas, outras regras existentes, encaminhamento entre sub-redes e o perfil de rede efetivo devem ser confirmados pela infraestrutura. Não é aberta a porta MySQL nem o perfil Public.

O primeiro backup é criado com a conta de manutenção e a chave privada gerada pelo instalador. O agendamento periódico, a cópia externa e a política de retenção devem ser configurados após definir a política da entidade. SMTP também é configurado posteriormente; não são enviados emails durante a instalação.

Para manutenção, abrir uma sessão administrativa e fornecer a conta de manutenção apenas nesse processo:

```powershell
$env:DB_USER = 'anferalta_maintenance'
$env:DB_PASS_FILE = 'C:\AnferaltaDocs\bootstrap\mysql-maintenance.key'
& C:\AnferaltaDocs\runtime\php\php.exe C:\AnferaltaDocs\app\cli\maintenance.php backup
Remove-Item Env:\DB_USER, Env:\DB_PASS_FILE
```

Consultar também [recuperação e instalação](recuperacao-e-instalacao.md).

## Integridade, retoma e distribuição

O `.sha256` ao lado do EXE permite comparar o ficheiro recebido. O catálogo `installer/windows/runtimes.json` fixa os downloads; um pacote diferente é recusado, mesmo que tenha o mesmo nome. Visual C++ Runtime e URL Rewrite também exigem assinatura Microsoft válida. Alterações de versões exigem rever o catálogo e reconstruir o EXE.

O assistente verifica os hashes do pacote da aplicação e dos ficheiros que vai retomar. Passwords não são passadas em argumentos de processo nem copiadas da instalação de desenvolvimento. A pasta de entrada privada guarda os dados necessários à retoma; após sucesso, remove as cópias de entrada PFX e as passwords redundantes. As credenciais de manutenção e a chave de recuperação continuam protegidas para operações administrativas.

A versão atual não tem assinatura Authenticode de uma entidade certificada. A assinatura empresarial exige um certificado de assinatura de código próprio e deve preceder a distribuição externa. O certificado HTTPS da aplicação tem uma função diferente e não serve para assinar o instalador.

## Reconstrução e testes

Na máquina de desenvolvimento, preparar Python, o compilador .NET Framework e `vendor/` exatamente correspondente ao `composer.lock`:

```powershell
./installer/windows/build.ps1
```

Para compilar uma variante de ensaio sem UAC, que continua a recusar preparação sem administrador:

```powershell
./installer/windows/build.ps1 -TestBuild
```

O argumento `--prepared-server` conserva o assistente anterior para infraestruturas já preparadas; não é o modo predefinido.

Fontes dos requisitos: [PHP Windows NTS/FastCGI](https://www.php.net/downloads.php?os=windows&version=8.4), [MySQL Community LTS](https://dev.mysql.com/downloads/mysql/8.4.html?os=3), [Visual C++ Runtime](https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist) e [IIS URL Rewrite](https://www.iis.net/downloads/microsoft/url-rewrite).
