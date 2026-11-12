<?php
/**
 * OS do cliente (#2848): substitui as telas visualizar_os e detalhes_os da
 * v4. Só leitura: status, valores em kpi-card, dados e textos do atendimento,
 * produtos e serviços (os/partes/*, sem as ações do painel), anexos e o PIX.
 *
 * @var object      $os
 * @var array       $totais
 * @var list<object> $produtos
 * @var list<object> $servicos
 * @var list<object> $anexos
 * @var string|null $garantia_ate
 * @var array|null  $pix
 */
$idOs = (int) $os->idOs;
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$campos = static fn (array $itens) => array_filter($itens, static fn ($valor) => $valor !== null && $valor !== '');

$dados = $campos([
    'Quem recebe o atendimento' => $os->beneficiario_nome ?? $os->nomeCliente ?? null,
    'Entrada' => dataBr($os->dataInicial),
    'Conclusão prevista' => dataBr($os->dataFinal),
    'Profissional responsável' => (string) $os->nome,
    'Garantia' => (int) $os->garantia > 0
        ? (int) $os->garantia . ' dias' . ($garantia_ate ? ($garantia_ate >= date('Y-m-d') ? ', até ' : ', venceu em ') . dataBr($garantia_ate) : ', a partir da entrega')
        : null,
]);

$textos = $campos([
    'Serviço solicitado' => trim(strip_tags((string) $os->descricaoProduto)) !== '' ? osTextoExibicao($os->descricaoProduto) : null,
    'Relato da cliente' => trim(strip_tags((string) $os->defeito)) !== '' ? osTextoExibicao($os->defeito) : null,
    'Observações' => trim(strip_tags((string) $os->observacoes)) !== '' ? osTextoExibicao($os->observacoes) : null,
    'Registro do atendimento' => trim(strip_tags((string) $os->laudoTecnico)) !== '' ? osTextoExibicao($os->laudoTecnico) : null,
]);
?>
<div class="flex flex-col gap-4">
    <div>
        <?= component('button', ['label' => 'Atendimentos', 'icon' => 'chevron-left', 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('mine/os'), 'class' => '-ml-3']) ?>
    </div>

    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-display text-heading-xl text-text"><?= e('Atendimento #' . $idOs) ?></h1>
                <?= component('pill-status', osStatusPill($os->status)) ?>
            </div>
            <p class="text-caption text-muted"><?= e('Aberta em ' . dataBr($os->dataInicial)) ?></p>
        </div>
        <?= component('button', ['label' => 'Imprimir', 'icon' => 'printer', 'variant' => 'outline', 'href' => site_url('mine/imprimirOs/' . $idOs), 'attrs' => ['target' => '_blank', 'rel' => 'noopener']]) ?>
    </header>

    <?php $this->load->view('agenda/horarios', ['agendamentos' => $os->status === 'Cancelado' ? [] : ($os->agendamentos ?? []), 'editar_agenda' => false]); ?>
    <?php if (! empty($os->notas_fiscais) || ! empty($os->nf_solicitada)) { ?>
        <section class="<?= e($caixa) ?>" aria-label="Nota fiscal">
            <h2 class="text-heading-sm text-text">Nota fiscal</h2>
            <?php if (empty($os->notas_fiscais)) { ?><p class="mt-2 text-body-md text-muted">Sua solicitação de nota fiscal está registrada. O documento ainda não foi anexado.</p><?php } ?>
            <?php foreach (($os->notas_fiscais ?? []) as $nota) { ?>
                <p class="mt-3"><?= component('link', ['label' => $nota->numero ? 'NF ' . $nota->numero . ' · ' . $nota->nome : $nota->nome, 'href' => site_url('mine/notaFiscal/' . (int) $nota->id)]) ?></p>
            <?php } ?>
        </section>
    <?php } ?>
    <?php if (isset($os->pacote_progresso)) { ?>
        <section class="<?= e($caixa) ?>" aria-label="Sessões do pacote">
            <h2 class="text-heading-sm text-text">Sessões do pacote</h2>
            <p class="mt-2 text-body-md text-text"><?= e((int) $os->pacote_progresso->realizadas . ' realizadas · ' . ((int) $os->pacote_progresso->total - (int) $os->pacote_progresso->realizadas) . ' restantes · ' . (int) $os->pacote_progresso->total . ' contratadas') ?></p>
        </section>
    <?php } ?>
    <?php // Uma coluna no celular: em duas, o valor em reais não cabe na linha do kpi-card.?>
    <div class="grid gap-4 sm:grid-cols-3">
        <?= component('kpi-card', ['label' => 'Produtos', 'value' => dinheiro($totais['produtos']), 'icon' => 'package']) ?>
        <?= component('kpi-card', ['label' => 'Serviços', 'value' => dinheiro($totais['servicos']), 'icon' => 'wrench']) ?>
        <?= component('kpi-card', ['label' => 'Total', 'value' => dinheiro($totais['total']), 'icon' => 'circle-dollar-sign', 'caption' => $totais['desconto'] > 0 ? 'Com ' . dinheiro($totais['desconto']) . ' de desconto' : null]) ?>
    </div>

    <?php if (isset($os->recebido_total)) { ?>
        <section class="<?= e($caixa) ?>" aria-label="Pagamentos do atendimento">
            <h2 class="text-heading-sm text-text">Pagamentos</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <p class="text-body-md text-text"><?= e('Recebido: ' . dinheiro($os->recebido_total)) ?></p>
                <p class="text-body-md text-text"><?= e('Saldo a receber: ' . dinheiro(max(0, $totais['total'] - $os->recebido_total))) ?></p>
            </div>
        </section>
    <?php } ?>
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="<?= e($caixa) ?>" aria-labelledby="secao-dados">
            <h2 id="secao-dados" class="text-heading-sm text-text">Dados do atendimento</h2>
            <dl class="mt-3 flex flex-col gap-3">
                <?php foreach ($dados as $rotulo => $valor) { ?>
                    <div>
                        <dt class="text-caption text-muted"><?= e($rotulo) ?></dt>
                        <dd class="text-body-md [overflow-wrap:anywhere] text-text"><?= e($valor) ?></dd>
                    </div>
                <?php } ?>
            </dl>
        </section>
        <section class="<?= e($caixa) ?> lg:col-span-2" aria-labelledby="secao-equipamento">
            <h2 id="secao-equipamento" class="text-heading-sm text-text">Serviço e informações do atendimento</h2>
            <?php if ($textos === []) { ?>
                <p class="mt-3 text-caption text-muted">A assistência ainda não preencheu os detalhes desto atendimento.</p>
            <?php } else { ?>
                <dl class="mt-3 grid gap-4 sm:grid-cols-2">
                    <?php foreach ($textos as $rotulo => $valor) { ?>
                        <div>
                            <dt class="text-caption text-muted"><?= e($rotulo) ?></dt>
                            <dd class="text-body-md [overflow-wrap:anywhere] text-text"><?= componenteConteudo($valor) ?></dd>
                        </div>
                    <?php } ?>
                </dl>
            <?php } ?>
        </section>
    </div>

    <section class="flex flex-col gap-3" aria-labelledby="secao-produtos">
        <h2 id="secao-produtos" class="text-heading-sm text-text">Produtos</h2>
        <?php $this->load->view('os/partes/produtos'); ?>
    </section>

    <section class="flex flex-col gap-3" aria-labelledby="secao-servicos">
        <h2 id="secao-servicos" class="text-heading-sm text-text">Serviços</h2>
        <?php $this->load->view('os/partes/servicos'); ?>
    </section>

    <?php $this->load->view('conecte/partes/anexos'); ?>

    <?php $this->load->view('conecte/partes/pix'); ?>
</div>
