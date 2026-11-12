<div class="flex max-w-4xl flex-col gap-4 pt-2 pb-8">
    <header><h1 class="font-display text-heading-xl text-text">Nova ficha de atendimento</h1><p class="mt-2 text-body-md text-muted"><?= e(($os->beneficiario_nome ?? $os->nomeCliente) . ' · Atendimento #' . $os->idOs) ?></p></header>
    <form method="get" action="<?= e(site_url('fichas/criar/' . $os->idOs)) ?>" class="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-surface p-4">
        <?= component('select', ['name' => 'modelo', 'label' => 'Modelo de ficha', 'options' => array_column($modelos, 'nome', 'id'), 'selected' => $modelo ? (string) $modelo->id : '', 'placeholder' => 'Escolha o modelo', 'required' => true]) ?>
        <?= component('button', ['label' => 'Carregar modelo', 'type' => 'submit', 'variant' => 'outline']) ?>
        <?= component('button', ['label' => 'Configurar modelos', 'variant' => 'ghost', 'href' => site_url('fichas/modelo')]) ?>
    </form>
    <?php if (isset($erros['_geral'])) { ?><?= component('alert', ['variant' => 'danger', 'message' => $erros['_geral']]) ?><?php } ?>
    <?php if ($modelo) { ?>
        <form method="post" action="<?= e(site_url('fichas/criar/' . $os->idOs)) ?>" class="grid gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6" novalidate <?= js_module('formulario/padrao') ?>>
            <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
            <input type="hidden" name="modelo_id" value="<?= e($modelo->id) ?>"><input type="hidden" name="versao" value="<?= e(is_string($valores['versao'] ?? null) ? $valores['versao'] : $modelo->versao) ?>"><input type="hidden" name="chave" value="<?= e($chave) ?>">
            <input type="hidden" name="cliente_esperado" value="<?= e(is_string($valores['cliente_esperado'] ?? null) ? $valores['cliente_esperado'] : $os->clientes_id) ?>"><input type="hidden" name="dependente_esperado" value="<?= e(is_string($valores['dependente_esperado'] ?? null) ? $valores['dependente_esperado'] : (int) $os->dependente_id) ?>">
            <?= component('select', ['name' => 'servico', 'label' => 'Serviço deste atendimento', 'options' => array_column($servicos, 'servico', 'servicos_id'), 'selected' => is_string($valores['servico'] ?? null) ? $valores['servico'] : '', 'placeholder' => 'Escolha o serviço', 'required' => true]) ?>
            <?php foreach (json_decode($modelo->campos_json, true) as $id => $rotulo) { ?>
                <?= component('textarea', ['name' => 'respostas[' . $id . ']', 'label' => $rotulo, 'value' => is_string($valores['respostas'][$id] ?? null) ? $valores['respostas'][$id] : '', 'error' => $erros[$id] ?? null, 'rows' => 3, 'attrs' => ['maxlength' => 2000]]) ?>
            <?php } ?>
            <p class="text-caption text-muted">A ficha inicial será preservada. Informações complementares e correções devem ser registradas na evolução, com data e autoria.</p>
            <div class="flex flex-wrap gap-3"><?= component('button', ['label' => 'Registrar ficha', 'type' => 'submit', 'disabled' => $servicos === []]) ?><?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('fichas/atendimento/' . $os->idOs)]) ?></div>
            <?php if (! $servicos) { ?><p class="text-body-md text-muted">Adicione os serviços ao atendimento antes de iniciar uma ficha.</p><?php } ?>
        </form>
    <?php } ?>
</div>
