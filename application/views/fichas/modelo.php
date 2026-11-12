<?php $valor = static fn ($campo, $padrao = '') => is_scalar($valores[$campo] ?? null) ? (string) $valores[$campo] : $padrao; ?>
<div class="flex max-w-5xl flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap items-start justify-between gap-3"><h1 class="font-display text-heading-xl text-text">Modelos de fichas e termos</h1><?= component('button', ['label' => 'Retornos', 'variant' => 'outline', 'href' => site_url('fichas')]) ?></header>
    <nav aria-label="Modelos disponíveis" class="flex flex-wrap gap-2"><?php foreach ($modelos as $item) { ?><?= component('button', ['label' => $item->nome, 'variant' => 'outline', 'href' => site_url('fichas/modelo/' . $item->id)]) ?><?php } ?><?= component('button', ['label' => 'Novo modelo', 'variant' => 'ghost', 'href' => site_url('fichas/modelo')]) ?></nav>
    <p class="text-body-md text-muted">Ajuste as perguntas e o texto do termo antes de iniciar novas fichas. Alterações não modificam documentos já registrados.</p>
    <?php if (isset($erros['_geral'])) { ?><?= component('alert', ['variant' => 'danger', 'message' => $erros['_geral']]) ?><?php } ?>
    <form method="post" action="<?= e(site_url('fichas/modelo' . ($modelo ? '/' . $modelo->id : ''))) ?>" class="grid gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6" novalidate <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>"><input type="hidden" name="versao" value="<?= e($valor('versao', '0')) ?>">
        <?= component('input', ['name' => 'nome', 'label' => 'Nome do modelo', 'value' => $valor('nome'), 'required' => true, 'error' => $erros['nome'] ?? null, 'attrs' => ['maxlength' => 150]]) ?>
        <?= component('textarea', ['name' => 'campos', 'label' => 'Perguntas da ficha — uma por linha', 'value' => $valor('campos'), 'rows' => 8, 'required' => true, 'error' => $erros['campos'] ?? null, 'help' => 'Até 20 perguntas, com até 150 caracteres cada. As respostas são campos de texto livre.']) ?>
        <?= component('textarea', ['name' => 'termo', 'label' => 'Texto do termo para assinatura (opcional)', 'value' => $valor('termo'), 'rows' => 10, 'error' => $erros['termo'] ?? null, 'attrs' => ['maxlength' => 12000], 'help' => 'Cole o texto aprovado para uso na sua atividade. A identificação da cliente, do bebê e do serviço será incluída na impressão.']) ?>
        <div><?= component('button', ['label' => 'Salvar modelo', 'type' => 'submit']) ?></div>
    </form>
</div>
