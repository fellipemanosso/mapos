# Gestão de atendimentos — etapa 1

O cadastro de clientes identifica quem contrata e responde pelo pagamento.
Na ficha da cliente, **Bebês e dependentes** registra nome, nascimento opcional
e relação com a responsável. Uma responsável pode ter vários dependentes.

No formulário de atendimento, selecione a responsável e depois quem recebe
o serviço. Em branco significa a própria cliente. O servidor rejeita um
dependente que não pertença à responsável selecionada. O acesso ao portal e
as cobranças continuam vinculados à responsável, e o nome do dependente
aparece nos detalhes do atendimento no painel e no portal.

## Atualização

Faça backup e execute `php index.php tools migrate` antes de acessar as telas.
A migration `20261111000000_add_dependentes` adiciona a tabela `dependentes`
e a referência opcional `os.dependente_id`, preservando atendimentos existentes.
As rotas internas e os nomes de tabelas da base Map-OS são mantidos.

Nesta etapa não há exclusão de dependentes pela interface, para preservar
o histórico. O cadastro pode ser corrigido pela ação Editar. Pacotes, sinal
e saldo, despesas específicas e automação de WhatsApp são etapas seguintes.
