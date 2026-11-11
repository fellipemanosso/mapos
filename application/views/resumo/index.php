<?php
$meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$titulo = $meses[(int) $mes->format('n')] . ' de ' . $mes->format('Y');
$url = static fn ($data) => site_url('resumo') . '?mes=' . $data->format('Y-m');
$filtro = ['periodo' => 'personalizado', 'de' => $mes->format('Y-m-d'), 'ate' => $mes->modify('last day of this month')->format('Y-m-d')];
$lista = static fn ($tipo) => site_url('financeiro/lancamentos') . '?' . http_build_query($filtro + ['tipo' => $tipo, 'status' => 'pendente']);
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$categoriasNomes = DESPESAS_CATEGORIAS + ['sem_categoria' => 'Sem categoria / lançamentos anteriores'];
$acumulado = 0;
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div><h1 class="font-display text-heading-xl text-text">Resumo financeiro mensal</h1><p class="text-body-md text-muted"><?= e($titulo) ?></p></div>
        <?= component('button', ['label' => 'Lançamentos', 'variant' => 'outline', 'href' => site_url('financeiro/lancamentos')]) ?>
    </header>
    <div class="flex flex-wrap items-end gap-2">
        <?= component('button', ['label' => 'Mês anterior', 'variant' => 'ghost', 'href' => $url($mes->modify('-1 month'))]) ?>
        <?= component('button', ['label' => 'Mês atual', 'variant' => 'outline', 'href' => site_url('resumo')]) ?>
        <?= component('button', ['label' => 'Próximo mês', 'variant' => 'ghost', 'href' => $url($mes->modify('+1 month'))]) ?>
        <form method="get" action="<?= e(site_url('resumo')) ?>" class="flex items-end gap-2">
            <?= component('input', ['name' => 'mes', 'label' => 'Escolher mês e ano', 'type' => 'month', 'value' => $mes->format('Y-m'), 'required' => true]) ?>
            <?= component('button', ['label' => 'Ver mês', 'variant' => 'outline', 'type' => 'submit']) ?>
        </form>
    </div>
    <section aria-label="Movimentação realizada" class="grid gap-4 sm:grid-cols-3">
        <?= component('kpi-card', ['label' => 'Recebido no mês', 'value' => dinheiro($totais['recebido']), 'caption' => 'Pela data em que o dinheiro foi recebido']) ?>
        <?= component('kpi-card', ['label' => 'Despesas pagas', 'value' => dinheiro($totais['pago']), 'caption' => 'Pela data em que o pagamento foi registrado']) ?>
        <?= component('kpi-card', ['label' => 'Resultado do mês', 'value' => dinheiro($totais['resultado']), 'caption' => 'Recebido menos despesas pagas']) ?>
    </section>
    <p class="text-caption text-muted"><?= e('Resultado do mês anterior: ' . dinheiro($anterior['resultado']) . ' · Variação: ' . dinheiro($totais['resultado'] - $anterior['resultado'])) ?></p>
    <section class="<?= e($caixa) ?>">
        <h2 class="text-heading-sm text-text">Pendências com vencimento neste mês</h2>
        <p class="mt-2 text-caption text-muted">Valores ainda em aberto. Não entram no resultado realizado acima.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <?= component('kpi-card', ['label' => 'A receber', 'value' => dinheiro($totais['a_receber']), 'href' => $lista('receita')]) ?>
            <?= component('kpi-card', ['label' => 'A pagar', 'value' => dinheiro($totais['a_pagar']), 'href' => $lista('despesa')]) ?>
        </div>
        <details class="mt-4"><summary class="cursor-pointer text-label-md text-text">Ver próximas pendências (até 10)</summary>
        <div class="mt-3"><?= component('data-table', ['caption' => 'Primeiras dez pendências do mês', 'rows' => $pendencias, 'columns' => [
            ['label' => 'Vencimento', 'render' => static fn ($l) => dataBr($l->data_vencimento)],
            ['label' => 'Tipo', 'render' => static fn ($l) => $l->tipo === 'receita' ? 'A receber' : 'A pagar'],
            ['label' => 'Descrição', 'render' => static fn ($l) => $l->descricao],
            ['label' => 'Valor', 'nowrap' => true, 'render' => static fn ($l) => dinheiro($l->liquido)],
        ], 'empty' => component('empty-state', ['title' => 'Nenhuma pendência neste mês', 'message' => 'Não há valores em aberto com vencimento no período.', 'icon' => 'banknote'])]) ?></div>
        </details>
    </section>
    <section class="<?= e($caixa) ?>">
        <h2 class="text-heading-sm text-text">Despesas pagas por categoria</h2>
        <div class="mt-4"><?= component('data-table', ['caption' => 'Categorias de despesas pagas no mês', 'rows' => $categorias, 'columns' => [
            ['label' => 'Categoria', 'render' => static fn ($l) => $categoriasNomes[$l->categoria] ?? 'Outras despesas'],
            ['label' => 'Total pago', 'nowrap' => true, 'render' => static fn ($l) => dinheiro($l->valor)],
        ], 'empty' => component('empty-state', ['title' => 'Nenhuma despesa paga', 'message' => 'As categorias aparecem depois de registrar os pagamentos.', 'icon' => 'banknote'])]) ?></div>
    </section>
    <section class="<?= e($caixa) ?>">
        <h2 class="text-heading-sm text-text">Movimento diário</h2>
        <p class="mt-2 text-caption text-muted">O acumulado começa em zero no primeiro dia do mês e representa apenas o resultado deste período.</p>
        <div class="mt-4"><?= component('data-table', ['caption' => 'Recebimentos e pagamentos por dia', 'rows' => $movimento, 'columns' => [
            ['label' => 'Dia', 'render' => static fn ($l) => dataBr($l->dia)],
            ['label' => 'Recebido', 'nowrap' => true, 'render' => static fn ($l) => dinheiro($l->receitas)],
            ['label' => 'Pago', 'nowrap' => true, 'render' => static fn ($l) => dinheiro($l->despesas)],
            ['label' => 'Resultado acumulado', 'nowrap' => true, 'render' => static function ($l) use (&$acumulado) {
                $acumulado = round($acumulado + (float) $l->receitas - (float) $l->despesas, 2);
                return dinheiro($acumulado);
            }],
        ], 'empty' => component('empty-state', ['title' => 'Sem movimentação realizada', 'message' => 'Os recebimentos e pagamentos registrados no período aparecem aqui.', 'icon' => 'banknote'])]) ?></div>
    </section>
</div>
