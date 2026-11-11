# Instalação Windows de raiz

O cenário definido é uma máquina com **Windows instalado**, sem PHP, MySQL ou servidor web, para utilização na **rede da empresa com domínio e certificado HTTPS**. O EXE não instala o sistema operativo.

Foi implementado o candidato de instalação com IIS/FastCGI, PHP NTS x64, MySQL LTS como serviço, contas separadas, configuração HTTPS por PFX, permissões privadas, migrações e primeiro backup completo. O [guia do EXE](instalador-windows-exe.md) contém os requisitos e os passos de operação.

**Estado: candidato compilado para ensaio, ainda sem homologação numa máquina Windows limpa e sem assinatura Authenticode.** Não confundir os testes isolados com validação completa da preparação do Windows.

## Verificação inicial independente

O comando abaixo apenas lê o estado da máquina; não altera funções Windows, serviços, certificados ou firewall:

```powershell
powershell.exe -NoProfile -File .\installer\windows\Test-CleanMachine.ps1 -Destination C:\AnferaltaDocs
```

O resultado JSON contém `ready` e as verificações individuais. Uma máquina com WAMP existente deve ser recusada. Se não for possível consultar portas ou serviços, o diagnóstico recusa prosseguir.

## Validação e limites

Os testes de desenvolvimento verificam os hashes dos requisitos, a extração segura, as regras de domínio/redes, a execução dos binários e a preparação das contas numa instância MySQL temporária ligada apenas a loopback. A instalação e recuperação da aplicação são ensaiadas numa base de teste, sem recorrer aos dados da instalação atual.

Continua necessária uma máquina virtual Windows limpa para verificar conjuntamente: elevação, funções IIS, instaladores Microsoft, reinícios e retoma, identidade do serviço MySQL, application pool, HTTPS com um certificado empresarial válido, ACL efetivas entre contas, firewall e acesso a partir de outra máquina. As falhas de download, inicialização parcial e certificado incorreto também fazem parte da homologação.

A configuração do DNS e a emissão/renovação do certificado dependem da infraestrutura da entidade. O instalador recebe o PFX; não solicita credenciais do DNS, não modifica o `hosts` e não instala uma CA raiz. Certificados de uma CA interna exigem a confiança corporativa apropriada no servidor e nos clientes.
