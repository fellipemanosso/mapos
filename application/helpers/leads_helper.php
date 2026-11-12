<?php

const LEADS_ETAPAS = ['novo' => 'Novo contato', 'negociacao' => 'Em negociação', 'enviado' => 'Orçamento enviado', 'sinal' => 'Aguardando sinal', 'convertido' => 'Convertido', 'perdido' => 'Perdido'];
const LEADS_ORIGENS = ['whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'indicacao' => 'Indicação', 'outro' => 'Outro'];

function leadTelefone($valor): ?string
{
    if (! is_string($valor) || preg_match('/[^0-9+() .-]/', $valor)) {
        return null;
    }
    $numero = preg_replace('/\D/', '', $valor);
    if (strlen($numero) === 10 || strlen($numero) === 11) {
        $numero = '55' . $numero;
    }
    return preg_match('/\A55[1-9][0-9][2-9][0-9]{7,8}\z/', $numero) ? $numero : null;
}

function leadStatus(string $etapa): array
{
    return ['label' => LEADS_ETAPAS[$etapa] ?? 'Etapa desconhecida', 'variant' => match ($etapa) {
        'convertido' => 'success', 'sinal' => 'warning', 'enviado' => 'info', 'negociacao' => 'progress', default => 'neutral',
    }];
}

function leadFormulario(array $post, array $catalogo): array
{
    $dados = [];
    $erros = [];
    foreach (['nome' => 150, 'bebe' => 150, 'interesse' => 255, 'observacoes' => 5000, 'condicoes' => 3000] as $campo => $limite) {
        $dados[$campo] = is_string($post[$campo] ?? '') ? trim($post[$campo] ?? '') : '';
        if (mb_strlen($dados[$campo]) > $limite || (isset($post[$campo]) && ! is_string($post[$campo]))) {
            $erros[$campo] = 'Informe um texto de até ' . $limite . ' caracteres.';
        }
    }
    foreach (['nome', 'interesse'] as $campo) {
        if ($dados[$campo] === '') {
            $erros[$campo] = 'Preencha este campo.';
        }
    }
    $dados['telefone'] = leadTelefone($post['telefone'] ?? null) ?? '';
    if ($dados['telefone'] === '') {
        $erros['telefone'] = 'Informe um telefone brasileiro com DDD.';
    }
    $dados['origem'] = is_string($post['origem'] ?? null) && isset(LEADS_ORIGENS[$post['origem']]) ? $post['origem'] : '';
    if ($dados['origem'] === '') {
        $erros['origem'] = 'Escolha a origem do contato.';
    }
    foreach (['validade', 'retorno'] as $campo) {
        $valor = $post[$campo] ?? '';
        $data = is_string($valor) ? DateTimeImmutable::createFromFormat('!Y-m-d', $valor) : false;
        $dados[$campo] = $valor === '' ? null : ($data && $data->format('Y-m-d') === $valor ? $valor : null);
        if ($valor !== '' && $dados[$campo] === null) {
            $erros[$campo] = 'Informe uma data válida.';
        }
    }
    $itens = [];
    $total = 0;
    $entradas = $post['servicos'] ?? [];
    if (! is_array($entradas) || count($entradas) > 500) {
        $erros['_geral'] = 'Composição do orçamento inválida.';
        $entradas = [];
    }
    $catalogo = array_column($catalogo, null, 'idServicos');
    foreach ($entradas as $id => $entrada) {
        if (! is_array($entrada)) {
            $erros['_geral'] = 'Confira os serviços do orçamento.';
            continue;
        }
        $qtd = $entrada['quantidade'] ?? '0';
        if ($qtd === '0' || $qtd === '') {
            continue;
        }
        $preco = valorDecimal($entrada['preco'] ?? null);
        if (! is_string($qtd) || ! ctype_digit($qtd) || (int) $qtd < 1 || (int) $qtd > 100 || ! isset($catalogo[$id]) || $preco === null || financeiroCentavos($preco) <= 0) {
            $erros['_geral'] = 'Cada item deve ter de 1 a 100 unidades e valor maior que zero.';
            continue;
        }
        $itens[] = ['servicos_id' => (int) $id, 'nome' => $catalogo[$id]->nome, 'quantidade' => (int) $qtd, 'preco' => $preco];
        $total += (int) $qtd * financeiroCentavos($preco);
    }
    if ($total > 9999999999 || count($itens) > 100) {
        $erros['_geral'] = 'Use até 100 itens e mantenha o total até R$ 99.999.999,99.';
    }
    $sinal = valorDecimal($post['sinal'] ?? '0');
    if ($sinal === null || financeiroCentavos($sinal) > $total) {
        $erros['sinal'] = 'O sinal deve estar entre zero e o total do orçamento.';
    }
    $dados += ['itens_json' => json_encode($itens, JSON_UNESCAPED_UNICODE), 'total' => financeiroDecimal($total), 'sinal' => $sinal ?? '0.00'];
    return [$dados, $erros];
}
