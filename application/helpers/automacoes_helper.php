<?php

function automacoesProvedores(): array
{
    return ['openai' => ['nome' => 'OpenAI', 'chave' => 'OPENAI_API_KEY'],
        'gemini' => ['nome' => 'Google Gemini', 'chave' => 'GEMINI_API_KEY'],
        'deepseek' => ['nome' => 'DeepSeek', 'chave' => 'DEEPSEEK_API_KEY'],
        'claude' => ['nome' => 'Claude (Anthropic)', 'chave' => 'ANTHROPIC_API_KEY']];
}

function automacoesChaveIA(string $provedor): string
{
    $variavel = automacoesProvedores()[$provedor]['chave'] ?? null;
    $chave = $variavel ? ($_ENV[$variavel] ?? '') : '';
    return is_string($chave) && ! preg_match('/[\x00-\x20\x7f]/', $chave) ? $chave : '';
}

function automacoesPadrao(): array
{
    return ['nome' => '', 'apresentacao' => '', 'horarios' => '', 'area' => '', 'politicas' => '',
        'automatico' => false, 'ia' => false, 'lembretes' => false, 'cobrancas' => false, 'retornos' => false,
        'inicio_envio' => '08:00', 'fim_envio' => '19:00', 'limite_diario' => 100,
        'provedor_ia' => 'openai', 'modelo_ia' => '', 'boas_vindas' => 'Olá! Sou a assistente virtual de {profissional}. Qual serviço você procura? Para conversar com a profissional, escreva HUMANO. Para autorizar respostas com IA, escreva AUTORIZO IA. Para parar mensagens, escreva SAIR.',
        'confirmacao' => 'Olá! Você tem um atendimento com {profissional} em {data} às {hora}. Responda CONFIRMAR ou peça para falar com a profissional se precisar alterar.',
        'lembrete' => 'Lembrete: seu atendimento com {profissional} será em {data} às {hora}. Para alterações, responda HUMANO.',
        'cobranca' => 'Olá! Há um saldo pendente no atendimento #{atendimento}: R$ {saldo}. Se já pagou, fale com a profissional para conferir.',
        'retorno' => 'Olá! Está na época de conversar com {profissional} sobre seu retorno. Responda HUMANO para combinar.'];
}

function automacoesDados(array $post): array
{
    $dados = automacoesPadrao();
    $erros = [];
    foreach ($dados as $campo => $padrao) {
        if (is_bool($padrao)) {
            $dados[$campo] = ($post[$campo] ?? '') === '1';
        } elseif ($campo === 'limite_diario') {
            $valor = $post[$campo] ?? '';
            if (! is_string($valor) || ! ctype_digit($valor) || (int) $valor < 1 || (int) $valor > 500) {
                $erros[$campo] = 'Informe de 1 a 500 mensagens por dia.';
            } else {
                $dados[$campo] = (int) $valor;
            }
        } else {
            $valor = $post[$campo] ?? $padrao;
            if (! is_string($valor) || mb_strlen($valor) > 3000) {
                $erros[$campo] = 'Use até 3.000 caracteres.';
                continue;
            }
            $dados[$campo] = trim($valor);
        }
    }
    if (! isset(automacoesProvedores()[$dados['provedor_ia']])) {
        $erros['provedor_ia'] = 'Selecione um provedor válido.';
    }
    foreach (['inicio_envio', 'fim_envio'] as $campo) {
        if (! preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/', $dados[$campo])) {
            $erros[$campo] = 'Informe uma hora válida.';
        }
    }
    if ($dados['fim_envio'] <= $dados['inicio_envio']) {
        $erros['fim_envio'] = 'O fim deve ser depois do início.';
    }
    if (($dados['automatico'] || $dados['lembretes'] || $dados['cobrancas'] || $dados['retornos']) && $dados['nome'] === '') {
        $erros['nome'] = 'Informe o nome da profissional antes de ativar os envios.';
    }
    if ($dados['ia'] && ! preg_match('/\A[a-zA-Z0-9._:-]{1,100}\z/', $dados['modelo_ia'])) {
        $erros['modelo_ia'] = 'Informe um modelo disponível na sua conta do provedor selecionado.';
    }
    foreach (['boas_vindas', 'confirmacao', 'lembrete', 'cobranca', 'retorno'] as $campo) {
        if ($dados[$campo] === '') {
            $erros[$campo] = 'Informe o texto da mensagem.';
        }
    }
    return [$dados, $erros];
}

function whatsappTelefone($valor): ?string
{
    if (! is_string($valor) || preg_match('/[a-zA-Z@]/', $valor) || (str_starts_with(trim($valor), '+') && ! str_starts_with(trim($valor), '+55'))) {
        return null;
    }
    $numero = preg_replace('/\D/', '', $valor);
    if (strlen($numero) === 10 || strlen($numero) === 11) {
        $numero = '55' . $numero;
    }
    return preg_match('/\A55[1-9][0-9][2-9][0-9]{7,8}\z/', $numero) ? $numero : null;
}

/** Somente mensagens individuais, nunca grupos, status ou identificadores LID. */
function whatsappEvento(array $evento, string $instancia): ?array
{
    if (($evento['instance'] ?? null) !== $instancia || ! is_string($evento['event'] ?? null) || strtolower(str_replace('_', '.', $evento['event'])) !== 'messages.upsert') {
        return null;
    }
    $d = $evento['data'] ?? null;
    if (! is_array($d) || ! is_array($d['key'] ?? null) || ! is_array($d['message'] ?? null) || ! is_bool($d['key']['fromMe'] ?? null)) {
        return null;
    }
    $jid = $d['key']['remoteJid'] ?? '';
    $id = $d['key']['id'] ?? '';
    if (! is_string($jid) || ! preg_match('/\A([0-9]+)@s\.whatsapp\.net\z/', $jid, $m) || ! is_string($id) || $id === '' || strlen($id) > 200) {
        return null;
    }
    $telefone = whatsappTelefone($m[1]);
    if (! $telefone) {
        return null;
    }
    $extendida = is_array($d['message']['extendedTextMessage'] ?? null) ? $d['message']['extendedTextMessage'] : [];
    $texto = $d['message']['conversation'] ?? $extendida['text'] ?? null;
    $midia = ! is_string($texto) || trim($texto) === '';
    $nome = is_string($d['pushName'] ?? null) ? mb_substr(trim($d['pushName']), 0, 150) : 'Contato WhatsApp';
    return ['telefone' => $telefone, 'nome' => $nome ?: 'Contato WhatsApp', 'texto' => $midia ? '[Mensagem sem texto — confira no WhatsApp]' : mb_substr($texto, 0, 4000),
        'midia' => $midia, 'propria' => $d['key']['fromMe'],
        'antiga' => ! is_numeric($d['messageTimestamp'] ?? null) || (int) $d['messageTimestamp'] < time() - 600,
        'chave' => hash('sha256', $instancia . ':' . $id), 'externa' => $id];
}

function automacoesTexto(string $modelo, array $valores): string
{
    $mapa = [];
    foreach ($valores as $campo => $valor) {
        $mapa['{' . $campo . '}'] = (string) $valor;
    }
    return mb_substr(strtr($modelo, $mapa) . "\nPara parar mensagens, responda SAIR.", 0, 4000);
}

function automacoesHumano(string $texto): bool
{
    return (bool) preg_match('/humano|atendente|profissional|dor|febre|sangr|inflam|alerg|medic|dose|sintoma|urg[eê]n|diagn[oó]st|infec|machuc|ferid|pus|receita|contraindica/iu', $texto);
}

function automacoesAnonimizar(string $texto): string
{
    $texto = preg_replace('/[\w.+-]+@[\w.-]+\.[a-z]{2,}/iu', '[email]', $texto);
    return preg_replace('/(?:\(?\+?\d[\d\s().\/-]{7,}\d)/u', '[número]', $texto);
}
