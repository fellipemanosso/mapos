<div class="flex flex-col gap-4 pt-2 pb-8">
    <h1 class="font-display text-heading-xl text-text">Pacotes e sessões</h1>
    <form method="get" action="<?= e(site_url('pacotes')) ?>" class="flex flex-wrap items-end gap-2">
        <?= component('input', ['name' => 'pesquisa', 'label' => 'Buscar pacote, cliente ou bebê', 'value' => $pesquisa, 'type' => 'search', 'attrs' => ['maxlength' => 100]]) ?>
        <?= component('button', ['label' => 'Buscar', 'variant' => 'outline', 'type' => 'submit']) ?>
    </form>
    <?php if ($pacotes === []) { ?><?= component('empty-state', ['title' => 'Nenhum pacote encontrado', 'message' => 'Os pacotes cadastrados e suas sessões aparecem aqui.', 'icon' => 'package']) ?><?php } ?>
    <?php foreach ($pacotes as $pacote) { ?>
        <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-surface p-4">
            <div>
                <h2 class="text-heading-sm text-text"><?= e($pacote->nome) ?></h2>
                <p class="mt-1 text-body-md text-muted"><?= e(($pacote->beneficiario ?: $pacote->nomeCliente) . ' · ' . $pacote->progresso['realizadas'] . '/' . $pacote->progresso['total'] . ' sessões realizadas') ?></p>
                <?php if ($pacote->status === 'Cancelado') { ?><?= component('pill-status', ['label' => 'Atendimento cancelado', 'variant' => 'neutral']) ?><?php } ?>
            </div>
            <?= component('button', ['label' => 'Ver pacote', 'variant' => 'outline', 'href' => site_url('pacotes/visualizar/' . (int) $pacote->id)]) ?>
        </article>
    <?php } ?>
    <?= component('pagination', $paginacao) ?>
</div>
