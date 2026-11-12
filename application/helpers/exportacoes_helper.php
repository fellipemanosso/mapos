<?php

/** Impede que conteúdo de uma cliente seja executado como fórmula ao abrir o CSV. */
function exportacaoCelula($valor): string
{
    $texto = is_scalar($valor) ? (string) $valor : '';
    return preg_match('/\A(?:\s*[=+@-]|[\t\r\n])/', $texto) ? "'" . $texto : $texto;
}

function exportacaoLinha($stream, array $valores): void
{
    fputcsv($stream, array_map('exportacaoCelula', $valores), ';', '"', '');
}
