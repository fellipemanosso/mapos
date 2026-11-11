<?php
$valor = static fn ($campo, $padrao = '') => is_string($valores[$campo] ?? null) ? $valores[$campo] : $padrao;
$dependentesOpcoes = [];
foreach ($dependentes as $dependente) {
    $dependentesOpcoes[$dependente->id] = $dependente->nome . ' · Responsável: ' . $dependente->nomeCliente;
}
?>
<div class="flex max-w-5xl flex-col gap-4 pt-2 pb-8">
    <h1 class="font-display text-heading-xl text-text">Novo pacote</h1>
    <p class="text-body-md text-muted">Escolha os serviços e suas sessões. O preço por sessão no pacote pode ser diferente do preço avulso.</p>
    <?php if (isset($erros['_geral'])) { ?><?= component('alert', ['variant' => 'danger', 'message' => $erros['_geral']]) ?><?php } ?>
    <form method="post" action="<?= e(site_url('pacotes/formulario')) ?>" class="flex flex-col gap-4" <?= js_module('pacotes/formulario') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="chave" value="<?= e($chave) ?>">
        <section class="grid gap-4 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2">
            <?= component('input', ['name' => 'nome', 'label' => 'Nome do pacote', 'value' => $valor('nome'), 'error' => $erros['nome'] ?? null, 'required' => true, 'placeholder' => 'Ex.: Acompanhamento pós-parto', 'attrs' => ['maxlength' => 150]]) ?>
            <?= component('select', ['name' => 'clientes_id', 'label' => 'Cliente ou responsável', 'options' => array_column($clientes, 'nomeCliente', 'idClientes'), 'selected' => $valor('clientes_id'), 'placeholder' => 'Escolha a cliente', 'required' => true, 'error' => $erros['clientes_id'] ?? null]) ?>
            <?= component('select', ['name' => 'dependente_id', 'label' => 'Bebê ou dependente', 'options' => $dependentesOpcoes, 'selected' => $valor('dependente_id'), 'placeholder' => 'O atendimento é para a própria cliente', 'error' => $erros['dependente_id'] ?? null]) ?>
            <?= component('select', ['name' => 'usuarios_id', 'label' => 'Profissional responsável', 'options' => array_column($profissionais, 'nome', 'idUsuarios'), 'selected' => $valor('usuarios_id', (string) $this->session->userdata('id_admin')), 'required' => true, 'error' => $erros['usuarios_id'] ?? null]) ?>
        </section>
        <section class="rounded-xl border border-border bg-surface p-4">
            <h2 class="text-heading-sm text-text">Composição do pacote</h2>
            <p class="mt-2 text-caption text-muted">Deixe a quantidade em zero nos serviços não incluídos. A duração vem do cadastro de Serviços e pode ser ajustada em cada agendamento.</p>
            <?php foreach ($servicos as $servico) {
                $entrada = is_array($valores['servicos'][$servico->idServicos] ?? null) ? $valores['servicos'][$servico->idServicos] : [];
                ?>
                <div data-pacote-item class="mt-4 grid items-end gap-3 border-t border-border pt-3 sm:grid-cols-3">
                    <p class="text-label-md text-text"><?= e($servico->nome . ' · ' . $servico->duracao_min . ' min por sessão') ?></p>
                    <?= component('input', ['name' => 'servicos[' . (int) $servico->idServicos . '][quantidade]', 'label' => 'Sessões de ' . $servico->nome, 'type' => 'number', 'value' => is_string($entrada['quantidade'] ?? null) ? $entrada['quantidade'] : '0', 'attrs' => ['min' => 0, 'max' => 100, 'step' => 1, 'data-pacote-quantidade' => true]]) ?>
                    <?= component('input', ['name' => 'servicos[' . (int) $servico->idServicos . '][preco]', 'label' => 'Valor por sessão (R$)', 'value' => is_string($entrada['preco'] ?? null) ? $entrada['preco'] : number_format((float) $servico->preco, 2, ',', ''), 'attrs' => ['inputmode' => 'decimal', 'data-pacote-preco' => true]]) ?>
                </div>
            <?php } ?>
            <?php if ($servicos === []) { ?><p class="mt-3 text-body-md text-muted">Cadastre serviços com duração maior que zero antes de criar um pacote.</p><?php } ?>
            <p data-pacote-total aria-live="polite" class="mt-4 text-heading-sm text-text">Total calculado ao salvar.</p>
        </section>
        <div class="flex flex-wrap justify-end gap-2">
            <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('pacotes')]) ?>
            <?= component('button', ['label' => 'Criar pacote e sessões', 'type' => 'submit', 'disabled' => $servicos === []]) ?>
        </div>
    </form>
</div>
