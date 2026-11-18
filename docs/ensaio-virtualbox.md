# Ensaio do instalador no VirtualBox

## Máquina preparada em 17/11/2026

- Nome: AnferaltaDocs-Homologacao.
- Identificador: fefe428f-09e2-4b6c-bd53-b87963f1934f.
- Pasta: `dist/virtualbox/AnferaltaDocs-Homologacao`.
- Windows 11 x64, firmware EFI e TPM 2.0.
- 4096 MB de RAM, 2 processadores, disco virtual VDI dinâmico de 64 GB.
- Rede NAT; sem encaminhamento de portas configurado neste ensaio.
- Área de transferência e arrastar/largar desativados.
- ISO ligada: `C:/Users/Administrador/Downloads/Win11_24H2_Portuguese_x64.iso`.

As VMs antigas não foram alteradas. Nenhuma configuração do WAMP foi alterada.

## Preparar o Windows

1. Na janela da VM, iniciar o instalador da ISO. Se aparecer o pedido para arrancar pelo DVD, premir uma tecla dentro da janela.
2. Instalar uma edição Windows Pro, Enterprise ou Education abrangida pela licença do utilizador. A edição Home não cumpre o alvo atual do instalador.
3. Escolher apenas o disco virtual vazio de 64 GB desta VM. Não ligar discos físicos do anfitrião.
4. Concluir a aceitação da licença, as opções iniciais e a criação da conta diretamente na VM. Não partilhar palavras-passe na conversa.
5. Instalar atualizações e concluir os reinícios pendentes.
6. Chegando ao ambiente de trabalho, desligar o Windows normalmente e criar um snapshot com o nome `Windows-limpo`, antes de instalar a aplicação.

O Windows ainda não foi instalado ou homologado pelo simples arranque da VM. A instalação, a licença e a ativação são etapas próprias.

## Ensaio da aplicação

Depois do snapshot, reconstruir o EXE normal com elevação e o pacote candidato a partir do código atual. A variante `Setup-Test.exe` é destinada a autotestes, não à homologação final de serviços.

Preparar o domínio e o certificado de teste segundo a política do instalador. Não reutilizar credenciais ou certificados de produção. Planear a validação de rede e HTTPS antes de executar a instalação de raiz; a rede NAT inicial destina-se a preparar o Windows e descarregar requisitos.

Seguir `homologacao-instalador-windows.md`, registando resultados, falhas e eventuais reinícios. Não contornar as verificações de certificado ou modificar o WAMP para libertar portas: as portas do convidado são independentes das do anfitrião.
