<?php
$colunas = [
    ['label' => 'Contato', 'render' => fn ($l) => component('link', ['label' => $l->nome, 'href' => site_url('leads/visualizar/' . $l->id)])],
    ['key' => 'telefone', 'label' => 'WhatsApp'],
    ['key' => 'interesse', 'label' => 'Interesse'],
    ['label' => 'Etapa', 'render' => fn ($l) => component('pill-status', leadStatus($l->etapa))],
    ['label' => 'Orçamento', 'render' => fn ($l) => dinheiro($l->total)],
    ['label' => 'Retomar contato', 'render' => fn ($l) => $l->retorno ? dataBr($l->retorno) : '—'],
];
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header><h1 class="font-display text-heading-xl text-text">Leads e orçamentos</h1><p class="mt-2 text-body-md text-muted">Acompanhe os contatos até a contratação. <?= e($total) ?> contato(s) encontrado(s).</p></header>
    <form method="get" action="<?= e(site_url('leads')) ?>" class="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-surface p-4">
        <?= component('input', ['name' => 'pesquisa', 'label' => 'Buscar contato', 'value' => $filtros['pesquisa'] ?? '', 'placeholder' => 'Nome, telefone, bebê ou serviço']) ?>
        <?= component('select', ['name' => 'etapa', 'label' => 'Etapa', 'options' => LEADS_ETAPAS, 'selected' => $filtros['etapa'] ?? '', 'placeholder' => 'Todas as etapas']) ?>
        <?= component('select', ['name' => 'retornos', 'label' => 'Retomada de contato', 'options' => ['hoje' => 'Até hoje, em aberto'], 'selected' => $filtros['retornos'] ?? '', 'placeholder' => 'Todos os contatos']) ?>
        <?= component('button', ['label' => 'Filtrar', 'type' => 'submit', 'variant' => 'outline']) ?>
        <?= component('button', ['label' => 'Limpar', 'variant' => 'ghost', 'href' => site_url('leads')]) ?>
    </form>
    <?= component('data-table', ['columns' => $colunas, 'rows' => $leads, 'caption' => 'Contatos e orçamentos', 'empty' => component('empty-state', ['title' => $filtros ? 'Nenhum contato com estes filtros' : 'Nenhum contato cadastrado', 'message' => $filtros ? 'Ajuste a busca ou limpe os filtros.' : 'Cadastre o primeiro contato para acompanhar a negociação.', 'icon' => 'file-text'])]) ?>
    <?= component('pagination', $paginacao) ?>
</div>
