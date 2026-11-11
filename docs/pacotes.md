# Pacotes e sessões

Abra **Pacotes e sessões → Novo pacote**. Informe o nome, a cliente responsável, o bebê ou dependente (opcional), a profissional e a quantidade de sessões de cada serviço. O servidor confere o vínculo do bebê com a responsável.

O preço por sessão pode ser negociado para o pacote, sem alterar o preço avulso do catálogo. Exemplo: três consultas de R$ 150 formam um pacote de R$ 450. A soma aparece durante o preenchimento e é recalculada pelo servidor. São permitidas de 1 a 200 sessões, até 100 por serviço. Serviços sem duração configurada não entram como sessão; taxas de deslocamento continuam sendo itens financeiros separados.

O cadastro cria um único atendimento, seus itens financeiros e as sessões individuais. **Orçamento e recebimentos** abre o controle de sinal, pagamentos parciais e saldo desse atendimento. Agendar ou realizar uma sessão não cria novas receitas. A composição das sessões é definida no cadastro; para negociar preço antes do primeiro recebimento, use os descontos do atendimento.

## Agenda e consumo

- **Disponível:** sem reserva ativa, incluindo reservas canceladas.
- **Agendada:** possui horário reservado, mas ainda não foi realizada.
- **Realizada:** marcada explicitamente após o término do horário. Diminui as sessões restantes.

Use **Agendar sessão** para abrir a agenda com atendimento, serviço e duração preenchidos. A duração pode ser ajustada naquele horário. As regras de conflito, deslocamento e intervalo continuam valendo. Uma sessão só pode ter uma reserva ativa, mesmo que sejam profissionais diferentes.

Cancelar o horário libera a sessão para reagendar, sem consumir uma sessão ou apagar pagamentos. Uma sessão realizada precisa ser reaberta antes de cancelar o horário. **Reabrir registro** corrige uma conclusão lançada por engano, devolvendo a sessão às restantes e preservando o agendamento.

Os registros preservam nome e duração do serviço na contratação. A área da cliente mostra sessões realizadas e restantes no próprio atendimento, respeitando a checagem de titularidade já existente.

## Permissões e atualização

Visualizar exige `vOs`; criar pacote exige `aOs` e `eOs`; agendar, concluir e reabrir sessões exigem `eOs`. As operações usam POST com CSRF. Reservas e conclusão usam transações e bloqueio da sessão no MariaDB. O cadastro tem uma chave única para não duplicar um reenvio do mesmo formulário.

Atendimentos vinculados a pacotes não podem ser excluídos, para preservar sessões e histórico. O cancelamento do atendimento suspende novos agendamentos e conclusões sem apagar os registros.

Aplique a migration `20261111030000_create_pacotes` por **Configurações → Atualizar banco** ou `php index.php tools migrate`. Ela cria `pacotes`, `pacote_sessoes` e o vínculo de sessão na agenda, sem modificar atendimentos ou pagamentos existentes.
