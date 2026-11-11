<?php

const DESPESAS_CATEGORIAS = ['meta' => 'Divulgação na Meta', 'divulgacao' => 'Outras divulgações', 'insumos' => 'Insumos e materiais', 'combustivel' => 'Combustível', 'equipamentos' => 'Equipamentos', 'transporte' => 'Transporte e estacionamento', 'sistemas' => 'Sistemas e assinaturas', 'impostos' => 'Impostos e taxas', 'outros' => 'Outras despesas'];
const DESPESAS_MODOS = ['avista' => 'À vista / lançamento único', 'parcelada' => 'Compra parcelada', 'recorrente' => 'Despesa recorrente'];
const DESPESAS_FREQUENCIAS = ['dia' => 'Diária', 'semana' => 'Semanal', 'mes' => 'Mensal'];

/** Compra divide o total; recorrência repete o valor por ocorrência. */
function despesaDoFormulario(array $post, string $hoje, bool $controleBaixa = false): array
{
    $texto = static fn ($campo) => is_string($post[$campo] ?? null) ? trim($post[$campo]) : '';
    [$base, $erros] = financeiroLancamentoDoFormulario(array_replace($post, ['tipo' => 'despesa']), ['hoje' => $hoje, 'controle_baixa' => $controleBaixa, 'pagamento_atual' => null]);
    $modo = $texto('modo');
    $categoria = $texto('categoria');
    if (! isset(DESPESAS_MODOS[$modo])) {
        $erros['modo'] = 'Escolha como registrar a despesa.';
    }
    if (! isset(DESPESAS_CATEGORIAS[$categoria])) {
        $erros['categoria'] = 'Escolha a categoria da despesa.';
    }
    if (! preg_match('/^[a-f0-9]{32}$/D', $texto('chave'))) {
        $erros['_geral'] = 'Recarregue a página antes de lançar a despesa.';
    }
    if ($base['data_pagamento'] !== null && $base['data_pagamento'] > $hoje) {
        $erros['data_pagamento'] = 'Um pagamento realizado não pode ter data futura.';
    }
    $quantidade = 1;
    $entrada = 0;
    $dataEntrada = null;
    $frequencia = null;
    if ($modo === 'parcelada') {
        $n = $texto('parcelas');
        if (! ctype_digit($n) || (int) $n < 2 || (int) $n > 60) {
            $erros['parcelas'] = 'Informe de 2 a 60 parcelas.';
        } else {
            $quantidade = (int) $n;
        }
        $decimal = $texto('entrada') === '' ? '0.00' : valorDecimal($texto('entrada'));
        if ($decimal === null) {
            $erros['entrada'] = 'Informe a entrada em reais, ou deixe em branco.';
        } else {
            $entrada = financeiroCentavos($decimal);
            if ($entrada >= financeiroCentavos($base['valor_desconto'])) {
                $erros['entrada'] = 'A entrada deve ser menor que o total da compra.';
            }
        }
        if ($entrada > 0) {
            $dataEntrada = dataIsoParaYmd($texto('data_entrada'));
            if ($dataEntrada === null || $dataEntrada > $hoje || ($controleBaixa && $dataEntrada !== $hoje)) {
                $erros['data_entrada'] = 'Informe quando a entrada foi paga, até hoje.';
            }
        }
        if (financeiroCentavos($base['valor_desconto']) - $entrada < $quantidade) {
            $erros['valor'] = 'Cada parcela deve ter pelo menos R$ 0,01.';
        }
    } elseif ($modo === 'recorrente') {
        $n = $texto('ocorrencias');
        if (! ctype_digit($n) || (int) $n < 1 || (int) $n > 366) {
            $erros['ocorrencias'] = 'Informe de 1 a 366 ocorrências.';
        } else {
            $quantidade = (int) $n;
        }
        $frequencia = $texto('frequencia');
        if (! isset(DESPESAS_FREQUENCIAS[$frequencia])) {
            $erros['frequencia'] = 'Escolha frequência diária, semanal ou mensal.';
        }
    }
    if ($modo !== 'avista' && (int) $base['baixado'] === 1) {
        $erros['baixado'] = 'As parcelas e recorrências começam em aberto. Marque cada pagamento depois; a entrada tem campo próprio.';
    }
    if ($erros !== []) {
        return [[], [], $erros];
    }
    if ($modo === 'parcelada') {
        $linhas = financeiroLinhasDoParcelamento($base, ['parcelas' => $quantidade, 'entrada' => $entrada, 'data_entrada' => $dataEntrada], null);
    } elseif ($modo === 'recorrente') {
        $linhas = [];
        $inicio = new DateTimeImmutable($base['data_vencimento']);
        for ($i = 0; $i < $quantidade; $i++) {
            $sufixo = ' - Ocorrência ' . ($i + 1) . '/' . $quantidade;
            $data = $frequencia === 'mes' ? financeiroAdicionarMeses($base['data_vencimento'], $i) : $inicio->modify('+' . ($frequencia === 'dia' ? $i : $i * 7) . ' days')->format('Y-m-d');
            $linhas[] = array_replace($base, ['descricao' => mb_substr($base['descricao'], 0, 255 - mb_strlen($sufixo)) . $sufixo, 'data_vencimento' => $data, 'data_pagamento' => null, 'baixado' => 0]);
        }
    } else {
        $linhas = [$base];
    }
    foreach ($linhas as $linha) {
        if (dataIsoParaYmd($linha['data_vencimento']) === null || $linha['data_vencimento'] < '1000-01-01') {
            return [[], [], ['data_vencimento' => 'O período programado ultrapassa o intervalo de datas aceito.']];
        }
    }
    $total = array_sum(array_map(static fn ($l) => financeiroCentavos($l['valor_desconto']), $linhas));
    return [[
        'chave' => $texto('chave'), 'descricao' => $base['descricao'], 'categoria' => $categoria,
        'modo' => $modo, 'frequencia' => $frequencia, 'quantidade' => $quantidade,
        'total_original' => financeiroDecimal($total), 'criado_em' => date('Y-m-d H:i:s'),
    ], $linhas, []];
}
