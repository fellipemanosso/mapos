<?php

const FICHA_TIPOS_ARQUIVO = ['foto' => 'Foto do atendimento', 'documento' => 'Documento', 'termo_assinado' => 'Termo assinado'];

function fichaTexto(array $post, string $campo, int $limite, array &$erros, bool $obrigatorio = false): string
{
    $valor = $post[$campo] ?? '';
    if (! is_string($valor)) {
        $erros[$campo] = 'Texto inválido.';
        return '';
    }
    $valor = trim($valor);
    if (mb_strlen($valor) > $limite || ($obrigatorio && $valor === '')) {
        $erros[$campo] = 'Informe um texto ' . ($obrigatorio ? 'obrigatório ' : '') . 'com até ' . $limite . ' caracteres.';
    }
    return $valor;
}

function fichaData($valor): ?string
{
    if (! is_string($valor) || ! preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $valor)) {
        return null;
    }
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return $data && $data->format('Y-m-d') === $valor ? $valor : null;
}

function fichaModeloDados(array $post): array
{
    $erros = [];
    $nome = fichaTexto($post, 'nome', 150, $erros, true);
    $texto = fichaTexto($post, 'campos', 4000, $erros, true);
    $termo = fichaTexto($post, 'termo', 12000, $erros);
    $campos = array_values(array_filter(array_map('trim', preg_split('/\R/u', $texto) ?: []), static fn ($s) => $s !== ''));
    if (count($campos) < 1 || count($campos) > 20 || count($campos) !== count(array_unique($campos))) {
        $erros['campos'] = 'Use de 1 a 20 perguntas diferentes, uma por linha.';
    }
    foreach ($campos as $campo) {
        if (mb_strlen($campo) > 150) {
            $erros['campos'] = 'Cada pergunta pode ter até 150 caracteres.';
        }
    }
    return [['nome' => $nome, 'campos_json' => json_encode($campos, JSON_UNESCAPED_UNICODE), 'termo' => $termo], $erros];
}

function fichaRespostas(array $post, array $campos): array
{
    $erros = [];
    $respostas = [];
    $entradas = $post['respostas'] ?? [];
    if (! is_array($entradas)) {
        return [[], ['_geral' => 'Respostas inválidas.']];
    }
    foreach ($campos as $id => $rotulo) {
        $respostas[$id] = fichaTexto($entradas, (string) $id, 2000, $erros);
    }
    if (! array_filter($respostas, static fn ($s) => $s !== '')) {
        $erros['_geral'] = 'Preencha pelo menos uma resposta da ficha.';
    }
    return [$respostas, $erros];
}

function fichaEvolucaoDados(array $post, string $agora): array
{
    $erros = [];
    $dados = [];
    foreach (['avaliacao', 'procedimento', 'orientacoes', 'observacoes'] as $campo) {
        $dados[$campo] = fichaTexto($post, $campo, 4000, $erros);
    }
    if (! array_filter($dados, static fn ($s) => $s !== '')) {
        $erros['_geral'] = 'Descreva ao menos uma informação do atendimento.';
    }
    $valor = $post['ocorrido_em'] ?? '';
    $data = is_string($valor) && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}\z/', $valor) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $valor) : false;
    $dados['ocorrido_em'] = $data && $data->format('Y-m-d\TH:i') === $valor ? $data->format('Y-m-d H:i:s') : '';
    if ($dados['ocorrido_em'] === '' || $dados['ocorrido_em'] > $agora) {
        $erros['ocorrido_em'] = 'Informe uma data e hora válida, até agora.';
    }
    $dados['retorno'] = fichaData($post['retorno'] ?? '');
    if (($post['retorno'] ?? '') !== '' && (! $dados['retorno'] || $dados['retorno'] < substr($dados['ocorrido_em'], 0, 10))) {
        $erros['retorno'] = 'Informe uma data de retorno igual ou posterior ao registro.';
    }
    $agenda = $post['agenda_id'] ?? '';
    $dados['agenda_id'] = is_string($agenda) && ctype_digit($agenda) && (int) $agenda > 0 ? (int) $agenda : null;
    if ($agenda !== '' && $dados['agenda_id'] === null) {
        $erros['agenda_id'] = 'Escolha um horário da lista.';
    }
    return [$dados, $erros];
}

function fichaArquivoDados(array $post, string $hoje): array
{
    $erros = [];
    $tipo = $post['tipo'] ?? '';
    if (! is_string($tipo) || ! isset(FICHA_TIPOS_ARQUIVO[$tipo])) {
        $erros['tipo'] = 'Escolha o tipo do documento.';
        $tipo = '';
    }
    $descricao = fichaTexto($post, 'descricao', 255, $erros, true);
    $signatario = fichaTexto($post, 'signatario', 150, $erros, $tipo === 'termo_assinado');
    $assinado = fichaData($post['assinado_em'] ?? '');
    if ($tipo === 'termo_assinado' && (! $assinado || $assinado > $hoje)) {
        $erros['assinado_em'] = 'Informe a data da assinatura, até hoje.';
    }
    return [['tipo' => $tipo, 'descricao' => $descricao, 'signatario' => $tipo === 'termo_assinado' ? $signatario : '', 'assinado_em' => $tipo === 'termo_assinado' ? $assinado : null], $erros];
}
