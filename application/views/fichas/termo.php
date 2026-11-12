<!DOCTYPE html>
<html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e('Termo · Ficha #' . $ficha->id) ?></title><link rel="stylesheet" href="<?= e(base_url('assets/dist/imprimir.css')) ?>"></head>
<body><main class="doc-folha"><h1 class="doc-titulo"><?= e('Termo · ' . $ficha->modelo_nome) ?></h1>
    <section class="py-4"><p><?= e('Pessoa atendida: ' . $ficha->paciente_nome) ?></p><p><?= e('Cliente / responsável: ' . $ficha->responsavel_nome) ?></p><p><?= e('Serviço: ' . $ficha->servico_nome . ' · Atendimento #' . $ficha->os_id . ' · Ficha #' . $ficha->id) ?></p></section>
    <div class="whitespace-pre-wrap"><?= e($ficha->termo) ?></div>
    <section class="mt-8"><p>Local e data: ____________________________________________________</p><p class="mt-8">Nome de quem assina: _____________________________________________</p><p class="mt-8">Documento: _____________________________________________________</p><p class="mt-8">Assinatura: _____________________________________________________</p><p class="mt-8">Profissional responsável: ________________________________________</p></section>
    <p class="doc-so-tela mt-8">Use Ctrl+P para imprimir ou salvar em PDF. Depois da assinatura, anexe o documento à ficha.</p>
</main></body></html>
