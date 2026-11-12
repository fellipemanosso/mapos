# Custos e resultado por atendimento

Abra **Atendimentos → atendimento → Custos e resultado** com permissão de
visualizar atendimentos e relatórios financeiros. Para alterar custos, também
é necessária a permissão de editar atendimentos; consumo e estorno de insumos
exigem editar insumos. É possível completar custos depois de receber o pagamento.
Atendimentos cancelados ficam somente para consulta.

O total cobrado segue sendo composto pelos serviços e produtos vendidos, com
os descontos existentes. Para cobrar deslocamento, adicione um serviço chamado
Taxa de deslocamento. O combustível/custo real do percurso é lançado
separadamente nos custos internos. Taxas de cartão também são custos internos.

**Insumos não são produtos vendidos.** Cadastre-os em Insumos, registre a
entrada com quantidade e custo total da compra e, no atendimento, informe o
lote e a quantidade consumida. O custo é calculado com o valor daquele lote,
sem depender de alterações futuras nos preços. Consumos anteriores registrados
no módulo Insumos já aparecem na tela: não os registre de novo.

O custo total da entrada começa vazio e deve ser informado explicitamente.
Para uma amostra ou outro lote com custo conhecido igual a zero, digite **0,00**.
Um valor ainda desconhecido não equivale a zero: confira o custo antes de
registrar a entrada. Consumo e descarte continuam usando o custo do lote, sem
pedir um novo valor. Lotes e movimentos anteriores não são alterados.

Os botões de estorno de consumo e de outros custos abrem uma confirmação com
o material ou a descrição e o valor. **Cancelar** ou Esc fecha sem alterar dados;
somente a confirmação efetua o estorno e preserva o histórico.

Em Serviços, use **Insumos padrão** para definir as quantidades por unidade do
serviço. Ajustes e remoções podem ser feitos em várias linhas e salvos juntos; a
adição de um material é uma ação separada. Na tela de custos do atendimento,
**Conferir materiais antes de baixar** compara composição, consumo já registrado
e saldo elegível. Informe o total real; o sistema baixa apenas a diferença,
priorizando lotes por validade. A confirmação é atômica se faltar estoque.
Mudanças posteriores na composição não alteram o histórico. Para reduzir consumo
já registrado, estorne-o primeiro.

O custo de compra de um produto vendido é informado em Outros custos diretos,
tipo Custo de produto vendido. Não há vínculo automático com o preço de compra
do catálogo. Assim o valor informado preserva o custo real daquela venda.

Resultado previsto = total cobrado − insumos consumidos − outros custos diretos
ativos. Recebido menos custos diretos usa somente os pagamentos recebidos e
não considera o saldo a receber. Nenhum dos dois é lucro líquido, pois despesas
gerais e tributos não são rateados automaticamente.

Custos não criam despesas ou alteram recebimentos. Registre o pagamento das
compras também em Despesas para o caixa; nesta tela, não lance a compra inteira
novamente: somente o consumo daquele atendimento. O histórico de estornos é
preservado. A impressão, o orçamento e a área do cliente não carregam esses
dados nem recebem campos novos de custo.

## Roteiro de teste

1. Cadastre Gaze (un) e dê entrada de 10 unidades por R$ 20,00 em um lote válido.
2. Crie um serviço de R$ 150,00 e configure 2 unidades de Gaze como padrão.
3. Crie um atendimento, adicione o serviço e Taxa de deslocamento de R$ 20,00.
4. Em Custos e resultado, aplique o padrão: consumo R$ 4,00 e saldo do lote 8 un.
   Repita o botão: o estoque e custo devem continuar iguais.
5. Registre deslocamento real de R$ 10,00 e taxa de cartão de R$ 5,00.
   Total cobrado R$ 170,00; custos R$ 19,00; resultado previsto R$ 151,00.
6. Receba um sinal de R$ 50,00: saldo a receber R$ 120,00 e recebido menos custos
   R$ 31,00. Após quitar, recebido menos custos deve ser R$ 151,00.
7. Estorne a taxa: custos R$ 14,00 e resultado R$ 156,00. Estorne o consumo:
   o estoque volta a 10 unidades e o custo de insumos fica zero.
8. Imprima orçamento/cupom e abra a área da cliente: devem aparecer serviço,
   deslocamento cobrado e eventuais produtos vendidos, nunca Gaze, taxa de
   cartão, combustível interno ou resultado.


## Conferência de materiais

Em Custos e resultado, abra **Conferir materiais antes de baixar**. A prévia soma a composição de todos os serviços pela quantidade contratada e mostra o consumo já registrado, inclusive manual, e o saldo não vencido. Informe o **total realmente usado**, não apenas o adicional. A confirmação baixa somente a diferença, por validade. Quantidades inferiores às já registradas exigem estorno primeiro.

A confirmação é transacional: faltar qualquer material cancela todas as baixas dessa confirmação. Reenvios de uma prévia antiga são recusados; confirmar novamente o mesmo total atualizado não gera consumo adicional. Mudanças nos serviços, composição ou consumo invalidam a conferência. Serviços sem composição continuam exigindo verificação e lançamento manual. Consumo com custo zero gera aviso para revisar as compras. Resultado previsto considera apenas custos registrados e não equivale a lucro líquido.

Na composição do serviço, cada linha permite salvar uma quantidade ou remover o material explicitamente. Na movimentação de estoque, os campos acompanham o tipo escolhido e mantêm o rascunho ao alternar as opções. O custo da entrada deve ser informado; digite **0,00** se o custo conhecido for realmente zero. A conferência pode ser retomada após registrar uma entrada, e os campos digitados são restaurados quando a prévia continua válida. Links na prévia levam diretamente à entrada do insumo.

Atualização: migration `20261113010000_add_conferencia_materiais` cria apenas o registro interno de conferência. Não altera os saldos ou consumos existentes.
