# Acompanhamento dos pedidos de demonstração — AnferaltaDocs

Responsável pelo contacto: Anferalta Empreendimentos, Lda.
Caixa de receção: suporte@anferalta.com.

## Objetivo

Transformar cada pedido recebido numa conversa com próximo passo definido. O site prepara um email no dispositivo do visitante; o pedido só existe na caixa de suporte depois de o visitante confirmar o envio. A receção de email não cria, por si só, um registo na aplicação.

## Receção e primeira resposta

1. Verificar os pedidos recebidos e pesquisar conversas anteriores do mesmo interessado para evitar duplicações.
2. Atribuir uma referência, por exemplo DEMO-2026-001, e registar a data, o nome, o email, o contexto e o responsável pelo acompanhamento.
3. Marcar como **Recebido**. Definir uma próxima ação e uma data.
4. Responder na conversa original, idealmente até ao próximo dia útil. Este é um objetivo interno sugerido, não um prazo contratual anunciado ao cliente.
5. Confirmar os documentos a organizar, o número aproximado de utilizadores e a dificuldade principal. Pedir duas disponibilidades para a demonstração.
6. Após a resposta efetivamente enviada, marcar como **Contactado**.

## Estados e critérios

| Estado | Quando utilizar | Próxima ação habitual |
| --- | --- | --- |
| Recebido | Pedido recebido, ainda sem resposta da equipa | Responder e perceber o contexto |
| Contactado | Primeira resposta enviada | Aguardar contexto ou disponibilidade |
| Demonstração agendada | Dia, hora e meio confirmados pelas duas partes | Preparar e realizar a sessão |
| Demonstração realizada | Sessão concluída | Enviar resumo e avaliar proposta |
| Proposta enviada | Proposta concreta enviada ao interessado | Acompanhar a decisão |
| Concluído | O acompanhamento terminou | Registar o resultado e eventual passagem à implementação |

Ao concluir, indicar um resultado: **Avançar**, **Sem interesse**, **Sem resposta**, **Sem adequação** ou **Duplicado**. Não confundir conclusão do acompanhamento com instalação concluída ou contrato celebrado.

Não saltar automaticamente de um estado para outro só porque se abriu um modelo de email. A mudança deve refletir uma ação realizada.

## Preparação e realização da demonstração

Confirmar data, hora, fuso horário, duração e meio de reunião. Só incluir uma ligação depois de a reunião ter sido criada. Reservar cerca de 20 a 30 minutos:

- 5 minutos: compreender o problema e os participantes.
- 15 minutos: mostrar registo, encaminhamento, histórico e acessos, com exemplos fictícios do setor.
- 5 a 10 minutos: dúvidas, condições de instalação e próximo passo.

Usar uma instância de demonstração preparada para o efeito. Não mostrar documentos reais de clientes nem criar contas de produção para uma simples apresentação. Explicar apenas funcionalidades verificadas; evitar prometer integrações, assinatura eletrónica ou automatismos que não tenham sido confirmados.

## Resumo e proposta

Depois da sessão, confirmar por escrito as necessidades e o próximo passo acordado. Quando houver interesse, preparar uma proposta com âmbito, utilizadores previstos, alojamento, requisitos, instalação, formação, suporte, manutenção, responsabilidades por backups, custos e condições aplicáveis. Preencher valores e condições antes de enviar; os modelos não constituem uma oferta comercial.

Para instalação nos servidores da entidade, envolver a equipa de TI e validar infraestrutura, domínio, HTTPS, acessos e recuperação antes de assumir uma data de entrada em serviço.

## Acompanhamento

Se não houver resposta, fazer um contacto breve após cerca de três dias úteis. Se continuar sem resposta, enviar uma última mensagem após cerca de uma semana e encerrar como **Sem resposta**, permitindo retomar a conversa mais tarde. Estes intervalos são sugestões internas e podem ser ajustados ao que foi combinado.

Cada pedido aberto deve ter um responsável, uma próxima ação e uma data. Não criar lembretes automáticos ou enviar mensagens sem configuração e autorização próprias.

## Informação a guardar

O ficheiro `registo-pedidos.csv` é um modelo vazio, compatível com importação no Excel usando UTF-8 e separador ponto e vírgula. Guardá-lo numa localização interna com acesso restrito, fora da pasta pública do site. Não publicar pedidos, contactos ou notas em `www/`, no repositório de código ou nos pacotes de entrega.

Registar apenas o necessário ao acompanhamento. Não pedir palavras-passe, credenciais de servidores nem documentos pessoais para preparar uma demonstração. A equipa deve definir os seus procedimentos de acesso e conservação dos pedidos.

## Verificação diária sugerida

Consultar a caixa de suporte, responder aos novos pedidos, atualizar os estados e tratar as próximas ações vencidas. No final, cada conversa deve ter um próximo passo ou uma conclusão registada.

Os modelos em `modelos-email.md` são rascunhos para revisão e envio manual pela equipa. Nenhuma mensagem é enviada por estes ficheiros.
