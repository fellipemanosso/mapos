<?php

function deslocamentoDados(array $post): array
{
    $erros = [];
    $dados = [];
    foreach (['endereco_atendimento' => 500, 'regra_deslocamento' => 500] as $campo => $max) {
        $dados[$campo] = is_string($post[$campo] ?? null) ? trim($post[$campo]) : '';
        if (! is_string($post[$campo] ?? null) || mb_strlen($dados[$campo]) > $max) {
            $erros[$campo] = 'Informe até 500 caracteres.';
        }
    }
    $distancia = valorDecimal($post['distancia_km'] ?? '0', 6);
    $minutos = $post['deslocamento_min'] ?? '';
    if ($distancia === null) {
        $erros['distancia_km'] = 'Informe a distância em quilômetros, até duas casas decimais.';
    }
    if (! is_string($minutos) || ! ctype_digit($minutos) || (int) $minutos > 720) {
        $erros['deslocamento_min'] = 'Use de 0 a 720 minutos.';
    }
    $dados['distancia_km'] = $distancia ?? '0.00';
    $dados['deslocamento_min'] = is_scalar($minutos) ? (int) $minutos : 0;
    return [$dados, $erros];
}
