# Ensaio de instalação na máquina de desenvolvimento

Data: 16/11/2026. Ensaio executado numa pasta nova, sem instalar serviços, alterar o IIS, firewall, DNS, certificados ou configurações do WAMP.

## Resultado

| Verificação | Resultado |
| --- | --- |
| Autoteste do EXE: assistente de raiz e recursos incorporados | 30 verificações, sem falhas |
| Autoteste do EXE: extração, pacote, requisitos e retoma | 17 verificações, sem falhas |
| Instalação com PHP e MySQL próprios, esquema, migrações, administrador e backup | 18 verificações, sem falhas |
| Formulários HTTP de login, recuperação e registo | 3 respostas 200 com campo CSRF |

O candidato de ensaio foi reconstruído com o código disponível nesta data. O pacote contém 2564 ficheiros e as dependências correspondem ao composer.lock. Não inclui configurações, credenciais ou dados operacionais da instalação existente.

EXE testado: `dist/AnferaltaDocs-Setup-Test.exe`.

SHA-256: `80d937c40c7402f72d0d547ef7e171c99cc2ab79f40d003adf489029125412fa`.

O EXE de teste usa manifesto sem elevação automática para os autotestes. Não substitui o candidato de instalação com elevação nem representa uma nova distribuição assinada. O ZIP candidato de distribuição e o EXE normal não foram reconstruídos neste ensaio.

## Diretório independente

Instância de ensaio:

`C:\wamp\www\anferalta\anferaltadocs\dist\bootstrap-isolated-d4306c7bf9f048d6b4b2514b88dd8a58`

Contém PHP, MySQL, dados, aplicação e relatórios próprios. O MySQL temporário utilizou a porta livre 54169 em loopback. As credenciais e o administrador são exclusivos do ensaio. Não foram enviados emails nem utilizados documentos reais.

Relatórios:

- `test-report.json` na pasta da instância.
- `web-test-report.json` na pasta da instância.
- `dist/ensaio-exe-20261116/bootstrap.json`.
- `dist/ensaio-exe-20261116/interface.json/self-test.json`.

Os processos temporários foram terminados no final. A instância não ficou publicada num serviço web permanente. Os ficheiros foram conservados para revisão; a pasta contém segredos de teste e não deve ser publicada nem enviada integralmente para suporte.

## Limites da validação

O diagnóstico apenas de leitura recusou corretamente a preparação de raiz nesta máquina: portas 80, 443 e 3306 ocupadas, serviços WAMP existentes, reinício pendente e processo sem elevação administrativa. Um diretório diferente não isola estes recursos do Windows.

Este ensaio confirma o núcleo de instalação e a abertura dos formulários na aplicação instalada. A verificação HTTP temporária não valida HTTPS, autenticação completa, alteração de palavras-passe, envio SMTP, publicação IIS/FastCGI, ACL das identidades de serviço, regras de firewall, acesso noutra máquina ou comportamento após reinício.

Para homologar o EXE de instalação de raiz continua a ser necessário um Windows separado e preparado para o ensaio, conforme `homologacao-instalador-windows.md`.

## Repetição

O teste de integração cria sempre uma pasta nova:

```powershell
./installer/windows/Test-IsolatedBootstrap.ps1
```

O teste web aceita apenas uma instância de integração aprovada sob `dist/bootstrap-isolated-*`; arranca e termina os seus próprios processos:

```powershell
./installer/windows/Test-IsolatedWeb.ps1 -Fixture 'CAMINHO_DA_INSTANCIA'
```

Não executar `Provision.ps1` diretamente na máquina WAMP. Não tentar libertar portas parando os serviços existentes para contornar a verificação do instalador.
