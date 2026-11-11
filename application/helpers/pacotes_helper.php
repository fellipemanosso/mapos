<?php

/** Composição e preço contratados, com limites e valores calculados no servidor. */
function pacoteDoFormulario(array $post, array $catalogo): array
{
    $nome = is_string($post['nome'] ?? null) ? trim($post['nome']) : '';
    $erros = [];
    if ($nome === '' || mb_strlen($nome) > 150) {
        $erros['nome'] = 'Informe um nome com até 150 caracteres.';
    }
    $linhas = [];
    $total = 0;
    $quantidade = 0;
    $selecionados = is_array($post['servicos'] ?? null) ? $post['servicos'] : [];
    foreach ($selecionados as $id => $entrada) {
        if (! is_array($entrada)) {
            $erros['_geral'] = 'Confira a composição do pacote.';
            continue;
        }
        $qtd = $entrada['quantidade'] ?? '';
        if ($qtd === '' || $qtd === '0') {
            continue;
        }
        $servico = null;
        foreach ($catalogo as $item) {
            if ((string) $item->idServicos === (string) $id) {
                $servico = $item;
                break;
            }
        }
        $preco = valorDecimal($entrada['preco'] ?? null);
        if (! is_string($qtd) || ! ctype_digit($qtd) || (int) $qtd < 1 || (int) $qtd > 100
            || ! $servico || (int) $servico->duracao_min < 1 || (int) $servico->duracao_min > 1440
            || $preco === null || financeiroCentavos($preco) <= 0) {
            $erros['_geral'] = 'Cada serviço deve ter de 1 a 100 sessões, duração configurada e preço por sessão maior que zero.';
            continue;
        }
        $linhas[] = ['servicos_id' => (int) $id, 'nome' => $servico->nome, 'duracao_min' => (int) $servico->duracao_min, 'quantidade' => (int) $qtd, 'preco' => $preco];
        $quantidade += (int) $qtd;
        $total += (int) $qtd * financeiroCentavos($preco);
    }
    if ($quantidade < 1 || $quantidade > 200 || $total > 9999999999) {
        $erros['_geral'] = 'Inclua de 1 a 200 sessões e mantenha o total até R$ 99.999.999,99.';
    }
    return [['nome' => $nome, 'itens' => $linhas, 'total' => $total, 'quantidade' => $quantidade], $erros];
}

function pacoteProgresso(array $sessoes): array
{
    $realizadas = 0;
    $agendadas = 0;
    foreach ($sessoes as $sessao) {
        if ($sessao->realizado_em) {
            $realizadas++;
        } elseif ($sessao->agenda_id && ! (int) $sessao->cancelado) {
            $agendadas++;
        }
    }
    return ['total' => count($sessoes), 'realizadas' => $realizadas, 'restantes' => count($sessoes) - $realizadas, 'agendadas' => $agendadas];
}
