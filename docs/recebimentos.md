# Orçamentos e recebimentos

Na tela de um atendimento, abra **Orçamento e recebimentos**.

1. Monte o orçamento na aba Serviços (e Produtos, se necessário). A taxa de deslocamento é um serviço separado, com duração zero. Ela entra no preço, sem aumentar a duração da agenda. O tempo de deslocamento continua sendo configurado no agendamento.
2. Defina o sinal previsto e o vencimento do saldo. O sinal compõe o preço: não é uma cobrança extra. Salvar as condições não lança uma receita antes de o dinheiro ser recebido.
3. Registre o valor efetivamente recebido, a data e a forma: Pix, dinheiro, cartão de crédito ou débito. Escolha Sinal, Pagamento parcial, Integral desde o início ou Quitação do saldo.
4. Confira os cartões Total, Recebido e Saldo a receber. A área da cliente também exibe recebido e saldo para os atendimentos que usam este fluxo.

Exemplo: serviço de R$ 200 + deslocamento de R$ 30 = R$ 230. Um sinal de R$ 50 cria uma receita paga de R$ 50 e uma receita pendente de R$ 180. Ao receber R$ 180, o pendente desaparece e ficam duas receitas pagas, somando R$ 230. Um pagamento integral desde o início cria somente a receita paga. O Pix estático passa a usar o saldo restante e deixa de ser exibido após a quitação.

## Confirmação e correções

O primeiro recebimento confirma os itens e valores do orçamento, mesmo que a configuração antiga permita editar atendimentos faturados. Orçamentos em Orçamento ou Negociação passam a Aprovado, com histórico. O recebimento não conclui o atendimento nem cancela sua agenda.

Para corrigir um recebimento, use **Estornar registro** e confirme. Isso corrige o controle interno; não movimenta dinheiro em bancos ou operadoras. O registro mantém valor original, data e usuário do estorno, mas seu valor contábil passa a zero. O saldo é recalculado. Depois de estornar todos os recebimentos, o orçamento volta a permitir alterações.

Lançamentos vinculados são gerenciados nesta tela, sem edição ou exclusão avulsa no Financeiro. Atendimentos com esse histórico não podem ser excluídos. Faturas criadas pelo fluxo anterior continuam no Financeiro e não são convertidas automaticamente, evitando duplicar receitas existentes.

## Permissões e consistência

- Visualizar: `vOs`.
- Salvar condições: `eOs`.
- Receber: `eOs` **e** `aLancamento`.
- Estornar: `eOs` **e** `eLancamento`.

Os valores são calculados pelo servidor em centavos. São recusados valor zero, pagamentos superiores ao saldo, datas futuras, operações em atendimentos cancelados e estornos pertencentes a outro atendimento. Uma chave única impede repetir o recebimento ao reenviar o mesmo formulário. No MariaDB, a transação bloqueia a linha do atendimento antes de conferir o saldo.

## Atualização

Execute **Configurações → Atualizar banco** ou `php index.php tools migrate` para aplicar `20261111020000_add_recebimentos_atendimentos`. A migration adiciona campos às tabelas `os` e `lancamentos`, sem alterar faturas legadas.
