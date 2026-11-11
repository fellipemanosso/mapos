<?php

const RECEBIMENTOS_FORMAS = ['Pix' => 'Pix', 'Dinheiro' => 'Dinheiro', 'Cartão de Crédito' => 'Cartão de crédito', 'Cartão de Débito' => 'Cartão de débito'];

/** Validação monetária em centavos; não aceita zero, negativos ou casas excedentes. */
function recebimentoDoFormulario(array $post, string $hoje): array
{
    $texto = static fn ($campo) => is_string($post[$campo] ?? null) ? trim($post[$campo]) : '';
    $erros = [];
    $decimal = valorDecimal($texto('valor'));
    if ($decimal === null || financeiroCentavos($decimal) <= 0) {
        $erros['valor'] = 'Informe um valor maior que zero, com até duas casas decimais.';
    }
    $data = dataIsoParaYmd($texto('data'));
    if ($data === null || $data > $hoje) {
        $erros['data'] = 'Informe a data do recebimento, até hoje.';
    }
    if (! isset(RECEBIMENTOS_FORMAS[$texto('forma')])) {
        $erros['forma'] = 'Escolha Pix, dinheiro ou cartão.';
    }
    if (! in_array($texto('tipo'), ['sinal', 'parcial', 'integral', 'quitacao'], true)) {
        $erros['tipo'] = 'Escolha o tipo de recebimento.';
    }
    if (! preg_match('/^[a-f0-9]{32}$/D', $texto('chave'))) {
        $erros['_geral'] = 'Recarregue a página antes de registrar o recebimento.';
    }
    if (mb_strlen($texto('observacoes')) > 1000) {
        $erros['observacoes'] = 'Use até 1.000 caracteres.';
    }
    return [[
        'centavos' => $decimal === null ? 0 : financeiroCentavos($decimal),
        'data' => $data, 'forma' => $texto('forma'), 'tipo' => $texto('tipo'),
        'chave' => $texto('chave'), 'observacoes' => $texto('observacoes'),
    ], $erros];
}

function recebimentoRotulo(string $tipo): string
{
    return ['sinal' => 'Sinal', 'parcial' => 'Pagamento parcial', 'integral' => 'Pagamento integral', 'quitacao' => 'Quitação', 'saldo' => 'Saldo a receber'][$tipo] ?? $tipo;
}
