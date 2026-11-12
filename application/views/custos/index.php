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
        <div><h1 class="font-display text-heading-xl text-text"><?= e('Custos e resultado · Atendimento #' . $id) ?></h1><p class="mt-2 text-caption text-muted">Somente gestão interna. Insumos, custos e resultado não aparecem para o cliente.</p></div>
        <?= component('button', ['label' => 'Voltar ao atendimento', 'variant' => 'outline', 'href' => site_url('os/visualizar/' . $id)]) ?>
    </header>
    <?php if ($erro) { ?><?= component('alert', ['variant' => 'danger', 'message' => $erro]) ?><?php } ?>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach (['cobrado' => 'Total cobrado', 'recebido' => 'Total recebido', 'saldo' => 'Saldo a receber', 'custo' => 'Custos diretos registrados', 'resultado' => 'Resultado previsto', 'recebido_menos_custos' => 'Recebido menos custos diretos'] as $campo => $rotulo) { ?>
            <?= component('kpi-card', ['label' => $rotulo, 'value' => dinheiro($resultado[$campo] / 100)]) ?>
        <?php } ?>
    </div>
    <p class="text-caption text-muted">Resultado previsto = total cobrado menos custos diretos registrados. Não inclui despesas gerais, impostos ou trabalho da profissional, salvo se lançados aqui. Não é lucro líquido nem saldo de caixa. Os custos não geram despesas financeiras automaticamente: registre o pagamento das compras em Despesas, sem descontá-lo novamente nesta tela.</p>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6">
        <h2 class="text-heading-sm text-text">Insumos consumidos</h2>
        <p class="mt-2 text-caption text-muted"><?= e('Custo dos insumos: ' . dinheiro($resultado['insumos'] / 100)) ?>. O consumo baixa o estoque e usa o custo do lote comprado. Confira o consumo real, mesmo quando usar um padrão.</p>
        <?php if ($editar_insumos) { ?>
            <form method="post" action="<?= e(site_url('custos/salvar/' . $id)) ?>" class="mt-4">
                <input type="hidden" name="<?= e($csrf) ?>" value="<?= e($token) ?>"><input type="hidden" name="acao" value="padroes">
                <?= component('button', ['label' => 'Aplicar insumos padrão dos serviços', 'variant' => 'outline', 'type' => 'submit']) ?>
            </form>
            <p class="mt-2 text-caption text-muted">Aplica uma vez por item de serviço, multiplicando pela quantidade contratada. Prioriza lotes que vencem primeiro. Repetir o botão não duplica consumos. Se já lançou o consumo manualmente, confira e estorne a duplicidade. Após aplicar, ajustes são feitos por consumo adicional ou estorno.</p>
            <form method="post" action="<?= e(site_url('custos/salvar/' . $id)) ?>" class="mt-4 grid gap-4 sm:grid-cols-3" novalidate <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($csrf) ?>" value="<?= e($token) ?>"><input type="hidden" name="acao" value="consumo"><input type="hidden" name="chave" value="<?= e($chave) ?>">
                <?= component('select', ['name' => 'lote_id', 'label' => 'Insumo e lote', 'options' => $opcoes, 'placeholder' => 'Escolha', 'required' => true, 'selected' => $valor('lote_id')]) ?>
                <?= component('input', ['name' => 'quantidade', 'label' => 'Quantidade consumida', 'value' => $valor('quantidade'), 'required' => true, 'help' => 'Na unidade do lote, com até três casas decimais.']) ?>
                <div class="sm:pt-7"><?= component('button', ['label' => 'Registrar consumo', 'variant' => 'outline', 'type' => 'submit']) ?></div>
            </form>
        <?php } ?>
        <?php if (! $consumos) { ?><?= component('empty-state', ['title' => 'Nenhum consumo registrado', 'message' => 'O custo fica incompleto até registrar os materiais usados.']) ?><?php } ?>
        <?php foreach ($consumos as $m) { ?>
            <article class="mt-4 flex flex-wrap justify-between gap-3 border-t border-border pt-3">
                <div><p class="text-body-md text-text"><?= e($m->nome . ' · ' . number_format((float) $m->quantidade, 3, ',', '') . ' ' . $m->unidade . ' · lote ' . $m->lote . ' · ' . dinheiro($m->custo)) ?></p><p class="mt-1 text-caption text-muted"><?= e($m->nota . ' · ' . $m->criado_em) ?></p></div>
                <?php if ($m->estornado_em) { ?><?= component('pill-status', ['label' => 'Estornado', 'variant' => 'neutral']) ?>
                <?php } elseif ($editar_insumos) { ?>
                    <form method="post" action="<?= e(site_url('custos/salvar/' . $id)) ?>"><input type="hidden" name="<?= e($csrf) ?>" value="<?= e($token) ?>"><input type="hidden" name="acao" value="estornar_consumo"><input type="hidden" name="registro" value="<?= e($m->id) ?>"><?= component('button', ['label' => 'Estornar consumo #' . $m->id, 'variant' => 'ghost', 'type' => 'submit']) ?></form>
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
                <div class="lg:pt-7"><?= component('button', ['label' => 'Registrar custo', 'type' => 'submit']) ?></div>
            </form>
        <?php } ?>
        <?php if (! $custos) { ?><p class="mt-4 text-body-md text-muted">Nenhum outro custo registrado.</p><?php } ?>
        <?php foreach ($custos as $custo) { ?>
            <article class="mt-4 flex flex-wrap justify-between gap-3 border-t border-border pt-3">
                <div><p class="text-body-md text-text"><?= e((CUSTOS_TIPOS[$custo->tipo] ?? 'Custo') . ' · ' . $custo->descricao . ' · ' . dinheiro($custo->valor)) ?></p><p class="mt-1 text-caption text-muted"><?= e($custo->criado_em) ?></p></div>
                <?php if ($custo->estornado_em) { ?><?= component('pill-status', ['label' => 'Estornado', 'variant' => 'neutral']) ?>
                <?php } elseif ($editar) { ?>
                    <form method="post" action="<?= e(site_url('custos/salvar/' . $id)) ?>"><input type="hidden" name="<?= e($csrf) ?>" value="<?= e($token) ?>"><input type="hidden" name="acao" value="estornar_custo"><input type="hidden" name="registro" value="<?= e($custo->id) ?>"><?= component('button', ['label' => 'Estornar custo #' . $custo->id, 'variant' => 'ghost', 'type' => 'submit']) ?></form>
                <?php } ?>
            </article>
        <?php } ?>
    </section>
</div>
