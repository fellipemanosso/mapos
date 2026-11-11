<?php
$valor = static fn ($campo, $padrao = '') => is_string($valores[$campo] ?? null) ? $valores[$campo] : $padrao;
$campo = static fn ($nome, $label, $extras = []) => $extras + ['name' => $nome, 'label' => $label, 'value' => $valor($nome), 'error' => $erros[$nome] ?? null];
$caixa = 'grid gap-4 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 sm:p-6';
?>
<div class="flex max-w-4xl flex-col gap-4 pt-2 pb-8">
    <h1 class="font-display text-heading-xl text-text">Nova despesa</h1>
    <?php if (isset($erros['_geral'])) { ?><?= component('alert', ['variant' => 'danger', 'message' => $erros['_geral']]) ?><?php } ?>
    <form method="post" action="<?= e(site_url('despesas/formulario')) ?>" novalidate class="flex flex-col gap-4" <?= js_module('despesas/formulario') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="chave" value="<?= e($chave) ?>">
        <section class="<?= e($caixa) ?>">
            <?= component('input', $campo('descricao', 'Descrição da despesa', ['required' => true, 'attrs' => ['maxlength' => 255], 'placeholder' => 'Ex.: compra de insumos'])) ?>
            <?= component('select', ['name' => 'categoria', 'label' => 'Categoria', 'options' => DESPESAS_CATEGORIAS, 'selected' => $valor('categoria', 'insumos'), 'required' => true, 'error' => $erros['categoria'] ?? null]) ?>
            <?= component('input', $campo('cliente_fornecedor', 'Fornecedor', ['required' => true, 'attrs' => ['maxlength' => 255], 'placeholder' => 'Ex.: Meta, loja ou posto'])) ?>
            <?= component('select', ['name' => 'modo', 'label' => 'Como registrar', 'options' => DESPESAS_MODOS, 'selected' => $valor('modo', 'avista'), 'required' => true, 'error' => $erros['modo'] ?? null]) ?>
            <?= component('input', $campo('valor', 'Valor total da compra (R$)', ['required' => true, 'help' => 'Na compra parcelada, informe o total já com juros, se houver. Na recorrência, o valor de cada ocorrência.', 'attrs' => ['inputmode' => 'decimal', 'data-despesa-valor' => true]])) ?>
            <?= component('input', $campo('desconto', 'Desconto (R$)', ['help' => 'Opcional. Na recorrência, aplica-se a cada ocorrência.', 'attrs' => ['inputmode' => 'decimal']])) ?>
            <?= component('input', $campo('data_vencimento', 'Primeiro vencimento', ['type' => 'date', 'required' => true, 'value' => $valor('data_vencimento', date('Y-m-d'))])) ?>
            <?= component('select', ['name' => 'forma_pgto', 'label' => 'Forma de pagamento', 'options' => array_combine(FINANCEIRO_FORMAS_PAGAMENTO, FINANCEIRO_FORMAS_PAGAMENTO), 'placeholder' => 'A definir', 'selected' => $valor('forma_pgto'), 'error' => $erros['forma_pgto'] ?? null]) ?>
        </section>
        <section data-despesa-parcelada class="<?= e($caixa) ?>">
            <h2 class="text-heading-sm text-text sm:col-span-2">Compra parcelada</h2>
            <?= component('input', $campo('parcelas', 'Número de parcelas mensais', ['type' => 'number', 'value' => $valor('parcelas', '3'), 'attrs' => ['min' => 2, 'max' => 60, 'step' => 1]])) ?>
            <?= component('input', $campo('entrada', 'Entrada já paga (R$)', ['help' => 'Opcional. É descontada do total antes de dividir as parcelas.', 'attrs' => ['inputmode' => 'decimal']])) ?>
            <?= component('input', $campo('data_entrada', 'Data do pagamento da entrada', ['type' => 'date', 'value' => $valor('data_entrada', date('Y-m-d')), 'attrs' => ['max' => date('Y-m-d')]])) ?>
        </section>
        <section data-despesa-recorrente class="<?= e($caixa) ?>">
            <h2 class="text-heading-sm text-text sm:col-span-2">Programar recorrência</h2>
            <?= component('select', ['name' => 'frequencia', 'label' => 'Frequência', 'options' => DESPESAS_FREQUENCIAS, 'selected' => $valor('frequencia', 'dia'), 'error' => $erros['frequencia'] ?? null]) ?>
            <?= component('input', $campo('ocorrencias', 'Quantidade de ocorrências', ['type' => 'number', 'value' => $valor('ocorrencias', '30'), 'help' => 'Ex.: 30 dias de divulgação na Meta. Não renova automaticamente.', 'attrs' => ['min' => 1, 'max' => 366, 'step' => 1]])) ?>
        </section>
        <section data-despesa-avista class="<?= e($caixa) ?>">
            <?= component('checkbox', ['name' => 'baixado', 'label' => 'Esta despesa já foi paga', 'value' => '1', 'checked' => $valor('baixado') === '1', 'error' => $erros['baixado'] ?? null]) ?>
            <?= component('input', $campo('data_pagamento', 'Data do pagamento', ['type' => 'date', 'value' => $valor('data_pagamento', date('Y-m-d')), 'attrs' => ['max' => date('Y-m-d')]])) ?>
        </section>
        <?php if (isset($erros['baixado'])) { ?><?= component('alert', ['variant' => 'danger', 'message' => $erros['baixado']]) ?><?php } ?>
        <section class="rounded-xl border border-border bg-surface p-4">
            <?= component('textarea', $campo('observacoes', 'Observações', ['rows' => 2])) ?>
            <p data-despesa-resumo class="mt-4 text-label-md text-text" aria-live="polite">Confira os valores antes de salvar.</p>
        </section>
        <div class="flex justify-end gap-2">
            <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('despesas')]) ?>
            <?= component('button', ['label' => 'Registrar despesa', 'type' => 'submit']) ?>
        </div>
    </form>
</div>
