<?php

function dependenteDados(array $post, string $hoje): array
{
    $texto = static fn ($campo) => is_string($post[$campo] ?? null) ? trim($post[$campo]) : '';
    $dados = ['nome' => $texto('nome'), 'nascimento' => $texto('nascimento'), 'parentesco' => $texto('parentesco')];
    $erros = [];
    if ($dados['nome'] === '' || mb_strlen($dados['nome']) > 255) {
        $erros['nome'] = 'Informe o nome completo, com até 255 caracteres.';
    }
    if ($dados['parentesco'] === '' || mb_strlen($dados['parentesco']) > 45) {
        $erros['parentesco'] = 'Informe a relação com a pessoa responsável, como filha ou filho.';
    }
    if ($dados['nascimento'] !== '') {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $dados['nascimento']);
        if (! $data || $data->format('Y-m-d') !== $dados['nascimento'] || $dados['nascimento'] > $hoje) {
            $erros['nascimento'] = 'Informe uma data válida, até hoje.';
        }
    } else {
        $dados['nascimento'] = null;
    }

    return [$dados, $erros];
}
