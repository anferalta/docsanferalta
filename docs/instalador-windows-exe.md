# Instalador Windows EXE

O ficheiro `AnferaltaDocs-Setup.exe` instala a aplicação num servidor Windows **já preparado** com PHP 8.3+ de 64 bits, MySQL, Apache/IIS e HTTPS. Inclui a aplicação, as bibliotecas correspondentes ao `composer.lock`, as migrações e os guias. Não precisa de Composer no destino nem descarrega bibliotecas ao instalar.

## Passos do assistente

1. Escolher uma pasta vazia, o `php.exe` do servidor e a URL HTTPS.
2. Indicar uma base MySQL vazia e uma conta de instalação dedicada, com password.
3. Definir o primeiro administrador, com password de pelo menos 14 caracteres.
4. Opcionalmente, configurar SMTP e o ficheiro CA necessário ao PHP.
5. Rever o resumo e clicar em **Instalar**.

O assistente verifica hashes durante a extração, os requisitos PHP, a configuração e a base vazia antes de aplicar migrações e criar o administrador. As passwords são mascaradas na interface e comunicadas ao instalador PHP por ficheiros temporários com acesso restrito, nunca por argumentos de processo. Os ficheiros de entrada são removidos quando a instalação termina com sucesso.

Se a operação falhar, os dados de entrada e o estado ficam privados para diagnóstico/retoma. Não enviar esses ficheiros para suporte nem copiar o seu conteúdo para o chat. Ao repetir na mesma pasta, o assistente verifica o código extraído e retoma apenas a instalação registada. Uma instalação concluída não é substituída.

## Preparação da infraestrutura

O EXE não instala ou substitui PHP, MySQL, Apache ou IIS, não cria contas no MySQL e não modifica os certificados ou virtual hosts existentes. A conta Windows que o executa precisa de escrita no destino. Se a pasta for protegida, a política do servidor pode exigir execução como administrador.

No final, o administrador da infraestrutura deve:

- Configurar o servidor HTTPS com DocumentRoot na subpasta `public/`.
- Ajustar a conta/grupo do PHP para ler a configuração privada e escrever no armazenamento.
- Trocar a conta MySQL do processo web para uma conta de aplicação com privilégios mínimos, reservando a conta de instalação para manutenção.
- Entrar na aplicação, alterar a password inicial e validar os acessos.
- Criar um backup completo, guardar a chave fora do servidor e ensaiar um restauro isolado.

Se SMTP for configurado, o assistente testa TLS e autenticação sem enviar email. Uma falha SMTP posterior à instalação é comunicada para revisão, sem apagar a aplicação instalada.

Consultar o [guia completo](guia-instalacao.md) para os comandos e as permissões. O Windows precisa do .NET Framework 4.8 para executar o assistente.

## Integridade e assinatura

O ficheiro `.sha256` distribuído ao lado do EXE permite comparar a integridade do download. A compilação atual não tem assinatura Authenticode de uma entidade certificada. A assinatura empresarial requer o certificado de assinatura da organização e deve ser aplicada antes de uma distribuição externa; não é substituída por um certificado de desenvolvimento.

O pacote não contém o `.env`, credenciais, chaves, certificados locais, documentos, logs ou backups da instalação de desenvolvimento. Mantém as licenças dos componentes incluídos em `vendor/` e a licença da aplicação.

## Reconstruir o executável

Na máquina de desenvolvimento, preparar Python, o compilador .NET Framework e `vendor/` exatamente correspondente ao lock. Executar:

```powershell
./installer/windows/build.ps1
```

O processo gera `dist/AnferaltaDocs-Setup.exe`, o SHA-256 e o relatório de composição do pacote. O código-fonte do assistente está em `installer/windows/Setup.cs`.
