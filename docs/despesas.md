# Despesas e compras parceladas

Abra **Financeiro → Despesas → Nova despesa**. Informe descrição, categoria, fornecedor e a forma de registrar:

- **À vista / lançamento único:** um lançamento, que pode ser marcado como já pago.
- **Compra parcelada:** informe o **total da compra**, o número de parcelas mensais (2 a 60) e o primeiro vencimento. Se houve entrada, informe seu valor e data de pagamento. A entrada é descontada do total antes da divisão.
- **Despesa recorrente:** informe o **valor de cada ocorrência**, frequência diária, semanal ou mensal e quantidade (até 366). É uma programação finita, sem renovação automática.

Exemplos: uma compra de R$ 1.200 em 3 vezes gera 3 parcelas de R$ 400. Com entrada de R$ 300 e 3 parcelas, gera uma entrada paga de R$ 300 e três parcelas de R$ 300. R$ 20 por dia na Meta durante 30 dias programa 30 lançamentos de R$ 20, totalizando R$ 600.

As categorias contemplam Meta, outras divulgações, insumos, combustível, equipamentos, transporte, sistemas, impostos e outras despesas. Compras antigas lançadas diretamente no Financeiro permanecem lá; não são convertidas automaticamente em grupos nesta tela.

## Pagamentos e documentos

Cada parcela ou ocorrência começa em aberto. Use **Registrar pagamento** na linha correspondente, marque **Já foi recebido ou pago**, informe data e forma de pagamento e salve. O sistema volta à despesa com os totais de pago e a pagar atualizados. A entrada, quando informada, já é registrada como paga. O controle de baixa configurado no sistema continua sendo respeitado.

Os lançamentos pertencem ao mesmo Financeiro usado pelo painel e relatórios: não há uma segunda cópia das despesas. Alterações ou exclusões individuais no Financeiro são refletidas nos totais da compra. A categoria e a programação originais ficam no grupo. Um lançamento desta compra não pode ser convertido em receita.

Na própria despesa, anexe comprovantes, recibos e documentos do fornecedor em PDF, JPG ou PNG. Os arquivos usam armazenamento protegido, limite de upload e validação de conteúdo já existentes. Downloads exigem `vLancamento`; anexar exige `eLancamento`. A documentação da compra fica no grupo, podendo incluir comprovantes de diversas parcelas.

## Consistência e atualização

Os cálculos usam centavos inteiros e distribuem o arredondamento entre as parcelas, sem perder centavos. Vencimentos mensais mantêm o dia original quando possível: 31/01, 28/02, 31/03. Datas de pagamentos futuros são recusadas. Um reenvio do mesmo formulário não duplica a compra.

Aplique `20261111040000_create_despesas` em **Configurações → Atualizar banco** ou `php index.php tools migrate`. A migration cria os grupos e comprovantes de despesas e adiciona `lancamentos.despesa_grupo_id`, sem alterar receitas ou pagamentos existentes.
