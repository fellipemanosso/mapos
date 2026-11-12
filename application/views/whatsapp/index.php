<div class="flex max-w-4xl flex-col gap-4 pt-2 pb-8">
    <header>
        <h1 class="font-display text-heading-xl text-text">WhatsApp</h1>
        <p class="mt-2 text-body-md text-muted">Conecte a instância existente da Evolution e acompanhe a conexão por aqui.</p>
    </header>
    <?php if ($erro) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => $erro]) ?>
    <?php } ?>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6">
        <h2 class="text-heading-sm text-text">Conexão com a Evolution</h2>
        <form method="post" action="<?= e(site_url('whatsapp/salvar')) ?>" class="mt-4 grid gap-4 sm:grid-cols-2" novalidate <?= js_module('formulario/padrao') ?>>
            <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
            <div class="sm:col-span-2">
                <?= component('input', ['name' => 'url', 'label' => 'URL da Evolution', 'type' => 'url', 'value' => $configuracao['url'], 'required' => true, 'error' => $erros['url'] ?? null, 'placeholder' => 'https://evolution.seudominio.com.br', 'attrs' => ['maxlength' => 500]]) ?>
            </div>
            <?= component('input', ['name' => 'instancia', 'label' => 'Nome da instância', 'value' => $configuracao['instancia'], 'required' => true, 'error' => $erros['instancia'] ?? null, 'attrs' => ['maxlength' => 100], 'help' => 'Informe o nome exato da instância já criada na Evolution.']) ?>
            <?= component('input', ['name' => 'chave', 'label' => 'Chave de acesso', 'type' => 'password', 'autocomplete' => 'new-password', 'required' => ! $tem_chave, 'error' => $erros['chave'] ?? null, 'attrs' => ['maxlength' => 1000], 'help' => $tem_chave ? 'Chave salva. Deixe em branco para mantê-la no mesmo servidor.' : 'Use a chave da instância, quando disponível. Ela não será exibida depois de salva.']) ?>
            <div class="sm:col-span-2"><?= component('button', ['label' => 'Salvar configuração', 'type' => 'submit']) ?></div>
        </form>
    </section>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-heading-sm text-text">Instância do WhatsApp</h2>
            <?= component('pill-status', $estado) ?>
        </div>
        <p class="mt-2 text-body-md text-muted">Consulte o status para testar a configuração. Para conectar pelo QR Code, abra Aparelhos conectados no WhatsApp do celular.</p>
        <p class="mt-2 text-caption text-muted">Os controles usam a configuração salva. O QR Code é temporário; se expirar, clique em Conectar novamente.</p>
        <form method="post" action="<?= e(site_url('whatsapp/executar')) ?>" class="mt-4 flex flex-wrap gap-3">
            <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
            <?= component('button', ['label' => 'Consultar status', 'type' => 'submit', 'variant' => 'outline', 'disabled' => ! $tem_chave, 'attrs' => ['name' => 'acao', 'value' => 'status']]) ?>
            <?= component('button', ['label' => 'Conectar / QR Code', 'type' => 'submit', 'variant' => 'outline', 'disabled' => ! $tem_chave, 'attrs' => ['name' => 'acao', 'value' => 'conectar']]) ?>
            <?= component('button', ['label' => 'Reiniciar conexão', 'variant' => 'ghost', 'disabled' => ! $tem_chave, 'attrs' => ['data-modal-abrir' => 'whatsapp-reiniciar']]) ?>
            <?= component('button', ['label' => 'Desconectar', 'variant' => 'ghost', 'disabled' => ! $tem_chave, 'attrs' => ['data-modal-abrir' => 'whatsapp-desconectar']]) ?>
        </form>
        <?php if ($qr) { ?>
            <div class="mt-4 flex flex-col items-start gap-3">
                <h3 class="text-heading-sm text-text">Leia o QR Code com o celular</h3>
                <img src="<?= e($qr) ?>" alt="QR Code temporário para conectar o WhatsApp" class="h-auto max-w-full rounded-sm border border-border">
                <p class="text-caption text-muted">Após a leitura, clique em Consultar status para confirmar a conexão.</p>
            </div>
        <?php } ?>
    </section>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-heading-sm text-text">Atendimento automático</h2>
            <?= component('pill-status', ['label' => 'Desligado', 'variant' => 'neutral']) ?>
        </div>
        <p class="mt-2 text-body-md text-muted">Esta etapa gerencia apenas a conexão. Respostas com IA, captura de leads e lembretes serão configurados na próxima etapa.</p>
        <p class="mt-2 text-caption text-muted">Automações já configuradas diretamente na Evolution continuam sob o controle dela.</p>
    </section>
    <?php foreach (['reiniciar' => ['Reiniciar a conexão?', 'O WhatsApp pode ficar indisponível por alguns instantes.', 'Reiniciar'], 'desconectar' => ['Desconectar o WhatsApp?', 'A sessão desta instância será encerrada. Para conectar novamente, pode ser necessário ler outro QR Code.', 'Desconectar']] as $acao => [$titulo, $mensagem, $rotulo]) { ?>
        <form id="<?= e('whatsapp-' . $acao . '-form') ?>" method="post" action="<?= e(site_url('whatsapp/executar')) ?>" hidden>
            <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
            <input type="hidden" name="acao" value="<?= e($acao) ?>">
        </form>
        <?= component('modal-confirm', ['id' => 'whatsapp-' . $acao, 'title' => $titulo, 'message' => $mensagem, 'confirm_label' => $rotulo, 'confirm_attrs' => ['form' => 'whatsapp-' . $acao . '-form']]) ?>
    <?php } ?>
</div>
