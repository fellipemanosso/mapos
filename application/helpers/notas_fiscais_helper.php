<?php

function notaFiscalDados(array $post, string $hoje): array
{
    $numero = is_string($post['numero'] ?? null) ? trim($post['numero']) : '';
    $data = is_string($post['data_emissao'] ?? null) ? trim($post['data_emissao']) : '';
    $erros = [];
    if (mb_strlen($numero) > 60 || (isset($post['numero']) && ! is_string($post['numero']))) {
        $erros['numero'] = 'O número da nota tem até 60 caracteres.';
    }
    $emissao = $data === '' ? null : dataIsoParaYmd($data);
    if (($data !== '' && ($emissao === null || $emissao > $hoje)) || (isset($post['data_emissao']) && ! is_string($post['data_emissao']))) {
        $erros['data_emissao'] = 'Informe uma data de emissão válida, até hoje, ou deixe em branco.';
    }
    return [['numero' => $numero === '' ? null : $numero, 'data_emissao' => $emissao], $erros];
}

function notaFiscalStatus(bool $solicitada, array $notas): array
{
    if (array_filter($notas, static fn ($nota) => (int) $nota->ativo === 1) !== []) {
        return ['label' => 'NF anexada', 'variant' => 'success'];
    }
    return $solicitada ? ['label' => 'NF solicitada', 'variant' => 'warning'] : ['label' => 'NF não solicitada', 'variant' => 'neutral'];
}
