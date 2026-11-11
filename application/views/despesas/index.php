<div class="flex flex-col gap-4 pt-2 pb-8">
    <header><h1 class="font-display text-heading-xl text-text">Despesas</h1><p class="text-body-md text-muted">Compras à vista, parceladas e despesas recorrentes. Todos os lançamentos também aparecem no financeiro.</p></header>
    <form method="get" action="<?= e(site_url('despesas')) ?>" class="flex flex-wrap items-end gap-3">
        <?= component('input', ['name' => 'pesquisa', 'label' => 'Buscar despesa', 'type' => 'search', 'value' => $filtros['pesquisa'] ?? '', 'attrs' => ['maxlength' => 100]]) ?>
        <?= component('select', ['name' => 'categoria', 'label' => 'Categoria', 'options' => DESPESAS_CATEGORIAS, 'placeholder' => 'Todas', 'selected' => $filtros['categoria'] ?? null]) ?>
        <?= component('button', ['label' => 'Filtrar', 'type' => 'submit', 'variant' => 'outline']) ?>
        <?= component('button', ['label' => 'Ver financeiro', 'variant' => 'ghost', 'href' => site_url('financeiro/lancamentos') . '?tipo=despesa']) ?>
    </form>
    <?php if ($grupos === []) { ?><?= component('empty-state', ['title' => 'Nenhuma despesa encontrada', 'message' => 'As compras e despesas cadastradas nesta tela aparecem aqui.', 'icon' => 'banknote']) ?><?php } ?>
    <?php foreach ($grupos as $grupo) { ?>
        <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-surface p-4">
            <div><h2 class="text-heading-sm text-text"><?= e($grupo->descricao) ?></h2>
                <p class="mt-1 text-caption text-muted"><?= e(DESPESAS_CATEGORIAS[$grupo->categoria] . ' · ' . DESPESAS_MODOS[$grupo->modo]) ?></p>
                <p class="mt-2 text-body-md text-text"><?= e('Total: ' . dinheiro($grupo->resumo['total'] / 100) . ' · Pago: ' . dinheiro($grupo->resumo['pago'] / 100) . ' · A pagar: ' . dinheiro($grupo->resumo['pendente'] / 100)) ?></p>
            </div>
            <?= component('button', ['label' => 'Ver despesa', 'variant' => 'outline', 'href' => site_url('despesas/visualizar/' . (int) $grupo->id)]) ?>
        </article>
    <?php } ?>
    <?= component('pagination', $paginacao) ?>
</div>
