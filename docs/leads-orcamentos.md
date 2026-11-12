# Leads e orçamentos

Em **Leads e orçamentos**, cadastre o nome da cliente ou responsável, telefone brasileiro com DDD, bebê quando houver, origem e necessidade. O contato pode ser salvo sem valores. Observações internas ficam fora da impressão.

## Negociação

As etapas são Novo contato, Em negociação, Orçamento enviado, Aguardando sinal, Convertido e Perdido. Marcar como enviado é apenas um registro manual; esta etapa não envia mensagens ou integra webhooks da Evolution. Para marcar perdido, informe o motivo; é possível reabrir a negociação escolhendo outra etapa.

Defina uma data de retomada e use **Até hoje, em aberto** para acompanhar contatos pendentes. Perdidos e convertidos não aparecem nesse filtro. Busca e etapa são preservadas na paginação.

## Orçamento

Selecione as quantidades dos serviços e seus preços negociados. Para deslocamento, inclua o serviço Taxa de deslocamento do catálogo com quantidade 1 e o valor acordado. Total e sinal são validados no servidor. O sinal pode ser zero ou o valor integral; não pode ultrapassar o total.

A validade é opcional; um orçamento vencido precisa ser revisado antes da conversão. **Imprimir orçamento** abre uma versão para a cliente com identificação do emitente quando configurada, serviços, sinal, saldo e condições. Use Ctrl+P para imprimir ou salvar em PDF. Essa página exige login e não é um link público para compartilhar.

## Conversão

Após confirmar a contratação, escolha a cliente existente ou a opção de criar cadastro e a profissional ativa. Cadastros com o mesmo telefone normalizado são indicados para revisão; criar uma nova cliente é bloqueado quando existe uma correspondência. O telefone é comparado após retirar pontuação e acrescentar o código 55 quando necessário. Telefones diferentes ou números antigos ainda exigem conferência manual.

Se houver bebê, o sistema reutiliza o dependente de mesmo nome da responsável escolhida ou cria esse vínculo. Confira homônimos e complete os dados no cadastro depois. A nova cliente recebe uma senha aleatória não divulgada e e-mail vazio; a liberação do portal deve ser feita pelo fluxo de cadastro existente.

O atendimento nasce Aprovado, com serviços e sinal previsto. Não há pagamento ou reserva de horário automática: registre o recebimento efetivo e agende no módulo correspondente. Quantidades de serviços não criam um pacote de sessões automaticamente; o módulo Pacotes mantém seu fluxo próprio.

Reenvios da conversão retornam o mesmo atendimento. Alterações concorrentes exigem reabrir o contato. Após a conversão, o orçamento comercial fica preservado e os ajustes posteriores são feitos no atendimento. Atendimentos vinculados a leads não podem ser excluídos, inclusive pela API.

## Permissões e atualização

Visualizar, listar e imprimir exige `vOs`; editar/cadastrar exige `aOs` e `eOs`; atualizar etapas exige `eOs`. Converter exige `aOs`, `eOs` e `aCliente`. Formulários usam CSRF e ações de etapa/conversão aceitam somente POST.

Aplique a migration `20261112020000_create_leads` pelo botão **Atualizar banco de dados**. A integração com captura automática de leads pelo WhatsApp será feita separadamente.
