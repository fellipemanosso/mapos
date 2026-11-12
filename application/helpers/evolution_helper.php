<?php

function evolutionConfiguracao(array $post): array
{
    $dados = [];
    $erros = [];
    foreach (['url', 'instancia', 'chave'] as $campo) {
        $dados[$campo] = is_string($post[$campo] ?? '') ? trim($post[$campo] ?? '') : '';
        if (isset($post[$campo]) && ! is_string($post[$campo])) {
            $erros[$campo] = 'Valor inválido.';
        }
    }
    $url = parse_url($dados['url']);
    if (! $url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || strlen($dados['url']) > 500) {
        $erros['url'] = 'Informe a URL HTTPS da Evolution, sem credenciais ou parâmetros.';
    }
    $dados['url'] = rtrim($dados['url'], '/');
    if (! preg_match('/\A[a-zA-Z0-9_-]{1,100}\z/', $dados['instancia'])) {
        $erros['instancia'] = 'Use até 100 letras, números, hífen ou sublinhado.';
    }
    if (strlen($dados['chave']) > 1000 || preg_match('/[\x00-\x20\x7f]/', $dados['chave'])) {
        $erros['chave'] = 'Chave de acesso inválida.';
    }
    return [$dados, $erros];
}

function evolutionEstado(array $resposta): array
{
    $estado = $resposta['instance']['state'] ?? $resposta['instance']['status'] ?? 'unknown';
    return match ($estado) {
        'open' => ['label' => 'Conectado', 'variant' => 'success'],
        'connecting' => ['label' => 'Conectando', 'variant' => 'progress'],
        'close', 'closed' => ['label' => 'Desconectado', 'variant' => 'warning'],
        default => ['label' => 'Estado não confirmado', 'variant' => 'neutral'],
    };
}

function evolutionQr(array $resposta): ?string
{
    $imagem = $resposta['base64'] ?? $resposta['qrcode']['base64'] ?? null;
    if (! is_string($imagem) || strlen($imagem) > 500000 || ! str_starts_with($imagem, 'data:image/png;base64,')) {
        return null;
    }
    $bytes = base64_decode(substr($imagem, 22), true);
    return $bytes !== false && str_starts_with($bytes, "\x89PNG\r\n\x1a\n") ? $imagem : null;
}
