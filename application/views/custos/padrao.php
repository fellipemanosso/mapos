<?php
$opcoes = [];
$vinculados = array_map(static fn ($item) => (int) $item->insumo_id, $itens);
foreach ($insumos as $insumo) {
    if (in_array((int) $insumo->id, $vinculados, true)) {
        continue;
    }
    $opcoes[$insumo->id] = $insumo->nome . ' · ' . $insumo->unidade;
}
?>
<div class="flex max-w-5xl flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap justify-between gap-3"><h1 class="font-display text-heading-xl text-text"><?= e('Insumos padrão · ' . $servico->nome) ?></h1><?= component('button', ['label' => 'Voltar aos serviços', 'variant' => 'outline', 'href' => site_url('servicos')]) ?></header>
    <p class="text-body-md text-muted">Defina o consumo para uma unidade deste serviço. O estoque só será baixado ao aplicar o padrão em Custos e resultado do atendimento. Alterações não modificam consumos anteriores. Estes itens não são produtos vendidos e não aparecem para o cliente.</p>
    <?php if ($erro) { ?><?= component('alert', ['variant' => 'danger', 'message' => $erro]) ?><?php } ?>
    <section class="rounded-xl border border-border bg-surface p-4">
        <h2 class="text-heading-sm text-text">Adicionar insumo</h2>
        <form method="post" action="<?= e(site_url('custos/padrao/' . $servico->idServicos)) ?>" novalidate class="mt-4 grid gap-4 sm:grid-cols-2" <?= js_module('formulario/padrao') ?>>
            <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
            <?= component('select', ['name' => 'insumo_id', 'label' => 'Insumo', 'options' => $opcoes, 'placeholder' => 'Escolha', 'required' => true]) ?>
            <?= component('input', ['name' => 'quantidade', 'label' => 'Quantidade por serviço', 'required' => true, 'attrs' => ['inputmode' => 'decimal'], 'help' => 'Até três casas decimais. Use as linhas abaixo para ajustar ou remover materiais.']) ?>
            <div class="sm:col-span-2"><?= component('button', ['label' => 'Salvar composição', 'type' => 'submit']) ?></div>
        </form>
    </section>
    <section class="rounded-xl border border-border bg-surface p-4"><h2 class="text-heading-sm text-text">Composição do serviço</h2>
        <?php foreach ($itens as $item) { ?>
        <form method="post" action="<?= e(site_url('custos/padrao/' . $servico->idServicos)) ?>" class="mt-4 grid items-end gap-3 border-t border-border pt-3 sm:grid-cols-3" novalidate <?= js_module('formulario/padrao') ?>>
            <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>"><input type="hidden" name="insumo_id" value="<?= e($item->insumo_id) ?>">
            <p class="text-label-md text-text"><?= e($item->nome . ' · ' . $item->unidade) ?></p>
            <?= component('input', ['name' => 'quantidade', 'id' => 'quantidade-' . $item->insumo_id, 'label' => 'Quantidade · ' . $item->nome, 'value' => number_format((float) $item->quantidade, 3, ',', ''), 'required' => true, 'attrs' => ['inputmode' => 'decimal']]) ?>
            <div class="flex flex-wrap gap-2"><?= component('button', ['label' => 'Salvar ajuste', 'type' => 'submit', 'variant' => 'outline']) ?><?= component('button', ['label' => 'Remover ' . $item->nome, 'type' => 'submit', 'variant' => 'ghost', 'attrs' => ['name' => 'remover', 'value' => $item->insumo_id, 'formnovalidate' => true]]) ?></div>
        </form>
        <?php } ?>
        <?php if (! $itens) { ?><p class="mt-3 text-body-md text-muted">Adicione os materiais normalmente usados neste serviço.</p><?php } ?>
    </section>
</div>
