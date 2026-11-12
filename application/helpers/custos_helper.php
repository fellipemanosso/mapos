<?php

const CUSTOS_TIPOS = ['deslocamento' => 'Deslocamento (custo real)', 'taxa' => 'Taxa de pagamento', 'produto' => 'Custo de produto vendido', 'outro' => 'Outro custo direto'];

function custosResultado(int $cobrado, int $recebido, int $insumos, int $outros): array
{
    $custo = $insumos + $outros;
    return ['cobrado' => $cobrado, 'recebido' => $recebido, 'saldo' => max(0, $cobrado - $recebido),
        'insumos' => $insumos, 'outros' => $outros, 'custo' => $custo,
        'resultado' => $cobrado - $custo, 'recebido_menos_custos' => $recebido - $custo];
}

function custoDados(array $post): array
{
    $tipo = is_string($post['tipo'] ?? null) ? $post['tipo'] : '';
    $descricao = is_string($post['descricao'] ?? null) ? trim($post['descricao']) : '';
    $valor = valorDecimal($post['valor'] ?? null);
    if (! isset(CUSTOS_TIPOS[$tipo]) || $descricao === '' || mb_strlen($descricao) > 150 || $valor === null || financeiroCentavos($valor) <= 0) {
        throw new DomainException('Informe o tipo, uma descrição de até 150 caracteres e um custo maior que zero.');
    }
    return ['tipo' => $tipo, 'descricao' => $descricao, 'valor' => $valor];
}
