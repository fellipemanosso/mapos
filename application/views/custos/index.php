<?php
$id = (int) $os->idOs;
$csrf = $this->security->get_csrf_token_name();
$token = $this->security->get_csrf_hash();
$valor = static fn ($campo, $padrao = '') => is_string($valores[$campo] ?? null) ? $valores[$campo] : $padrao;
$opcoes = [];
foreach ($lotes as $lote) {
    $opcoes[$lote->id] = $lote->nome . ' · ' . $lote->unidade . ' · lote ' . $lote->lote . ' · saldo ' . number_format((float) $lote->saldo, 3, ',', '');
}
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap justify-between gap-3">
        <div><h1 class="font-display text-heading-xl text-text"><?= e('Custos e resultado · Atendimento #' . $id) ?></h1><p class="mt-2 text-body-md text-text"><?= e(($os->beneficiario_nome ?? $os->nomeCliente ?? '') . ' · ' . dataBr($os->dataInicial)) ?></p><p class="mt-2 text-caption text-muted">Somente gestão interna. Insumos, custos e resultado não aparecem para o cliente.</p></div>
        <?= component('button', ['label' => 'Voltar ao atendimento', 'variant' => 'outline', 'href' => site_url('os/visualizar/' . $id)]) ?>
    </header>
    <?php if ($erro) { ?><?= component('alert', ['variant' => 'danger', 'message' => $erro]) ?><?php } ?>
    <?= component('alert', ['variant' => $previa['conferido'] && ! $previa['sem_custo'] ? 'success' : 'warning', 'message' => $previa['conferido'] ? ($previa['sem_custo'] ? 'Materiais conferidos, mas há consumo com custo zero. Confira os valores das compras antes de considerar o resultado.' : 'Materiais conferidos. Confira também os outros custos diretos.') : 'Resultado parcial: os materiais ainda precisam ser conferidos.']) ?>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6">
        <h2 class="text-heading-sm text-text">Insumos consumidos</h2>
        <p class="mt-2 text-caption text-muted"><?= e('Custo dos insumos: ' . dinheiro($resultado['insumos'] / 100)) ?>. O consumo baixa o estoque e usa o custo do lote comprado. Confira o consumo real, mesmo quando usar um padrão.</p>
        <?php if ($editar_insumos) { ?>
            <details class="mt-4" <?= ! $previa['conferido'] ? 'open' : '' ?>>
                <summary class="min-h-11 cursor-pointer py-2.5 text-label-md text-text">Conferir materiais antes de baixar</summary>
                <p class="mt-3 text-body-md text-muted">Informe o total realmente usado neste atendimento. O que já foi registrado é descontado: somente a diferença será baixada, usando primeiro os lotes que vencem antes. Quantidade zero significa que o material não foi usado.</p>
                <ul class="mt-3 text-caption text-muted">
                    <?php foreach ($previa['servicos'] as $servico) { ?><li><?= e($servico['nome'] . ' · ' . $servico['quantidade'] . ($servico['composicao'] ? '' : ' · sem composição: confira se houve material e registre manualmente')) ?></li><?php } ?>
                </ul>
                <?php if ($previa['materiais']) { ?>
                <form method="post" action="<?= e(site_url('custos/salvar/' . $id)) ?>" class="mt-4 flex flex-col gap-4" novalidate <?= js_module('formulario/padrao') ?>>
                    <input type="hidden" name="<?= e($csrf) ?>" value="<?= e($token) ?>"><input type="hidden" name="acao" value="padroes"><input type="hidden" name="assinatura" value="<?= e($previa['assinatura']) ?>">
                    <?php foreach ($previa['materiais'] as $material) {
                        $total = $previa['conferido'] ? $material['registrado'] : max($material['previsto'], $material['registrado']);
                        $falta = max(0, $total - $material['registrado'] - $material['saldo']); ?>
                        <div class="grid gap-3 border-t border-border pt-3 sm:grid-cols-2">
                            <div><p class="text-label-md text-text"><?= e($material['nome'] . ' · ' . $material['unidade']) ?></p><p class="mt-1 text-caption text-muted"><?= e('Padrão: ' . str_replace('.', ',', insumoDecimal($material['previsto'])) . ' · Já registrado: ' . str_replace('.', ',', insumoDecimal($material['registrado'])) . ' · Disponível: ' . str_replace('.', ',', insumoDecimal($material['saldo']))) ?></p>
                            <?php if ($falta > 0) { ?><p class="mt-2 text-caption text-danger-ink"><?= e('Faltam ' . str_replace('.', ',', insumoDecimal($falta)) . ' ' . $material['unidade'] . ' para o total sugerido.') ?></p><?php } ?>
                            <?= component('button', ['label' => 'Registrar compra ou saldo inicial', 'variant' => 'link', 'href' => site_url('insumos/visualizar/' . $material['id']) . '#movimentacao']) ?></div>
                            <?= component('input', ['name' => 'materiais[' . $material['id'] . ']', 'label' => 'Total realmente usado · ' . $material['nome'], 'value' => is_string($valores['materiais'][$material['id']] ?? null) ? $valores['materiais'][$material['id']] : str_replace('.', ',', insumoDecimal($total)), 'required' => true, 'attrs' => ['inputmode' => 'decimal'], 'help' => 'Inclua o consumo já registrado. Para reduzir esse consumo, estorne primeiro.']) ?>
                        </div>
                    <?php } ?>
                    <p class="text-caption text-muted">Se faltar estoque, a confirmação inteira será cancelada, sem baixas parciais. Serviços sem composição precisam de conferência manual.</p>
                    <div><?= component('button', ['label' => 'Confirmar e baixar diferença', 'type' => 'submit']) ?></div>
                </form>
                <?php } else { ?><p class="mt-3 text-body-md text-muted">Sem composição disponível. Configure os insumos nos serviços ou registre o consumo manualmente abaixo.</p><?php } ?>
            </details>
            <?php if (! $opcoes) { ?><div class="mt-4"><?= component('alert', ['variant' => 'warning', 'message' => 'Nenhum lote com saldo disponível e dentro da validade. Registre uma compra ou saldo inicial para consumir materiais.']) ?><?= component('button', ['label' => 'Ir aos insumos', 'variant' => 'outline', 'href' => site_url('insumos')]) ?></div><?php } ?>
            <details class="mt-4" <?= ($valores['acao'] ?? '') === 'consumo' ? 'open' : '' ?>><summary class="min-h-11 cursor-pointer py-2.5 text-label-md text-text">Registrar consumo adicional manualmente</summary>
            <form method="post" action="<?= e(site_url('custos/salvar/' . $id)) ?>" class="mt-4 grid gap-4 sm:grid-cols-3" novalidate <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($csrf) ?>" value="<?= e($token) ?>"><input type="hidden" name="acao" value="consumo"><input type="hidden" name="chave" value="<?= e($chave) ?>">
                <?= component('select', ['name' => 'lote_id', 'label' => 'Insumo e lote', 'options' => $opcoes, 'placeholder' => 'Escolha', 'required' => true, 'selected' => $valor('lote_id')]) ?>
                <?= component('input', ['name' => 'quantidade', 'label' => 'Quantidade consumida', 'value' => $valor('quantidade'), 'attrs' => ['inputmode' => 'decimal'], 'required' => true, 'help' => 'Na unidade do lote, com até três casas decimais.']) ?>
                <div class="sm:pt-7"><?= component('button', ['label' => 'Registrar consumo', 'variant' => 'outline', 'type' => 'submit']) ?></div>
            </form></details>
        <?php } ?>
        <?php if (! $consumos) { ?><?= component('empty-state', ['title' => 'Nenhum consumo registrado', 'message' => 'O custo fica incompleto até registrar os materiais usados.']) ?><?php } ?>
        <?php foreach ($consumos as $m) { ?>
            <article class="mt-4 flex flex-wrap justify-between gap-3 border-t border-border pt-3">
                <div><p class="text-body-md text-text"><?= e($m->nome . ' · ' . number_format((float) $m->quantidade, 3, ',', '') . ' ' . $m->unidade . ' · lote ' . $m->lote . ' · ' . dinheiro($m->custo)) ?></p><p class="mt-1 text-caption text-muted"><?= e($m->nota . ' · ' . date('d/m/Y H:i', strtotime($m->criado_em))) ?></p></div>
                <?php if ($m->estornado_em) { ?><?= component('pill-status', ['label' => 'Estornado', 'variant' => 'neutral']) ?>
                <?php } elseif ($editar_insumos) { ?>
                    <form method="post" action="<?= e(site_url('custos/salvar/' . $id)) ?>"><input type="hidden" name="<?= e($csrf) ?>" value="<?= e($token) ?>"><input type="hidden" name="acao" value="estornar_consumo"><input type="hidden" name="registro" value="<?= e($m->id) ?>"><?= component('button', ['label' => 'Estornar ' . $m->nome, 'variant' => 'ghost', 'type' => 'submit']) ?></form>
                <?php } ?>
            </article>
        <?php } ?>
    </section>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6">
        <h2 class="text-heading-sm text-text">Outros custos diretos</h2>
        <p class="mt-2 text-caption text-muted">Registre o combustível/deslocamento real, taxas de cartão e o custo de compra dos produtos vendidos. O custo de um produto vendido deve ser informado aqui; seu preço de venda continua na aba Produtos. A taxa de deslocamento cobrada do cliente deve ser adicionada como serviço no atendimento.</p>
        <?php if ($editar) { ?>
            <form method="post" action="<?= e(site_url('custos/salvar/' . $id)) ?>" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" novalidate <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($csrf) ?>" value="<?= e($token) ?>"><input type="hidden" name="acao" value="custo"><input type="hidden" name="chave" value="<?= e($chave) ?>">
                <?= component('select', ['name' => 'tipo', 'label' => 'Tipo de custo', 'options' => CUSTOS_TIPOS, 'selected' => $valor('tipo', 'deslocamento')]) ?>
                <?= component('input', ['name' => 'descricao', 'label' => 'Descrição interna', 'required' => true, 'value' => $valor('descricao'), 'attrs' => ['maxlength' => 150]]) ?>
                <?= component('input', ['name' => 'valor', 'label' => 'Custo total (R$)', 'required' => true, 'value' => $valor('valor'), 'help' => 'Exemplo: 12,50.']) ?>
                <div class="lg:pt-7"><?= component('button', ['label' => 'Registrar custo', 'variant' => 'outline', 'type' => 'submit']) ?></div>
            </form>
        <?php } ?>
        <?php if (! $custos) { ?><p class="mt-4 text-body-md text-muted">Nenhum outro custo registrado.</p><?php } ?>
        <?php foreach ($custos as $custo) { ?>
            <article class="mt-4 flex flex-wrap justify-between gap-3 border-t border-border pt-3">
                <div><p class="text-body-md text-text"><?= e((CUSTOS_TIPOS[$custo->tipo] ?? 'Custo') . ' · ' . $custo->descricao . ' · ' . dinheiro($custo->valor)) ?></p><p class="mt-1 text-caption text-muted"><?= e(date('d/m/Y H:i', strtotime($custo->criado_em))) ?></p></div>
                <?php if ($custo->estornado_em) { ?><?= component('pill-status', ['label' => 'Estornado', 'variant' => 'neutral']) ?>
                <?php } elseif ($editar) { ?>
                    <form method="post" action="<?= e(site_url('custos/salvar/' . $id)) ?>"><input type="hidden" name="<?= e($csrf) ?>" value="<?= e($token) ?>"><input type="hidden" name="acao" value="estornar_custo"><input type="hidden" name="registro" value="<?= e($custo->id) ?>"><?= component('button', ['label' => 'Estornar custo #' . $custo->id, 'variant' => 'ghost', 'type' => 'submit']) ?></form>
                <?php } ?>
            </article>
        <?php } ?>
    </section>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6"><h2 class="text-heading-sm text-text">Resultado com os custos registrados</h2>
        <dl class="mt-3 grid grid-cols-2 gap-4"><div><dt class="text-caption text-muted">Custos diretos</dt><dd class="text-heading-sm text-text"><?= e(dinheiro($resultado['custo'] / 100)) ?></dd></div><div><dt class="text-caption text-muted">Resultado previsto · parcial até revisar todos os custos</dt><dd class="text-heading-sm text-text"><?= e(dinheiro($resultado['resultado'] / 100)) ?></dd></div></dl>
        <details class="mt-4"><summary class="min-h-11 cursor-pointer py-2.5 text-label-md text-text">Ver cobrança e recebimentos</summary>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach (['cobrado' => 'Total cobrado', 'recebido' => 'Total recebido', 'saldo' => 'Saldo a receber', 'recebido_menos_custos' => 'Recebido menos custos diretos'] as $campo => $rotulo) { ?>
            <?= component('kpi-card', ['label' => $rotulo, 'value' => dinheiro($resultado[$campo] / 100)]) ?>
        <?php } ?>
    </div>
    <p class="text-caption text-muted">Resultado previsto = total cobrado menos custos diretos registrados. Não inclui despesas gerais, impostos ou trabalho da profissional, salvo se lançados aqui. Não é lucro líquido nem saldo de caixa. Os custos não geram despesas financeiras automaticamente: registre o pagamento das compras em Despesas, sem descontá-lo novamente nesta tela.</p>
        </details></section>
</div>
