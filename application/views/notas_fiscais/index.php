<?php $id = (int) $os->idOs;
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6'; ?>
<div class="flex max-w-4xl flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div><h1 class="font-display text-heading-xl text-text"><?= e('Nota fiscal · Atendimento #' . $id) ?></h1>
            <p class="text-body-md text-muted"><?= e(($os->beneficiario_nome ?? $os->nomeCliente) . ' · Cliente: ' . $os->nomeCliente) ?></p>
        </div>
        <?= component('button', ['label' => 'Voltar ao atendimento', 'variant' => 'outline', 'href' => site_url('os/visualizar/' . $id)]) ?>
    </header>
    <?= component('pill-status', notaFiscalStatus((bool) $os->nf_solicitada, $notas)) ?>
    <section class="<?= e($caixa) ?>">
        <h2 class="text-heading-sm text-text">Solicitação da cliente</h2>
        <?php if ($pode_editar) { ?>
            <form method="post" action="<?= e(site_url('notasfiscais/solicitar/' . $id)) ?>" class="mt-4 flex flex-wrap items-end gap-3" <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                <?= component('select', ['name' => 'solicitada', 'label' => 'A cliente solicitou nota fiscal?', 'options' => ['0' => 'Não solicitada', '1' => 'Sim, solicitada'], 'selected' => (string) $os->nf_solicitada]) ?>
                <?= component('button', ['label' => 'Salvar solicitação', 'type' => 'submit', 'variant' => 'outline']) ?>
            </form>
        <?php } else { ?><p class="mt-3 text-body-md text-text"><?= e($os->nf_solicitada ? 'A cliente solicitou nota fiscal.' : 'Não há solicitação registrada.') ?></p><?php } ?>
    </section>
    <?php if ($pode_editar) { ?>
        <section class="<?= e($caixa) ?>">
            <h2 class="text-heading-sm text-text">Anexar nota fiscal</h2>
            <p class="mt-2 text-caption text-muted">Anexe o documento emitido fora do sistema. O arquivo ficará disponível para a cliente, inclusive quando o atendimento já estiver pago.</p>
            <form method="post" action="<?= e(site_url('notasfiscais/anexar/' . $id)) ?>" enctype="multipart/form-data" class="mt-4 grid gap-4 sm:grid-cols-2" <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                <?= component('input', ['name' => 'numero', 'label' => 'Número da nota (opcional)', 'attrs' => ['maxlength' => 60]]) ?>
                <?= component('input', ['name' => 'data_emissao', 'label' => 'Data de emissão (opcional)', 'type' => 'date', 'attrs' => ['max' => date('Y-m-d')]]) ?>
                <label class="flex flex-col gap-2 text-label-md text-text sm:col-span-2">Documento (PDF, JPG ou PNG)
                    <input type="file" name="userfile" required accept=".pdf,.jpg,.jpeg,.png" class="block max-w-full rounded-sm border border-input bg-field p-2 text-body-md text-text">
                </label>
                <div class="sm:col-span-2"><?= component('button', ['label' => 'Anexar nota fiscal', 'type' => 'submit', 'variant' => 'outline']) ?></div>
            </form>
        </section>
    <?php } ?>
    <section class="<?= e($caixa) ?>">
        <h2 class="text-heading-sm text-text">Documentos anexados</h2>
        <?php if ($notas === []) { ?><p class="mt-3 text-body-md text-muted">Nenhuma nota fiscal anexada a este atendimento.</p><?php } ?>
        <?php foreach ($notas as $nota) {
            $modal = 'nf-disponibilidade-' . (int) $nota->id; ?>
            <article class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3">
                <div>
                    <?= component('link', ['label' => $nota->numero ? 'NF ' . $nota->numero . ' · ' . $nota->nome : $nota->nome, 'href' => site_url('notasfiscais/arquivo/' . (int) $nota->id)]) ?>
                    <?php if ($nota->data_emissao) { ?><p class="mt-1 text-caption text-muted"><?= e('Emissão: ' . dataBr($nota->data_emissao)) ?></p><?php } ?>
                    <?= component('pill-status', ['label' => $nota->ativo ? 'Disponível para a cliente' : 'Retirada da área da cliente', 'variant' => $nota->ativo ? 'success' : 'neutral']) ?>
                </div>
                <?php if ($pode_editar) { ?>
                    <?= component('button', ['label' => $nota->ativo ? 'Retirar da área da cliente' : 'Disponibilizar novamente', 'variant' => 'ghost', 'attrs' => ['data-modal-abrir' => $modal]]) ?>
                    <form id="<?= e($modal . '-form') ?>" method="post" action="<?= e(site_url('notasfiscais/disponibilidade/' . $id)) ?>" hidden>
                        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                        <input type="hidden" name="nota" value="<?= e((int) $nota->id) ?>">
                        <input type="hidden" name="ativo" value="<?= e($nota->ativo ? '0' : '1') ?>">
                    </form>
                    <?= component('modal-confirm', ['id' => $modal, 'title' => $nota->ativo ? 'Retirar este documento da área da cliente?' : 'Disponibilizar este documento novamente?', 'message' => 'O arquivo e seu histórico serão preservados. Esta ação altera apenas o acesso pela área da cliente; não cancela nem emite uma nota fiscal.', 'confirm_label' => $nota->ativo ? 'Retirar documento' : 'Disponibilizar documento', 'confirm_attrs' => ['form' => $modal . '-form']]) ?>
                <?php } ?>
            </article>
        <?php } ?>
    </section>
</div>
