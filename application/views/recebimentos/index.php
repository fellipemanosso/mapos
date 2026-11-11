<?php
$id = (int) $os->idOs;
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$valor = function (string $campo, string $padrao, string $grupo) use ($formulario): string {
    $post = $this->input->post($campo);
    return $formulario === $grupo && is_string($post) ? $post : $padrao;
};
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-display text-heading-xl text-text"><?= e('Orçamento e recebimentos #' . $id) ?></h1>
            <p class="text-caption text-muted"><?= e(($os->beneficiario_nome ?? $os->nomeCliente) . ' · Cliente: ' . $os->nomeCliente) ?></p>
        </div>
        <?php if (! empty($os->pacote_id)) { ?><?= component('button', ['label' => 'Ver sessões do pacote', 'variant' => 'outline', 'href' => site_url('pacotes/visualizar/' . (int) $os->pacote_id)]) ?><?php } ?>
        <?= component('button', ['label' => 'Voltar ao atendimento', 'variant' => 'outline', 'href' => site_url('os/visualizar/' . $id)]) ?>
    </header>
    <?php if (isset($erros['_geral'])) { ?><?= component('alert', ['variant' => 'danger', 'message' => $erros['_geral']]) ?><?php } ?>
    <?php if ($legado) { ?>
        <?= component('alert', ['variant' => 'info', 'message' => 'Este atendimento foi faturado no fluxo anterior. Consulte o lançamento original no Financeiro; ele não é somado como um novo recebimento aqui.']) ?>
    <?php } ?>
    <div class="grid gap-4 sm:grid-cols-3">
        <?= component('kpi-card', ['label' => 'Total do orçamento', 'value' => dinheiro($total / 100), 'caption' => 'Serviços e produtos, incluindo deslocamento, menos descontos']) ?>
        <?= component('kpi-card', ['label' => 'Recebido', 'value' => $legado ? 'Consulte o financeiro' : dinheiro($recebido / 100), 'caption' => 'Recebimentos ativos deste atendimento']) ?>
        <?= component('kpi-card', ['label' => 'Saldo a receber', 'value' => $legado ? 'Consulte o financeiro' : dinheiro($saldo / 100), 'caption' => $os->saldo_vencimento ? 'Vencimento: ' . dataBr($os->saldo_vencimento) : 'Vencimento ainda não definido']) ?>
    </div>
    <section class="<?= e($caixa) ?>">
        <h2 class="text-heading-sm text-text">Condições do orçamento</h2>
        <p class="mt-2 text-body-md text-muted">Adicione os serviços e a taxa de deslocamento nos itens do atendimento. O sinal previsto é parte do total.</p>
        <?php if ($editar_orcamento) { ?>
            <form method="post" action="<?= e(site_url('recebimentos/orcamento/' . $id)) ?>" class="mt-4 grid gap-4 sm:grid-cols-3 sm:items-end" novalidate <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                <?= component('input', ['name' => 'sinal_previsto', 'label' => 'Sinal previsto (R$)', 'value' => $valor('sinal_previsto', number_format((float) $os->sinal_previsto, 2, ',', ''), 'orcamento'), 'required' => true, 'error' => $erros['sinal_previsto'] ?? null, 'attrs' => ['inputmode' => 'decimal']]) ?>
                <?= component('input', ['name' => 'saldo_vencimento', 'label' => 'Vencimento do saldo', 'type' => 'date', 'value' => $valor('saldo_vencimento', $os->saldo_vencimento ?: date('Y-m-d'), 'orcamento'), 'required' => true, 'error' => $erros['saldo_vencimento'] ?? null]) ?>
                <?= component('button', ['label' => 'Salvar condições', 'type' => 'submit', 'variant' => 'outline']) ?>
            </form>
        <?php } else { ?>
            <p class="mt-3 text-body-md text-text"><?= e('Sinal previsto: ' . dinheiro($os->sinal_previsto)) ?></p>
            <?php if ((int) $os->financeiro_confirmado) { ?>
                <p class="mt-2 text-caption text-muted">Valores confirmados pelo primeiro recebimento. Para rever o orçamento, estorne os registros de recebimento. A agenda continua disponível.</p>
            <?php } ?>
        <?php } ?>
    </section>
    <?php if ($pode_receber && $saldo > 0) { ?>
        <section class="<?= e($caixa) ?>">
            <h2 class="text-heading-sm text-text">Registrar recebimento</h2>
            <p class="mt-2 text-caption text-muted">Registre somente valores já recebidos. O sinal pode ser diferente do previsto, e o pagamento pode ser integral desde o início.</p>
            <form method="post" action="<?= e(site_url('recebimentos/receber/' . $id)) ?>" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" novalidate <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                <input type="hidden" name="chave" value="<?= e($chave) ?>">
                <?= component('select', ['name' => 'tipo', 'label' => 'Tipo', 'options' => ['sinal' => 'Sinal', 'parcial' => 'Pagamento parcial', 'integral' => 'Integral desde o início', 'quitacao' => 'Quitação do saldo'], 'selected' => $valor('tipo', $recebido > 0 ? 'quitacao' : ((float) $os->sinal_previsto > 0 && (float) $os->sinal_previsto < $total / 100 ? 'sinal' : 'integral'), 'receber'), 'error' => $erros['tipo'] ?? null, 'required' => true]) ?>
                <?= component('input', ['name' => 'valor', 'label' => 'Valor recebido (R$)', 'value' => $valor('valor', number_format(($recebido === 0 && (float) $os->sinal_previsto > 0 ? min((float) $os->sinal_previsto, $saldo / 100) : $saldo / 100), 2, ',', ''), 'receber'), 'required' => true, 'error' => $erros['valor'] ?? null, 'attrs' => ['inputmode' => 'decimal']]) ?>
                <?= component('select', ['name' => 'forma', 'label' => 'Forma de pagamento', 'options' => RECEBIMENTOS_FORMAS, 'selected' => $valor('forma', 'Pix', 'receber'), 'required' => true, 'error' => $erros['forma'] ?? null]) ?>
                <?= component('input', ['name' => 'data', 'label' => 'Recebido em', 'type' => 'date', 'value' => $valor('data', date('Y-m-d'), 'receber'), 'required' => true, 'error' => $erros['data'] ?? null, 'attrs' => ['max' => date('Y-m-d')]]) ?>
                <div class="sm:col-span-2 lg:col-span-4"><?= component('textarea', ['name' => 'observacoes', 'label' => 'Observações do recebimento', 'value' => $valor('observacoes', '', 'receber'), 'error' => $erros['observacoes'] ?? null, 'attrs' => ['maxlength' => 1000]]) ?></div>
                <div class="sm:col-span-2 lg:col-span-4"><?= component('button', ['label' => 'Registrar recebimento', 'type' => 'submit', 'variant' => 'outline']) ?></div>
            </form>
        </section>
    <?php } elseif (! $legado && $total > 0 && $saldo === 0) { ?>
        <?= component('alert', ['variant' => 'success', 'message' => 'Atendimento quitado. Todos os valores foram recebidos.']) ?>
    <?php } ?>
    <section class="<?= e($caixa) ?>">
        <h2 class="text-heading-sm text-text">Histórico de recebimentos</h2>
        <?php if ($historico === []) { ?><p class="mt-3 text-body-md text-muted">Nenhum recebimento registrado por este fluxo.</p><?php } ?>
        <?php foreach ($historico as $pagamento) { ?>
            <article class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3">
                <div>
                    <p class="text-label-md text-text"><?= e(recebimentoRotulo($pagamento->recebimento_tipo) . ' · ' . dinheiro($pagamento->recebimento_valor)) ?></p>
                    <p class="mt-1 text-caption text-muted"><?= e(dataBr($pagamento->data_pagamento) . ' · ' . $pagamento->forma_pgto) ?></p>
                    <?php if ($pagamento->observacoes) { ?><p class="mt-1 text-caption text-muted whitespace-pre-line"><?= e($pagamento->observacoes) ?></p><?php } ?>
                    <?php if ($pagamento->estornado_em) { ?><?= component('pill-status', ['label' => 'Estornado', 'variant' => 'neutral']) ?><?php } ?>
                </div>
                <?php if ($pode_estornar && ! $pagamento->estornado_em) { ?>
                    <?= component('button', ['label' => 'Estornar registro', 'variant' => 'ghost', 'attrs' => ['data-modal-abrir' => 'estorno-' . (int) $pagamento->idLancamentos]]) ?>
                    <form id="<?= e('estorno-form-' . (int) $pagamento->idLancamentos) ?>" method="post" action="<?= e(site_url('recebimentos/estornar/' . $id)) ?>" hidden>
                        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                        <input type="hidden" name="pagamento" value="<?= e((int) $pagamento->idLancamentos) ?>">
                    </form>
                    <?= component('modal-confirm', ['id' => 'estorno-' . (int) $pagamento->idLancamentos, 'title' => 'Estornar registro de recebimento?', 'message' => 'O valor sairá dos recebimentos e voltará ao saldo devedor. O histórico será preservado. Isso corrige o registro; não transfere nem devolve dinheiro.', 'confirm_label' => 'Estornar registro', 'confirm_attrs' => ['form' => 'estorno-form-' . (int) $pagamento->idLancamentos]]) ?>
                <?php } ?>
            </article>
        <?php } ?>
    </section>
</div>
