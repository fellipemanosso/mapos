<?php $colunas = [
    ['label' => 'Retorno', 'render' => fn ($r) => dataBr($r->retorno)],
    ['label' => 'Pessoa atendida', 'render' => fn ($r) => component('link', ['label' => $r->paciente_nome, 'href' => site_url('fichas/visualizar/' . $r->ficha_id)])],
    ['key' => 'responsavel_nome', 'label' => 'Cliente / responsável'], ['key' => 'servico_nome', 'label' => 'Serviço'],
    ['label' => 'Situação', 'render' => fn ($r) => component('pill-status', ['label' => $r->retorno_status === 'concluido' ? 'Concluído' : 'Pendente', 'variant' => $r->retorno_status === 'concluido' ? 'success' : 'warning'])],
]; ?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="font-display text-heading-xl text-text">Fichas e retornos</h1><p class="mt-2 text-body-md text-muted">Acompanhe os retornos e acesse o histórico pela pessoa atendida.</p></div><?php if ($pode_modelo) { ?><?= component('button', ['label' => 'Modelos e termos', 'variant' => 'outline', 'href' => site_url('fichas/modelo')]) ?><?php } ?></header>
    <p class="text-body-md text-muted">Para iniciar uma ficha, abra um atendimento e clique em Ficha de atendimento.</p>
    <form method="get" action="<?= e(site_url('fichas')) ?>" class="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-surface p-4">
        <?= component('select', ['name' => 'status', 'label' => 'Situação do retorno', 'options' => ['pendente' => 'Pendente', 'concluido' => 'Concluído'], 'selected' => $status]) ?>
        <?= component('input', ['name' => 'ate', 'label' => 'Retornos até', 'type' => 'date', 'value' => $ate]) ?>
        <?= component('button', ['label' => 'Filtrar', 'type' => 'submit', 'variant' => 'outline']) ?>
    </form>
    <?= component('data-table', ['columns' => $colunas, 'rows' => $retornos, 'caption' => 'Acompanhamento de retornos', 'empty' => component('empty-state', ['title' => 'Nenhum retorno neste período', 'message' => 'Os retornos definidos nos registros de atendimento aparecem aqui.', 'icon' => 'file-text'])]) ?>
    <?= component('pagination', $paginacao) ?>
</div>
