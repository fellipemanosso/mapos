<?php $caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6'; ?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap justify-between gap-3">
        <div>
            <h1 class="font-display text-heading-xl text-text"><?= e($pacote->nome) ?></h1>
            <p class="text-body-md text-muted"><?= e(($pacote->beneficiario ?: $pacote->nomeCliente) . ' · Responsável: ' . $pacote->nomeCliente) ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?= component('button', ['label' => 'Orçamento e recebimentos', 'variant' => 'outline', 'href' => site_url('recebimentos/index/' . (int) $pacote->os_id)]) ?>
            <?= component('button', ['label' => 'Ver atendimento', 'variant' => 'ghost', 'href' => site_url('os/visualizar/' . (int) $pacote->os_id)]) ?>
            <?= component('button', ['label' => 'Todos os pacotes', 'variant' => 'ghost', 'href' => site_url('pacotes')]) ?>
        </div>
    </header>
    <div class="grid gap-4 sm:grid-cols-3">
        <?= component('kpi-card', ['label' => 'Sessões contratadas', 'value' => (string) $progresso['total']]) ?>
        <?= component('kpi-card', ['label' => 'Realizadas', 'value' => (string) $progresso['realizadas']]) ?>
        <?= component('kpi-card', ['label' => 'Restantes', 'value' => (string) $progresso['restantes'], 'caption' => $progresso['agendadas'] . ' já agendadas']) ?>
    </div>
    <?php if ($pacote->status === 'Cancelado') { ?><?= component('alert', ['variant' => 'info', 'message' => 'O atendimento deste pacote está cancelado. As sessões e o histórico foram preservados.']) ?><?php } ?>
    <section class="<?= e($caixa) ?>">
        <h2 class="text-heading-sm text-text">Sessões do pacote</h2>
        <p class="mt-2 text-caption text-muted">Agendar reserva o horário. A sessão só é descontada das restantes quando marcada como realizada.</p>
        <?php foreach ($sessoes as $sessao) {
            $agendada = $sessao->agenda_id && ! (int) $sessao->cancelado;
            $modal = 'reabrir-sessao-' . (int) $sessao->id;
            ?>
            <article class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                <div>
                    <h3 class="text-label-md text-text"><?= e('Sessão ' . $sessao->numero . ' · ' . $sessao->nome_servico) ?></h3>
                    <p class="mt-1 text-caption text-muted"><?= e(($agendada ? (int) ((strtotime($sessao->fim) - strtotime($sessao->inicio)) / 60) : (int) $sessao->duracao_min) . ' min' . ($agendada ? ' · ' . date('d/m/Y H:i', strtotime($sessao->inicio)) . '–' . date('H:i', strtotime($sessao->fim)) : ' · Sem horário reservado')) ?></p>
                    <?= component('pill-status', ['label' => $sessao->realizado_em ? 'Realizada' : ($agendada ? 'Agendada' : 'Disponível'), 'variant' => $sessao->realizado_em ? 'success' : ($agendada ? 'info' : 'neutral')]) ?>
                </div>
                <?php if ($pode_editar) { ?>
                    <div class="flex flex-wrap items-center gap-2">
                        <?php if (! $sessao->realizado_em) { ?>
                            <?= component('button', ['label' => $agendada ? 'Editar horário' : 'Agendar sessão', 'variant' => 'outline', 'href' => $agendada ? site_url('agenda/formulario/' . (int) $sessao->agenda_id) : site_url('agenda/formulario') . '?sessao=' . (int) $sessao->id]) ?>
                            <?php if ($agendada) { ?>
                                <form method="post" action="<?= e(site_url('pacotes/realizar/' . (int) $pacote->id)) ?>">
                                    <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                                    <input type="hidden" name="sessao" value="<?= e((int) $sessao->id) ?>">
                                    <input type="hidden" name="acao" value="realizar">
                                    <?= component('button', ['label' => 'Marcar realizada', 'type' => 'submit', 'variant' => 'ghost']) ?>
                                </form>
                            <?php } ?>
                        <?php } else { ?>
                            <?= component('button', ['label' => 'Reabrir registro', 'variant' => 'ghost', 'attrs' => ['data-modal-abrir' => $modal]]) ?>
                            <form id="<?= e($modal . '-form') ?>" method="post" action="<?= e(site_url('pacotes/realizar/' . (int) $pacote->id)) ?>" hidden>
                                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                                <input type="hidden" name="sessao" value="<?= e((int) $sessao->id) ?>">
                                <input type="hidden" name="acao" value="reabrir">
                            </form>
                            <?= component('modal-confirm', ['id' => $modal, 'title' => 'Reabrir registro da sessão?', 'message' => 'A sessão voltará às restantes. O horário e os pagamentos serão preservados.', 'confirm_label' => 'Reabrir registro', 'confirm_attrs' => ['form' => $modal . '-form']]) ?>
                        <?php } ?>
                    </div>
                <?php } ?>
            </article>
        <?php } ?>
    </section>
</div>
