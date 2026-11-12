# Módulos de operação

Atualização: aplique a migration `20261112040000_add_operacao`. Ela cria o estoque de insumos, acrescenta campos de logística ao atendimento e habilita as novas permissões apenas no grupo Administrador (ID 1). Os demais grupos precisam ser configurados explicitamente.

## Permissões específicas

Em Configurações → Permissões, configure separadamente:

| Código | Acesso |
| --- | --- |
| `vFicha` | Consultar fichas, evolução, documentos e termos |
| `eFicha` | Criar fichas, evoluções, anexos e acompanhar retornos |
| `cModeloFicha` | Configurar perguntas e termos |
| `rFicha` | Exportar histórico clínico |
| `vInsumo` | Consultar lotes, custos, saldo e movimentos |
| `eInsumo` | Cadastrar insumos e registrar ou estornar movimentos |

Escrita em ficha, configuração de modelos ou exportação clínica implica consulta às fichas; movimentar insumos implica consulta ao estoque. `cSistema` e permissões financeiras não concedem acesso clínico. Quem só consulta não vê formulários de edição. Usuários com `cBackup` continuam tendo acesso à cópia completa do banco, inclusive seus dados privados; reserve essa permissão aos responsáveis pelo sistema.

## Estoque de insumos

O módulo **Insumos** controla materiais de consumo interno, separadamente do estoque de produtos para venda. Cadastre nome, unidade (un, ml, g ou cm) e mínimo. A unidade é definida no cadastro e deve ser a mesma nas entradas e saídas.

Quantidades aceitam até três casas decimais, sem separador de milhar: `1,250` significa uma unidade e um quarto. Cada entrada cria um lote com identificação, validade opcional, saldo e custo total da entrada. O custo unitário é rateado pelo lote. A despesa da compra deve ser registrada também em Despesas; a entrada de estoque não lança pagamento automaticamente.

Consumo exige um atendimento existente e não cancelado. O lote deve pertencer ao insumo, ter saldo suficiente e não estar vencido. Descarte permite registrar perdas, inclusive de lotes vencidos. A lista ordena os lotes por validade; o usuário confirma qual lote será consumido. Lotes vencidos não entram no saldo disponível nem podem ser consumidos em atendimento.

O histórico registra tipo, quantidade, custo, usuário, data e atendimento. Estornos preservam o registro e corrigem o saldo uma vez. Uma entrada só pode ser estornada depois que todas as suas saídas ativas forem estornadas. Estornos de estoque não alteram o financeiro. Atendimentos com histórico de consumo não podem ser excluídos.

## Orçamento em pacote

Depois de converter o lead em atendimento, abra **Criar sessões de pacote** no atendimento. Selecione apenas os serviços que representam sessões; taxas e deslocamentos não devem ser selecionados. Cada unidade contratada vira uma sessão com a duração do catálogo.

São aceitas de 1 a 100 sessões por serviço, até 200 no pacote. O sistema reutiliza a OS, os preços, o sinal, os pagamentos e os documentos existentes. Não cria outra receita nem duplica atendimentos. Se o pacote já existir, o fluxo abre o mesmo pacote. Serviços de duração zero não podem gerar sessões. Agendamento e realização continuam no módulo Pacotes.

## Endereço e deslocamento

No atendimento, use **Endereço e deslocamento** para definir local, distância estimada, minutos de viagem antes do atendimento e regra de cobrança combinada. A distância é informada pela profissional: não há consulta a mapas nem cálculo automático da taxa. A cobrança deve estar como serviço no orçamento.

O tempo salvo é sugerido em novos agendamentos abertos a partir daquele atendimento ou de suas sessões. O usuário pode ajustá-lo antes de salvar. A agenda reserva o intervalo anterior e verifica conflitos como nos demais horários. Alterar a logística não modifica preços, pagamentos ou reservas já existentes. O endereço e a regra combinada aparecem na área da própria cliente.

## Indicadores

O menu **Indicadores** exige `rOs` e `rFinanceiro`. A referência é mensal:

- Caixa: receitas recebidas e despesas pagas, pelas datas de pagamento, reaproveitando as regras do resumo mensal. Resultado de caixa é recebido menos pago, não uma apuração contábil de lucro.
- Conversão: contatos criados no mês, agrupados por origem, que já possuem atendimento vinculado no momento da consulta. Não representa a data da contratação.
- Serviços contratados: valores brutos dos serviços em atendimentos iniciados no mês, aprovados, em execução, finalizados, faturados ou confirmados financeiramente, excluindo cancelados. Não é receita recebida e não aplica descontos do atendimento.
- Consumo: custo rateado dos insumos efetivamente consumidos no mês, excluindo movimentos estornados. É informativo e não é descontado novamente do caixa, para não duplicar despesas da compra.

## Exportação

Em **Exportação de dados**, `rCliente` permite baixar contatos das clientes e `rFinanceiro` permite baixar lançamentos por período de vencimento. CSVs são UTF-8, com separador ponto e vírgula; células que poderiam executar fórmulas recebem proteção. Senhas não são exportadas.

Na ficha, **Exportar histórico desta pessoa** exige `rFicha` e gera NDJSON: um objeto JSON por linha, contendo cada ficha da mesma pessoa, evoluções e metadados dos documentos. Não inclui arquivos binários ou caminhos privados, nem chaves de reenvio dos formulários. Faça backup do armazenamento para preservar os anexos.

Downloads exigem POST, CSRF e as respectivas permissões. Não substituem um backup completo e devem ser guardados em local restrito.

## Área da cliente

Textos e navegação foram adaptados para atendimentos e serviços, removendo referências a oficina, equipamento e conserto das telas revisadas. Vínculos, pagamentos, NF, horários e isolamento entre clientes continuam usando as regras existentes. Fichas clínicas e insumos não são publicados no portal.

## Dependências externas ainda pendentes

A publicação em hospedagem, domínio/HTTPS, rotinas de backup automático, envio real de e-mails e conexão com a Evolution precisam dos dados e configurações da instalação. Captura automática de mensagens, caixa de atendimento do WhatsApp, lembretes e IA não fazem parte destes módulos de operação.
