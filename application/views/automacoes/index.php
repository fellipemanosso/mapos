<div class="flex max-w-5xl flex-col gap-4 pt-2 pb-8">
    <header><h1 class="font-display text-heading-xl text-text">Perfil e automações</h1><p class="mt-2 text-body-md text-muted">Prepare as informações comerciais, mensagens e horários de envio.</p></header>
    <?php if (isset($erros['_geral'])) { ?><?= component('alert', ['variant' => 'danger', 'message' => $erros['_geral']]) ?><?php } ?>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6">
        <h2 class="text-heading-sm text-text">Conexões e processamento</h2>
        <div class="mt-3 flex flex-wrap gap-3">
            <?= component('pill-status', ['label' => $tem_webhook ? 'Segredo do webhook configurado' : 'Webhook pendente', 'variant' => $tem_webhook ? 'success' : 'warning']) ?>
            <?= component('pill-status', ['label' => $tem_ia ? 'Chave da IA configurada' : 'IA sem chave', 'variant' => $tem_ia ? 'success' : 'neutral']) ?>
            <?= component('pill-status', ['label' => $tem_smtp ? 'SMTP configurado; valide o envio' : 'E-mail pendente', 'variant' => $tem_smtp ? 'info' : 'neutral']) ?>
        </div>
        <p class="mt-3 text-body-md text-muted">Último processamento: <?= e($estado->ultimo_worker ?: 'Ainda não executado') ?>. A rotina deve rodar a cada minuto no servidor.</p>
        <p class="mt-2 break-all text-body-sm text-muted">Webhook: <?= e($webhook_url) ?></p>
        <p class="mt-2 text-body-sm text-muted">Na Evolution, habilite MESSAGES_UPSERT, desative eventos na URL e Base64, e configure o cabeçalho X-Mapos-Webhook com o segredo do servidor. Chaves nunca são exibidas nesta tela.</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <?= component('button', ['label' => 'Conexão da Evolution', 'href' => site_url('whatsapp'), 'variant' => 'outline']) ?>
            <?= component('button', ['label' => 'Nome e aparência do sistema', 'href' => site_url('mapos/configurar'), 'variant' => 'ghost']) ?>
            <?php if ($pode_emitente) { ?><?= component('button', ['label' => 'Dados do MEI e logotipo', 'href' => site_url('mapos/emitente'), 'variant' => 'ghost']) ?><?php } ?>
        </div>
    </section>
    <form method="post" action="<?= e(site_url('automacoes/salvar')) ?>" class="flex flex-col gap-4" novalidate <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="versao" value="<?= e($estado->versao) ?>">
        <section class="grid gap-4 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 sm:p-6">
            <h2 class="text-heading-sm text-text sm:col-span-2">Informações da profissional</h2>
            <?php foreach (['nome' => 'Nome usado nas mensagens', 'horarios' => 'Horários de atendimento', 'area' => 'Cidades e bairros atendidos'] as $campo => $rotulo) { ?>
                <?= component('input', ['name' => $campo, 'label' => $rotulo, 'value' => $valores[$campo], 'error' => $erros[$campo] ?? null, 'attrs' => ['maxlength' => 3000]]) ?>
            <?php } ?>
            <?php foreach (['apresentacao' => 'Apresentação e serviços', 'politicas' => 'Sinal, deslocamento, cancelamento e pacotes'] as $campo => $rotulo) { ?>
                <div class="sm:col-span-2"><?= component('textarea', ['name' => $campo, 'label' => $rotulo, 'value' => $valores[$campo], 'error' => $erros[$campo] ?? null, 'attrs' => ['maxlength' => 3000], 'help' => 'Informações comerciais para a assistente. Não inclua dados de clientes ou orientações clínicas.']) ?></div>
            <?php } ?>
        </section>
        <section class="grid gap-4 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 sm:p-6">
            <h2 class="text-heading-sm text-text sm:col-span-2">O que deseja ativar?</h2>
            <?php foreach (['automatico' => 'Boas-vindas e atendimento automático', 'ia' => 'Respostas comerciais com IA após autorização', 'lembretes' => 'Confirmação 48h antes e lembrete 24h antes', 'cobrancas' => 'Aviso semanal de saldo vencido', 'retornos' => 'Lembrete no dia previsto para retorno'] as $campo => $rotulo) { ?>
                <?= component('checkbox', ['name' => $campo, 'label' => $rotulo, 'value' => '1', 'checked' => $valores[$campo]]) ?>
            <?php } ?>
            <p class="text-body-sm text-muted sm:col-span-2">Lembretes exigem cliente vinculada à conversa e autorização registrada. A cliente pode escrever SAIR a qualquer momento. A IA não marca horários nem altera pagamentos.</p>
            <?= component('input', ['name' => 'modelo_ia', 'label' => 'Modelo OpenAI', 'value' => $valores['modelo_ia'], 'error' => $erros['modelo_ia'] ?? null, 'help' => 'Use o identificador de um modelo disponível na sua conta. A chave é configurada no servidor.', 'attrs' => ['maxlength' => 100]]) ?>
            <?= component('input', ['name' => 'limite_diario', 'label' => 'Limite diário de envios', 'type' => 'number', 'value' => $valores['limite_diario'], 'error' => $erros['limite_diario'] ?? null, 'attrs' => ['min' => 1, 'max' => 500]]) ?>
            <?php foreach (['inicio_envio' => 'Enviar a partir de', 'fim_envio' => 'Enviar até'] as $campo => $rotulo) { ?><?= component('input', ['name' => $campo, 'label' => $rotulo, 'type' => 'time', 'value' => $valores[$campo], 'error' => $erros[$campo] ?? null]) ?><?php } ?>
        </section>
        <section class="grid gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h2 class="text-heading-sm text-text">Modelos de mensagens</h2>
            <p class="text-body-sm text-muted">Campos disponíveis: {profissional}, {data} e {hora} nos horários; {atendimento} e {saldo} nas cobranças. A opção SAIR é acrescentada aos lembretes automaticamente.</p>
            <?php foreach (['boas_vindas' => 'Boas-vindas', 'confirmacao' => 'Pedido de confirmação', 'lembrete' => 'Lembrete de atendimento', 'cobranca' => 'Saldo pendente', 'retorno' => 'Retorno'] as $campo => $rotulo) { ?>
                <?= component('textarea', ['name' => $campo, 'label' => $rotulo, 'value' => $valores[$campo], 'error' => $erros[$campo] ?? null, 'required' => true, 'attrs' => ['maxlength' => 3000]]) ?>
            <?php } ?>
        </section>
        <div><?= component('button', ['label' => 'Salvar perfil e automações', 'type' => 'submit']) ?></div>
    </form>
</div>
