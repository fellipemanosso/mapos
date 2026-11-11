<?php
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$colunas = [
    ['label' => 'Descrição', 'render' => static fn ($l) => $l->descricao],
    ['label' => 'Vencimento', 'render' => static fn ($l) => dataBr($l->data_vencimento)],
    ['label' => 'Valor', 'render' => static fn ($l) => dinheiro(financeiroLiquido($l))],
    ['label' => 'Situação', 'render' => static fn ($l) => component('pill-status', financeiroStatusPill($l, date('Y-m-d')))],
    ['label' => 'Pagamento', 'render' => static fn ($l) => (int) $l->baixado === 1 ? dataBr($l->data_pagamento) . ' · ' . $l->forma_pgto : 'Ainda não pago'],
];
if ($pode_editar) {
    $colunas[] = ['label' => 'Ação', 'render' => static fn ($l) => component('button', ['label' => (int) $l->baixado === 1 ? 'Ver / corrigir pagamento' : 'Registrar pagamento', 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('financeiro/editar/' . (int) $l->idLancamentos)])];
}
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap justify-between gap-3">
        <div><h1 class="font-display text-heading-xl text-text"><?= e($grupo->descricao) ?></h1>
            <p class="text-body-md text-muted"><?= e(DESPESAS_CATEGORIAS[$grupo->categoria] . ' · ' . DESPESAS_MODOS[$grupo->modo] . ($grupo->frequencia ? ' · ' . DESPESAS_FREQUENCIAS[$grupo->frequencia] : '')) ?></p>
        </div>
        <?= component('button', ['label' => 'Todas as despesas', 'variant' => 'outline', 'href' => site_url('despesas')]) ?>
    </header>
    <div class="grid gap-4 sm:grid-cols-3">
        <?= component('kpi-card', ['label' => 'Total', 'value' => dinheiro($resumo['total'] / 100), 'caption' => $resumo['quantidade'] . ' lançamentos ativos']) ?>
        <?= component('kpi-card', ['label' => 'Pago', 'value' => dinheiro($resumo['pago'] / 100), 'caption' => $resumo['pagas'] . ($resumo['pagas'] === 1 ? ' pagamento registrado' : ' pagamentos registrados')]) ?>
        <?= component('kpi-card', ['label' => 'A pagar', 'value' => dinheiro($resumo['pendente'] / 100)]) ?>
    </div>
    <?php if ($grupo->modo === 'parcelada') { ?>
        <?= component('alert', ['variant' => 'info', 'message' => 'Compra dividida em ' . $grupo->quantidade . ' parcelas mensais, além da entrada quando informada. Registre o pagamento de cada parcela separadamente.']) ?>
    <?php } elseif ($grupo->modo === 'recorrente') { ?>
        <?= component('alert', ['variant' => 'info', 'message' => 'Foram programadas ' . $grupo->quantidade . ' ocorrências. Os valores são previsões até registrar o pagamento; não há renovação automática após este período.']) ?>
    <?php } ?>
    <?= component('data-table', ['columns' => $colunas, 'rows' => $linhas, 'caption' => 'Lançamentos da despesa', 'empty' => component('empty-state', ['title' => 'Sem lançamentos ativos', 'message' => 'Os lançamentos desta despesa foram removidos do financeiro.', 'icon' => 'banknote'])]) ?>
    <section class="<?= e($caixa) ?>">
        <h2 class="text-heading-sm text-text">Comprovantes e documentos da compra</h2>
        <p class="mt-2 text-caption text-muted">Anexe recibos, comprovantes de pagamento ou documentos do fornecedor. Os arquivos ficam vinculados a esta despesa.</p>
        <?php foreach ($anexos as $anexo) { ?>
            <p class="mt-3"><?= component('link', ['label' => $anexo->nome, 'href' => site_url('despesas/comprovante/' . (int) $anexo->id)]) ?></p>
        <?php } ?>
        <?php if ($pode_editar) { ?>
            <form method="post" action="<?= e(site_url('despesas/anexar/' . (int) $grupo->id)) ?>" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                <label class="flex flex-col gap-2 text-label-md text-text">Comprovante (PDF, JPG ou PNG)
                    <input type="file" name="userfile" accept=".pdf,.jpg,.jpeg,.png" required class="block max-w-full rounded-sm border border-input bg-field p-2 text-body-md text-text">
                </label>
                <?= component('button', ['label' => 'Anexar comprovante', 'variant' => 'outline', 'type' => 'submit']) ?>
            </form>
        <?php } ?>
    </section>
</div>
