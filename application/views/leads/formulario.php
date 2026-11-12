<?php $valor = static fn ($campo, $padrao = '') => is_string($valores[$campo] ?? null) ? $valores[$campo] : $padrao; ?>
<div class="flex max-w-5xl flex-col gap-4 pt-2 pb-8">
    <h1 class="font-display text-heading-xl text-text"><?= e($lead ? 'Editar contato e orçamento' : 'Novo contato') ?></h1>
    <p class="text-body-md text-muted">O contato pode ser salvo sem orçamento. Inclua os serviços quando tiver os valores definidos.</p>
    <?php if (isset($erros['_geral'])) { ?><?= component('alert', ['variant' => 'danger', 'message' => $erros['_geral']]) ?><?php } ?>
    <form method="post" action="<?= e(site_url('leads/formulario' . ($lead ? '/' . $lead->id : ''))) ?>" class="flex flex-col gap-4" novalidate <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="chave" value="<?= e($chave) ?>">
        <input type="hidden" name="versao" value="<?= e($versao) ?>">
        <section class="grid gap-4 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2">
            <?= component('input', ['name' => 'nome', 'label' => 'Nome da cliente ou responsável', 'value' => $valor('nome'), 'required' => true, 'error' => $erros['nome'] ?? null, 'attrs' => ['maxlength' => 150]]) ?>
            <?= component('input', ['name' => 'telefone', 'label' => 'WhatsApp com DDD', 'type' => 'tel', 'value' => $valor('telefone'), 'required' => true, 'error' => $erros['telefone'] ?? null, 'attrs' => ['maxlength' => 25]]) ?>
            <?= component('input', ['name' => 'bebe', 'label' => 'Nome do bebê (se houver)', 'value' => $valor('bebe'), 'error' => $erros['bebe'] ?? null, 'attrs' => ['maxlength' => 150]]) ?>
            <?= component('select', ['name' => 'origem', 'label' => 'Origem do contato', 'options' => LEADS_ORIGENS, 'selected' => $valor('origem', 'whatsapp'), 'error' => $erros['origem'] ?? null]) ?>
            <?= component('input', ['name' => 'interesse', 'label' => 'Serviço ou necessidade', 'value' => $valor('interesse'), 'required' => true, 'error' => $erros['interesse'] ?? null, 'attrs' => ['maxlength' => 255]]) ?>
            <?= component('input', ['name' => 'retorno', 'label' => 'Retomar contato em (opcional)', 'type' => 'date', 'value' => $valor('retorno'), 'error' => $erros['retorno'] ?? null]) ?>
            <div class="sm:col-span-2"><?= component('textarea', ['name' => 'observacoes', 'label' => 'Observações internas', 'value' => $valor('observacoes'), 'error' => $erros['observacoes'] ?? null, 'attrs' => ['maxlength' => 5000], 'help' => 'Não aparecem no orçamento para a cliente.']) ?></div>
        </section>
        <section class="rounded-xl border border-border bg-surface p-4">
            <h2 class="text-heading-sm text-text">Orçamento</h2>
            <p class="mt-2 text-caption text-muted">Informe a quantidade dos serviços incluídos. Para cobrar deslocamento, use o serviço Taxa de deslocamento do catálogo. O total será calculado ao salvar.</p>
            <?php foreach ($servicos as $servico) {
                $entrada = is_array($valores['servicos'][$servico->idServicos] ?? null) ? $valores['servicos'][$servico->idServicos] : []; ?>
                <div class="mt-4 grid items-end gap-3 border-t border-border pt-3 sm:grid-cols-3">
                    <p class="text-label-md text-text"><?= e($servico->nome) ?></p>
                    <?= component('input', ['name' => 'servicos[' . (int) $servico->idServicos . '][quantidade]', 'label' => 'Quantidade de ' . $servico->nome, 'type' => 'number', 'value' => is_string($entrada['quantidade'] ?? null) ? $entrada['quantidade'] : '0', 'attrs' => ['min' => 0, 'max' => 100, 'step' => 1]]) ?>
                    <?= component('input', ['name' => 'servicos[' . (int) $servico->idServicos . '][preco]', 'label' => 'Valor unitário de ' . $servico->nome . ' (R$)', 'value' => is_string($entrada['preco'] ?? null) ? $entrada['preco'] : number_format((float) $servico->preco, 2, ',', ''), 'attrs' => ['inputmode' => 'decimal']]) ?>
                </div>
            <?php } ?>
            <?php if ($servicos === []) { ?><p class="mt-4 text-body-md text-muted">Cadastre os serviços para compor o orçamento.</p><?php } ?>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <?= component('input', ['name' => 'sinal', 'label' => 'Sinal previsto (R$)', 'value' => $valor('sinal', '0,00'), 'error' => $erros['sinal'] ?? null, 'help' => 'Zero para pagamento integral. Este campo não registra um recebimento.']) ?>
                <?= component('input', ['name' => 'validade', 'label' => 'Validade do orçamento (opcional)', 'type' => 'date', 'value' => $valor('validade'), 'error' => $erros['validade'] ?? null]) ?>
                <div class="sm:col-span-2"><?= component('textarea', ['name' => 'condicoes', 'label' => 'Condições e informações para a cliente', 'value' => $valor('condicoes'), 'error' => $erros['condicoes'] ?? null, 'attrs' => ['maxlength' => 3000], 'help' => 'Ex.: formas de pagamento, endereço e regras de deslocamento.']) ?></div>
            </div>
        </section>
        <div class="flex flex-wrap justify-end gap-3">
            <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('leads' . ($lead ? '/visualizar/' . $lead->id : ''))]) ?>
            <?= component('button', ['label' => 'Salvar contato e orçamento', 'type' => 'submit']) ?>
        </div>
    </form>
</div>
