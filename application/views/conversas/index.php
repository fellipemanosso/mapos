<?php $colunas = [
    ['key' => 'nome', 'label' => 'Contato'], ['key' => 'telefone', 'label' => 'Telefone'],
    ['label' => 'Atendimento', 'render' => fn ($c) => component('pill-status', ['label' => $c->optout ? 'Mensagens interrompidas' : ($c->modo === 'ia' ? 'IA' : 'Humano'), 'variant' => $c->optout ? 'warning' : 'neutral'])],
    ['key' => 'atualizado_em', 'label' => 'Última atividade'],
    ['label' => 'Ações', 'render' => fn ($c) => component('button', ['label' => 'Abrir conversa', 'href' => site_url('conversas/visualizar/' . $c->id), 'variant' => 'ghost'])],
]; ?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header><h1 class="font-display text-heading-xl text-text">Conversas do WhatsApp</h1><p class="mt-2 text-body-md text-muted">Acompanhe os contatos e assuma o atendimento quando precisar.</p></header>
    <form method="get" action="<?= e(site_url('conversas')) ?>" class="flex flex-wrap items-end gap-3">
        <?= component('input', ['name' => 'pesquisa', 'label' => 'Nome ou telefone', 'value' => $filtros['pesquisa'] ?? '']) ?>
        <?= component('select', ['name' => 'modo', 'label' => 'Atendimento', 'selected' => $filtros['modo'] ?? '', 'options' => ['' => 'Todos', 'humano' => 'Humano', 'ia' => 'IA']]) ?>
        <?= component('button', ['label' => 'Filtrar', 'type' => 'submit', 'variant' => 'outline']) ?>
    </form>
    <?= component('data-table', ['columns' => $colunas, 'rows' => $conversas, 'caption' => 'Contatos do WhatsApp', 'empty' => $filtros ? 'Nenhuma conversa corresponde aos filtros.' : 'As mensagens aparecem aqui depois de conectar a Evolution e configurar o webhook.']) ?>
    <?= component('pagination', $paginacao) ?>
</div>
