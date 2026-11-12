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
            <?= component('select', ['name' => 'insumo_id', 'label' => 'Insumo', 'options' => $opcoes, 'placeholder' => 'Escolha', 'required' => true, 'selected' => is_scalar($valores['insumo_id'] ?? null) ? $valores['insumo_id'] : null]) ?>
            <?= component('input', ['name' => 'quantidade', 'label' => 'Quantidade por serviço', 'required' => true, 'value' => is_string($valores['quantidade'] ?? null) ? $valores['quantidade'] : '', 'error' => $erros['quantidade'] ?? null, 'attrs' => ['inputmode' => 'decimal'], 'help' => 'Até três casas decimais. Adicionar um insumo não salva os ajustes da composição abaixo.']) ?>
            <div class="sm:col-span-2"><?= component('button', ['label' => 'Adicionar insumo', 'variant' => 'outline', 'type' => 'submit']) ?></div>
        </form>
    </section>
    <section class="rounded-xl border border-border bg-surface p-4"><h2 class="text-heading-sm text-text">Composição do serviço</h2>
        <?php if ($itens) { ?>
        <p class="mt-2 text-caption text-muted">Ajuste as quantidades e marque os materiais a remover. Salve todas as alterações de uma vez; nenhuma delas é aplicada antes de salvar.</p>
        <form method="post" action="<?= e(site_url('custos/padrao/' . $servico->idServicos)) ?>" novalidate <?= js_module('custos/composicao') ?> data-pendente="<?= e(($valores['acao'] ?? '') === 'composicao' ? 'true' : 'false') ?>">
            <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>"><input type="hidden" name="acao" value="composicao">
        <?php foreach ($itens as $item) { ?>
        <div class="mt-4 grid items-end gap-3 border-t border-border pt-3 sm:grid-cols-3">
            <p class="text-label-md text-text"><?= e($item->nome . ' · ' . $item->unidade) ?></p>
            <?= component('input', ['name' => 'quantidades[' . $item->insumo_id . ']', 'id' => 'quantidade-' . $item->insumo_id, 'label' => 'Quantidade · ' . $item->nome, 'value' => is_string($valores['quantidades'][$item->insumo_id] ?? null) ? $valores['quantidades'][$item->insumo_id] : number_format((float) $item->quantidade, 3, ',', ''), 'error' => $erros[$item->insumo_id] ?? null, 'required' => true, 'attrs' => ['inputmode' => 'decimal']]) ?>
            <?= component('checkbox', ['name' => 'remover[' . $item->insumo_id . ']', 'label' => 'Remover ' . $item->nome, 'value' => '1', 'checked' => isset($valores['remover'][$item->insumo_id])]) ?>
        </div>
        <?php } ?>
            <p class="mt-4 text-caption text-muted" data-composicao-status role="status">Sem alterações pendentes.</p>
            <div class="mt-3"><?= component('button', ['label' => 'Salvar alterações da composição', 'type' => 'submit']) ?></div>
        </form>
        <?php } ?>
        <?php if (! $itens) { ?><p class="mt-3 text-body-md text-muted">Adicione os materiais normalmente usados neste serviço.</p><?php } ?>
    </section>
</div>
