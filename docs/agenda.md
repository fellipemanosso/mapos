# Agenda de atendimentos

Execute `php index.php tools migrate` para criar a agenda e adicionar
`servicos.duracao_min`. Configure a duração em Serviços. O valor zero é
reservado a itens sem execução, como taxa de deslocamento; serviços antigos
recebem zero até serem configurados, sem inventar uma duração para eles.

Em Agenda, selecione um atendimento e marque os serviços. A duração é a soma
dos serviços selecionados ou o valor informado para aquela sessão. Essa
seleção organiza o horário; os itens e valores do orçamento continuam sendo
gerenciados no atendimento. Alterar a duração do catálogo não muda reservas
existentes. Cada sessão de um pacote pode ter uma reserva separada.

O início e o término são horários locais de America/Sao_Paulo. Deslocamento
reserva minutos antes do início e intervalo reserva minutos após o término.
Bloqueios de almoço ou indisponibilidade participam da mesma conferência.
O término de uma reserva pode coincidir com o início ocupado da seguinte.
O banco serializa a gravação por profissional para conferir conflitos também
quando duas reservas são salvas simultaneamente.

O calendário mensal em grade é a visão padrão. Clique no dia para ver seus
horários ou na reserva para abrir sua edição. As visões diária e semanal
continuam disponíveis. O mês inclui semanas completas; dias de outros meses
aparecem com destaque reduzido. Dias com mais de duas reservas têm um link
para a lista completa. A agenda admite várias
profissionais, mas não permite sobreposição para a mesma profissional.
Reservas canceladas e atendimentos com status Cancelado liberam o horário.
Cancelar uma reserva preserva o atendimento, os itens e os pagamentos.
Não há definição de expediente fixo nem sincronização com calendários externos.
O portal do cliente mostra apenas os horários do próprio atendimento; criar
ou alterar reservas exige eOs, e visualizar a agenda interna exige vOs.

A grade mensal usa células de altura fixa, com horário e título em uma linha. Até duas reservas aparecem por dia; o link +N mais abre a lista completa sem aumentar a célula.
