<div class="flex max-w-5xl flex-col gap-4 pt-2 pb-8">
    <header><h1 class="font-display text-heading-xl text-text"><?= e($conversa->nome) ?></h1><p class="mt-2 text-body-md text-muted"><?= e($conversa->telefone) ?> · <?= e($conversa->modo === 'ia' ? 'Atendimento por IA' : 'Atendimento humano') ?></p></header>
    <?php if ($conversa->optout) { ?><?= component('alert', ['variant' => 'warning', 'message' => 'Esta pessoa solicitou a interrupção das mensagens. Novos envios estão bloqueados.']) ?><?php } ?>
    <?php if ($conversa->lead_id && $pode_lead) { ?><div><?= component('button', ['label' => 'Abrir lead e orçamento', 'href' => site_url('leads/visualizar/' . $conversa->lead_id), 'variant' => 'outline']) ?></div><?php } ?>
    <?php if ($pode_editar) { ?>
        <section class="rounded-xl border border-border bg-surface p-4 sm:p-6"><h2 class="text-heading-sm text-text">Controle do atendimento</h2>
            <form method="post" action="<?= e(site_url('conversas/ajustar/' . $conversa->id)) ?>" class="mt-4 grid gap-4 sm:grid-cols-2">
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                <?= component('select', ['name' => 'modo', 'label' => 'Quem responde', 'selected' => $conversa->modo, 'options' => ['humano' => 'Profissional — pausar IA', 'ia' => 'IA — exige autorização da cliente']]) ?>
                <?php if ($pode_cliente) { ?><?= component('input', ['name' => 'cliente_id', 'label' => 'Código da cliente responsável', 'type' => 'number', 'value' => $conversa->cliente_id ?: '', 'attrs' => ['min' => 1], 'help' => $cliente ? 'Vínculo atual: ' . $cliente->nomeCliente : 'Confira o cadastro antes de vincular. O código é o número da cliente.']) ?><?php } else { ?><input type="hidden" name="cliente_id" value="<?= e($conversa->cliente_id ?: '') ?>"><?php } ?>
                <?= component('checkbox', ['name' => 'lembretes', 'label' => 'A cliente autorizou lembretes, cobranças e retorno neste número', 'value' => '1', 'checked' => (bool) $conversa->lembretes, 'disabled' => (bool) $conversa->optout]) ?>
                <p class="text-body-sm text-muted">IA: <?= e($conversa->consentimento_ia ? 'Autorizada pela cliente' : 'Ainda não autorizada') ?>. Lembretes autorizados em: <?= e($conversa->consentimento_em ?: 'Ainda não registrado') ?>.</p>
                <div><?= component('button', ['label' => 'Atualizar controle', 'type' => 'submit', 'variant' => 'outline']) ?></div>
            </form>
        </section>
        <?php if (! $conversa->optout) { ?><section class="rounded-xl border border-border bg-surface p-4 sm:p-6"><h2 class="text-heading-sm text-text">Responder como profissional</h2>
            <form method="post" action="<?= e(site_url('conversas/responder/' . $conversa->id)) ?>" class="mt-4 flex flex-col gap-4" novalidate <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>"><input type="hidden" name="chave" value="<?= e($chave) ?>">
                <?= component('textarea', ['name' => 'texto', 'label' => 'Mensagem', 'required' => true, 'attrs' => ['maxlength' => 4000], 'help' => 'A resposta pausa a IA e entra na fila de envio, respeitando horário e limite diário.']) ?>
                <div><?= component('button', ['label' => 'Assumir e enviar mensagem', 'type' => 'submit']) ?></div>
            </form>
        </section><?php } ?>
    <?php } ?>
    <section class="flex flex-col gap-3"><h2 class="text-heading-sm text-text">Histórico — mais recentes primeiro</h2>
        <?php foreach ($mensagens as $m) { ?><article class="rounded-xl border border-border bg-surface p-4"><div class="flex flex-wrap items-center justify-between gap-2"><p class="text-body-sm text-muted"><?= e($m->direcao === 'entrada' ? 'Cliente' : 'Profissional / assistente') ?> · <?= e($m->criado_em) ?> · <?= e($m->tipo) ?></p><?= component('pill-status', ['label' => ucfirst(str_replace('_', ' ', $m->estado)), 'variant' => $m->estado === 'incerto' ? 'warning' : ($m->estado === 'enviado' ? 'success' : 'neutral')]) ?></div><p class="mt-3 whitespace-pre-wrap break-words text-body-md text-text"><?= e($m->texto) ?></p>
            <?php if ($m->erro) { ?><p class="mt-2 text-body-sm text-muted"><?= e($m->erro) ?></p><?php } ?>
            <?php if ($pode_editar && in_array($m->estado, ['pendente', 'aguarda_ia'], true)) { ?><form method="post" action="<?= e(site_url('conversas/cancelar/' . $conversa->id)) ?>" class="mt-3"><input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>"><input type="hidden" name="mensagem" value="<?= e($m->id) ?>"><?= component('button', ['label' => 'Cancelar envio pendente', 'type' => 'submit', 'variant' => 'ghost']) ?></form><?php } ?>
        </article><?php } ?>
    </section>
    <?= component('pagination', $paginacao) ?>
</div>
