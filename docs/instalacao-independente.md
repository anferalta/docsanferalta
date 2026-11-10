# Instalacao independente

Comecar pelo [guia de instalacao passo a passo](guia-instalacao.md).

A aplicacao dispoe de esquema inicial limpo, instalador CLI, migracoes com historico/checksums e backup/restauro completo encriptado para um destino vazio. O servidor local passou na verificacao HTTPS e de isolamento. Estes resultados nao homologam automaticamente um servidor de cliente.

- [Instalacao, migracoes, backup e recuperacao](recuperacao-e-instalacao.md)
- [Validacao do servidor](validacao-servidor.md)
- [Evidencias da auditoria e estado das correcoes](auditoria-instalacao-independente-2026-11-10.md)

## Percurso de implantacao

Para uma copia limpa, o ponto de entrada recomendado e `php install.php`. O [guia do instalador](instalador.md) explica o assistente, a simulacao e a execucao automatica.

1. Preparar PHP 8.3+ de 64 bits e MySQL. Instalar dependencias pelo composer.lock e verificar a plataforma.
2. Configurar uma base vazia, identidade/URL, destinatarios e ficheiros privados. Criar uma chave de backup propria; nao reutilizar as credenciais nem a chave desta instalacao.
3. Executar `php cli/maintenance.php install` com o email do administrador e um ficheiro privado com a password inicial, conforme o guia detalhado.
4. Servir exclusivamente `public/` em HTTPS. Configurar o certificado e os privilegios minimos apropriados a entidade.
5. Executar os validadores, criar um backup e ensaiar o restauro num destino isolado antes de encaminhar trafego.
6. Nas atualizacoes, executar `php cli/maintenance.php migrate`, que cria primeiro o backup completo de uma base existente.

A revogacao da credencial SMTP antiga foi confirmada pelo responsavel. A nova credencial local e lida por `MAIL_PASS_FILE` e o teste real `php cli/smtp-check.php` passou com TLS, sem enviar mensagens. Em cada entidade, configurar uma credencial propria e repetir essa verificacao.

## Distribuicao

`python scripts/criar_pacote_fonte.py` exporta por lista de ficheiros permitidos, incluindo as migracoes e ferramentas de manutencao. Exclui Git, vendor, segredos, certificados locais, uploads, logs e backups. O manifesto regista os hashes dos ficheiros. Instalar as dependencias pelo lock no destino ou num processo de build controlado.

Nao distribuir o projeto original por copia integral ou clone: o historico Git antigo ainda contem dados operacionais e configuracao. O `.gitignore` nao remove ficheiros ja versionados. A entrega externa deve usar o pacote limpo e respeitar a licenca e os componentes de terceiros. O Docker/instalador grafico e a homologacao da infraestrutura de cada cliente pertencem a uma etapa de distribuicao posterior.
