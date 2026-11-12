<?php

const INSUMOS_UNIDADES = ['un' => 'Unidade', 'ml' => 'Mililitro', 'g' => 'Grama', 'cm' => 'Centímetro'];

function insumoCustoEntrada($texto): string
{
    $custo = valorDecimal($texto);
    if ($custo === null) {
        throw new DomainException('Informe o custo total da entrada. Se o custo conhecido for zero, digite 0,00; não registre um custo ainda desconhecido.');
    }
    return $custo;
}

function insumoQuantidade($texto): ?int
{
    if (! is_string($texto) || ! preg_match('/\A(\d{1,6})(?:[,.](\d{1,3}))?\z/', trim($texto), $m)) {
        return null;
    }
    return (int) $m[1] * 1000 + (int) str_pad($m[2] ?? '', 3, '0');
}

function insumoDecimal(int $milesimos): string
{
    return intdiv($milesimos, 1000) . '.' . str_pad((string) ($milesimos % 1000), 3, '0', STR_PAD_LEFT);
}

function insumoDados(array $post): array
{
    $erros = [];
    $nome = is_string($post['nome'] ?? null) ? trim($post['nome']) : '';
    $unidade = is_string($post['unidade'] ?? null) ? $post['unidade'] : '';
    $minimo = insumoQuantidade($post['minimo'] ?? '0');
    if ($nome === '' || mb_strlen($nome) > 150) {
        $erros['nome'] = 'Informe um nome de até 150 caracteres.';
    }
    if (! isset(INSUMOS_UNIDADES[$unidade])) {
        $erros['unidade'] = 'Escolha a unidade de medida.';
    }
    if ($minimo === null) {
        $erros['minimo'] = 'Informe uma quantidade com até três casas decimais, sem separador de milhar.';
    }
    return [['nome' => $nome, 'unidade' => $unidade, 'minimo' => insumoDecimal($minimo ?? 0)], $erros];
}
