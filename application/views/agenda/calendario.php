<?php
$meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$titulo = $meses[(int) $selecionado->format('n')] . ' de ' . $selecionado->format('Y');
$hoje = date('Y-m-d');
?>
<section class="rounded-xl border border-border bg-surface" aria-label="Calendário mensal">
    <header class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
        <div class="flex items-center gap-2">
            <?= component('button', ['label' => 'Mês anterior', 'icon' => 'chevron-left', 'icon_only' => true, 'variant' => 'ghost', 'href' => $url($selecionado->modify('first day of this month')->modify('-1 month'), 'calendario')]) ?>
        <h2 class="text-heading-md font-display text-text"><?= e($titulo) ?></h2>
            <?= component('button', ['label' => 'Próximo mês', 'icon' => 'chevron-right', 'icon_only' => true, 'variant' => 'ghost', 'href' => $url($selecionado->modify('first day of this month')->modify('+1 month'), 'calendario')]) ?>
            <?= component('button', ['label' => 'Hoje', 'variant' => 'outline', 'size' => 'sm', 'href' => $url(new DateTimeImmutable('today'), 'calendario')]) ?>
        </div>
        <form method="get" action="<?= e(site_url('agenda')) ?>" class="flex items-end gap-2">
            <input type="hidden" name="modo" value="calendario">
            <?= component('input', ['name' => 'mes', 'label' => 'Ir para o mês', 'type' => 'month', 'value' => $selecionado->format('Y-m'), 'required' => true]) ?>
            <?= component('button', ['label' => 'Ir', 'type' => 'submit', 'variant' => 'outline']) ?>
        </form>
    </header>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px] table-fixed border-collapse">
            <caption class="sr-only"><?= e('Reservas de ' . $titulo) ?></caption>
            <thead>
                <tr>
                    <?php foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $nome) { ?>
                        <th scope="col" class="border-b border-t border-border bg-surface-subtle px-2 py-1 text-left text-caption text-muted"><?= e($nome) ?></th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($semana = $inicio; $semana < $fim; $semana = $semana->modify('+7 days')) { ?>
                    <tr>
                        <?php for ($coluna = 0; $coluna < 7; $coluna++) {
                            $data = $semana->modify('+' . $coluna . ' days');
                            $dataFim = $data->modify('+1 day')->format('Y-m-d H:i:s');
                            $dataInicio = $data->format('Y-m-d H:i:s');
                            $reservas = array_values(array_filter($eventos, static fn ($e) => $e->ocupado_inicio < $dataFim && $e->ocupado_fim > $dataInicio));
                            $foraDoMes = $data->format('Y-m') !== $selecionado->format('Y-m');
                            $ehHoje = $data->format('Y-m-d') === $hoje;
                            ?>
                            <td class="border-b border-r border-border align-top <?= e($foraDoMes ? 'bg-surface-subtle' : 'bg-surface') ?>">
                                <div class="flex h-24 flex-col gap-0.5 overflow-hidden p-1">
                                    <a href="<?= e($url($data, 'dia')) ?>" aria-label="<?= e('Ver agenda de ' . $data->format('d/m/Y')) ?>" class="flex size-5 shrink-0 items-center justify-center self-start rounded-full text-label-md focus-visible:outline-3 focus-visible:outline-ring <?= e($ehHoje ? 'bg-primary-tint font-semibold text-text' : ($foraDoMes ? 'text-muted' : 'text-text')) ?>">
                                        <?= e($data->format('j')) ?>
                                    </a>
                                    <?php foreach (array_slice($reservas, 0, 2) as $reserva) {
                                        $bloqueio = $reserva->tipo === 'bloqueio';
                                        $destino = $pode_editar ? site_url('agenda/formulario/' . (int) $reserva->id) : ($reserva->os_id ? site_url('os/visualizar/' . (int) $reserva->os_id) : $url($data, 'dia'));
                                        $pessoa = $reserva->beneficiario ?: $reserva->nomeCliente;
                                        ?>
                                        <a href="<?= e($destino) ?>" class="block shrink-0 truncate rounded-sm px-1 text-caption focus-visible:outline-3 focus-visible:outline-ring <?= e($bloqueio ? 'bg-neutral-soft text-neutral-ink' : 'bg-info-soft text-info-ink') ?>" title="<?= e($reserva->titulo . ($pessoa ? ' · ' . $pessoa : '') . ' · Reservado ' . date('d/m H:i', strtotime($reserva->ocupado_inicio)) . ' até ' . date('d/m H:i', strtotime($reserva->ocupado_fim))) ?>">
                                            <span class="font-semibold"><?= e(substr($reserva->inicio, 11, 5)) ?></span>
                                            <span><?= e(($bloqueio ? 'Bloqueio · ' : '') . $reserva->titulo) ?></span>
                                            <?php if ($pessoa) { ?><span class="sr-only"><?= e($pessoa) ?></span><?php } ?>
                                        </a>
                                    <?php } ?>
                                    <?php if (count($reservas) > 2) { ?>
                                        <a class="truncate text-caption text-muted hover:underline" href="<?= e($url($data, 'dia')) ?>"><?= e('+' . (count($reservas) - 2) . ' mais') ?></a>
                                    <?php } ?>
                                </div>
                            </td>
                        <?php } ?>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
