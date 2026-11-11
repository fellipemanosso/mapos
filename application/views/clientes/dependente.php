<?php
$campo = static fn ($nome, $label, $extras = []) => $extras + ['name' => $nome, 'label' => $label, 'value' => $valores[$nome] ?? null, 'error' => $erros[$nome] ?? null];
?>
<div class="flex max-w-4xl flex-col gap-4 pt-2 pb-8">
    <header>
        <h1 class="font-display text-heading-xl text-text">Bebê ou dependente</h1>
        <p class="text-caption text-muted">Responsável: <?= e($cliente->nomeCliente) ?>. O contato e as cobranças ficam no cadastro dessa pessoa.</p>
    </header>
    <?php if (isset($erros['_geral'])) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => $erros['_geral']]) ?>
    <?php } ?>
    <form method="post" action="<?= e(current_url()) ?>" novalidate class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <?= component('input', $campo('nome', 'Nome completo de quem recebe o atendimento', ['required' => true, 'attrs' => ['maxlength' => 255]])) ?>
        <?= component('input', $campo('nascimento', 'Data de nascimento', ['type' => 'date', 'attrs' => ['max' => date('Y-m-d')]])) ?>
        <?= component('input', $campo('parentesco', 'Relação com a pessoa responsável', ['required' => true, 'help' => 'Ex.: filha, filho ou tutelado.', 'attrs' => ['maxlength' => 45]])) ?>
        <div class="flex flex-wrap justify-end gap-2">
            <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('clientes/visualizar/' . (int) $cliente->idClientes)]) ?>
            <?= component('button', ['label' => 'Salvar dependente', 'type' => 'submit', 'icon' => 'save']) ?>
        </div>
    </form>
</div>
