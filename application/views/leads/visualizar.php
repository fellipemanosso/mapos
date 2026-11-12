<?php $csrf = $this->security->get_csrf_token_name();
$token = $this->security->get_csrf_hash(); ?>
<div class="flex max-w-5xl flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div><h1 class="font-display text-heading-xl text-text"><?= e($lead->nome) ?></h1><p class="mt-2 text-body-md text-muted"><?= e($lead->telefone . ($lead->bebe ? ' · Bebê: ' . $lead->bebe : '')) ?></p></div>
        <div class="flex flex-wrap gap-2">
            <?= component('button', ['label' => 'Voltar', 'variant' => 'ghost', 'href' => site_url('leads')]) ?>
            <?php if (! $lead->os_id && $pode_formulario) { ?><?= component('button', ['label' => 'Editar orçamento', 'variant' => 'outline', 'href' => site_url('leads/formulario/' . $lead->id)]) ?><?php } ?>
            <?php if ($itens) { ?><?= component('button', ['label' => 'Imprimir orçamento', 'variant' => 'outline', 'href' => site_url('leads/imprimir/' . $lead->id), 'attrs' => ['target' => '_blank', 'rel' => 'noopener']]) ?><?php } ?>
        </div>
    </header>
    <?= component('pill-status', leadStatus($lead->etapa)) ?>
    <?php if ($erro) { ?><?= component('alert', ['variant' => 'danger', 'message' => $erro]) ?><?php } ?>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6">
        <h2 class="text-heading-sm text-text">Contato</h2>
        <p class="mt-3 text-body-md text-text"><?= e($lead->interesse) ?></p>
        <p class="mt-2 text-caption text-muted"><?= e('Origem: ' . (LEADS_ORIGENS[$lead->origem] ?? $lead->origem) . ' · Retomar contato: ' . ($lead->retorno ? dataBr($lead->retorno) : 'não definido')) ?></p>
        <?php if ($lead->observacoes) { ?><h3 class="mt-4 text-label-md text-text">Observações internas</h3><p class="mt-2 whitespace-pre-wrap text-body-md text-text"><?= e($lead->observacoes) ?></p><?php } ?>
        <?php if ($lead->motivo_perda) { ?><p class="mt-3 text-body-md text-muted"><?= e('Motivo da perda: ' . $lead->motivo_perda) ?></p><?php } ?>
    </section>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6">
        <h2 class="text-heading-sm text-text">Orçamento #<?= e($lead->id) ?></h2>
        <?php foreach ($itens as $item) { ?><div class="mt-3 flex flex-wrap justify-between gap-2 border-b border-border pb-2"><p class="text-body-md text-text"><?= e($item['nome'] . ' · ' . $item['quantidade'] . ' × ' . dinheiro($item['preco'])) ?></p><p class="text-body-md text-text"><?= e(dinheiro($item['quantidade'] * (float) $item['preco'])) ?></p></div><?php } ?>
        <?php if (! $itens) { ?><p class="mt-3 text-body-md text-muted">Ainda não há serviços no orçamento.</p><?php } ?>
        <p class="mt-4 text-heading-sm text-text"><?= e('Total: ' . dinheiro($lead->total)) ?></p>
        <p class="mt-2 text-body-md text-muted"><?= e('Sinal previsto: ' . dinheiro($lead->sinal) . ' · Saldo após sinal: ' . dinheiro((float) $lead->total - (float) $lead->sinal)) ?></p>
        <?php if ($lead->validade) { ?><p class="mt-2 text-body-md text-muted"><?= e('Válido até ' . dataBr($lead->validade)) ?></p><?php } ?>
        <?php if ($lead->condicoes) { ?><p class="mt-3 whitespace-pre-wrap text-body-md text-text"><?= e($lead->condicoes) ?></p><?php } ?>
    </section>
    <?php if ($lead->os_id) { ?>
        <section class="rounded-xl border border-border bg-surface p-4 sm:p-6"><h2 class="text-heading-sm text-text">Atendimento vinculado</h2><p class="mt-2 text-body-md text-muted">O orçamento original foi preservado. Pagamentos e agendamentos são gerenciados no atendimento.</p><div class="mt-4"><?= component('button', ['label' => 'Abrir atendimento #' . $lead->os_id, 'href' => site_url('os/visualizar/' . $lead->os_id)]) ?></div></section>
    <?php } else { ?>
        <?php if ($pode_editar) { ?>
            <section class="rounded-xl border border-border bg-surface p-4 sm:p-6"><h2 class="text-heading-sm text-text">Andamento da negociação</h2>
                <form method="post" action="<?= e(site_url('leads/etapa/' . $lead->id)) ?>" class="mt-4 grid items-end gap-4 sm:grid-cols-2" <?= js_module('formulario/padrao') ?>>
                    <input type="hidden" name="<?= e($csrf) ?>" value="<?= e($token) ?>"><input type="hidden" name="versao" value="<?= e($lead->versao) ?>">
                    <?= component('select', ['name' => 'etapa', 'label' => 'Etapa', 'options' => array_diff_key(LEADS_ETAPAS, ['convertido' => true]), 'selected' => $lead->etapa]) ?>
                    <?= component('input', ['name' => 'motivo_perda', 'label' => 'Motivo (obrigatório ao marcar perdido)', 'value' => $lead->motivo_perda, 'attrs' => ['maxlength' => 255]]) ?>
                    <p class="text-caption text-muted">Marcar como enviado registra o acompanhamento; não envia mensagem pelo WhatsApp.</p>
                    <?= component('button', ['label' => 'Atualizar etapa', 'type' => 'submit', 'variant' => 'outline']) ?>
                </form>
            </section>
        <?php } ?>
        <?php if ($pode_converter && $itens && $lead->etapa !== 'perdido') { ?>
            <section class="rounded-xl border border-border bg-surface p-4 sm:p-6"><h2 class="text-heading-sm text-text">Converter em atendimento</h2><p class="mt-2 text-body-md text-muted">Confirme a contratação. Os serviços e o sinal previsto serão copiados; o pagamento e o agendamento são registrados depois.</p>
                <?php if ($candidatas) { ?><?= component('alert', ['variant' => 'info', 'message' => 'Encontramos cadastro(s) com este telefone. Confira a cliente antes de converter: ' . implode(', ', array_column($candidatas, 'nomeCliente'))]) ?><?php } ?>
                <form method="post" action="<?= e(site_url('leads/converter/' . $lead->id)) ?>" class="mt-4 grid gap-4 sm:grid-cols-2" <?= js_module('formulario/padrao') ?>>
                    <input type="hidden" name="<?= e($csrf) ?>" value="<?= e($token) ?>"><input type="hidden" name="versao" value="<?= e($lead->versao) ?>">
                    <?= component('select', ['name' => 'cliente', 'label' => 'Cliente ou responsável', 'options' => ['0' => 'Criar cadastro de ' . $lead->nome] + array_column($clientes, 'nomeCliente', 'idClientes'), 'placeholder' => 'Confira e escolha a cliente', 'required' => true]) ?>
                    <?= component('select', ['name' => 'profissional', 'label' => 'Profissional responsável', 'options' => array_column($profissionais, 'nome', 'idUsuarios'), 'selected' => (string) $this->session->userdata('id_admin'), 'required' => true]) ?>
                    <p class="text-caption text-muted">Para um bebê, será usado ou criado o dependente com o nome informado, vinculado à cliente escolhida.</p>
                    <?= component('button', ['label' => 'Confirmar contratação e criar atendimento', 'type' => 'submit']) ?>
                </form>
            </section>
        <?php } ?>
    <?php } ?>
</div>
