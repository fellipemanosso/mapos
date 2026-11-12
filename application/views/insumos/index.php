<?php $colunas = [
    ['label' => 'Insumo', 'render' => fn ($i) => component('link', ['label' => $i->nome, 'href' => site_url('insumos/visualizar/' . $i->id)])],
    ['key' => 'unidade', 'label' => 'Unidade'], ['label' => 'Saldo válido', 'render' => fn ($i) => number_format((float) $i->disponivel, 3, ',', '')],
    ['label' => 'Mínimo', 'render' => fn ($i) => number_format((float) $i->minimo, 3, ',', '')],
    ['label' => 'Reposição', 'render' => fn ($i) => component('pill-status', ['label' => (float) $i->disponivel <= (float) $i->minimo ? 'Repor estoque' : 'Disponível', 'variant' => (float) $i->disponivel <= (float) $i->minimo ? 'warning' : 'success'])],
]; ?>
<div class="flex flex-col gap-4 pt-2 pb-8"><header><h1 class="font-display text-heading-xl text-text">Estoque de insumos</h1><p class="mt-2 text-body-md text-muted">Lotes, validade e consumo interno. O saldo disponível exclui lotes vencidos.</p></header>
<form method="get" action="<?= e(site_url('insumos')) ?>" class="flex flex-wrap items-end gap-3"><?= component('input', ['name' => 'pesquisa', 'label' => 'Buscar insumo', 'value' => $pesquisa]) ?><?= component('button', ['label' => 'Buscar', 'type' => 'submit', 'variant' => 'outline']) ?></form>
<?= component('data-table', ['columns' => $colunas, 'rows' => $itens, 'caption' => 'Insumos e necessidade de reposição', 'empty' => $pesquisa ? 'Nenhum insumo corresponde à busca.' : 'Cadastre o primeiro insumo para registrar as entradas.']) ?><?= component('pagination', $paginacao) ?></div>
