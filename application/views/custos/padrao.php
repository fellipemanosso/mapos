<?php
$opcoes = [];
foreach ($insumos as $insumo) {
    $opcoes[$insumo->id] = $insumo->nome . ' · ' . $insumo->unidade;
}
?>
<div class="flex max-w-5xl flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap justify-between gap-3"><h1 class="font-display text-heading-xl text-text"><?= e('Insumos padrão · ' . $servico->nome) ?></h1><?= component('button', ['label' => 'Voltar aos serviços', 'variant' => 'outline', 'href' => site_url('servicos')]) ?></header>
    <p class="text-body-md text-muted">Defina o consumo para uma unidade deste serviço. O estoque só será baixado ao aplicar o padrão em Custos e resultado do atendimento. Alterações não modificam consumos anteriores. Estes itens não são produtos vendidos e não aparecem para o cliente.</p>
    <?php if ($erro) { ?><?= component('alert', ['variant' => 'danger', 'message' => $erro]) ?><?php } ?>
    <section class="rounded-xl border border-border bg-surface p-4">
        <h2 class="text-heading-sm text-text">Adicionar ou ajustar insumo</h2>
        <form method="post" action="<?= e(site_url('custos/padrao/' . $servico->idServicos)) ?>" novalidate class="mt-4 grid gap-4 sm:grid-cols-2" <?= js_module('formulario/padrao') ?>>
            <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
            <?= component('select', ['name' => 'insumo_id', 'label' => 'Insumo', 'options' => $opcoes, 'placeholder' => 'Escolha', 'required' => true]) ?>
            <?= component('input', ['name' => 'quantidade', 'label' => 'Quantidade por serviço', 'required' => true, 'help' => 'Até três casas decimais. Zero remove este insumo do padrão.']) ?>
            <div class="sm:col-span-2"><?= component('button', ['label' => 'Salvar composição', 'type' => 'submit']) ?></div>
        </form>
    </section>
    <?= component('data-table', ['caption' => 'Composição do serviço', 'columns' => [
        ['key' => 'nome', 'label' => 'Insumo'], ['key' => 'unidade', 'label' => 'Unidade'],
        ['label' => 'Quantidade', 'render' => static fn ($item) => number_format((float) $item->quantidade, 3, ',', '')],
    ], 'rows' => $itens, 'empty' => component('empty-state', ['title' => 'Sem insumos padrão', 'message' => 'Adicione os materiais normalmente usados neste serviço.'])]) ?>
</div>
