<?php
$meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$titulo = $meses[(int) $selecionado->format('n')] . ' de ' . $selecionado->format('Y');
$hoje = date('Y-m-d');
?>
<section class="rounded-xl border border-border bg-surface" aria-label="Calendário mensal">
    <header class="p-4 sm:p-6">
        <h2 class="text-heading-md font-display text-text"><?= e($titulo) ?></h2>
        <p class="mt-2 text-caption text-muted">Clique em um dia para ver seus horários. Os bloqueios também aparecem no calendário.</p>
    </header>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px] table-fixed border-collapse">
            <caption class="sr-only"><?= e('Reservas de ' . $titulo) ?></caption>
            <thead>
                <tr>
                    <?php foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $nome) { ?>
                        <th scope="col" class="border-b border-t border-border bg-surface-subtle p-3 text-left text-caption text-muted"><?= e($nome) ?></th>
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
                                <div class="flex min-h-28 flex-col gap-2 p-2">
                                    <a href="<?= e($url($data, 'dia')) ?>" aria-label="<?= e('Ver agenda de ' . $data->format('d/m/Y')) ?>" class="flex size-8 items-center justify-center self-start rounded-full text-label-md focus-visible:outline-3 focus-visible:outline-ring <?= e($ehHoje ? 'bg-primary-tint font-semibold text-text' : ($foraDoMes ? 'text-muted' : 'text-text')) ?>">
                                        <?= e($data->format('j')) ?>
                                    </a>
                                    <?php foreach (array_slice($reservas, 0, 3) as $reserva) {
                                        $bloqueio = $reserva->tipo === 'bloqueio';
                                        $destino = $pode_editar ? site_url('agenda/formulario/' . (int) $reserva->id) : ($reserva->os_id ? site_url('os/visualizar/' . (int) $reserva->os_id) : $url($data, 'dia'));
                                        $pessoa = $reserva->beneficiario ?: $reserva->nomeCliente;
                                        ?>
                                        <a href="<?= e($destino) ?>" class="block rounded-sm border border-border p-2 text-caption focus-visible:outline-3 focus-visible:outline-ring <?= e($bloqueio ? 'bg-neutral-soft text-neutral-ink' : 'bg-info-soft text-info-ink') ?>" title="<?= e($reserva->titulo . ($pessoa ? ' · ' . $pessoa : '') . ' · Reservado ' . date('d/m H:i', strtotime($reserva->ocupado_inicio)) . ' até ' . date('d/m H:i', strtotime($reserva->ocupado_fim))) ?>">
                                            <span class="block font-semibold"><?= e(($bloqueio ? 'Bloqueio · ' : '') . substr($reserva->inicio, 11, 5) . '–' . substr($reserva->fim, 11, 5)) ?></span>
                                            <span class="block truncate"><?= e($reserva->titulo) ?></span>
                                            <?php if ($pessoa) { ?><span class="sr-only"><?= e($pessoa) ?></span><?php } ?>
                                        </a>
                                    <?php } ?>
                                    <?php if (count($reservas) > 3) { ?>
                                        <?= component('link', ['label' => '+' . (count($reservas) - 3) . ' reservas', 'href' => $url($data, 'dia')]) ?>
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
