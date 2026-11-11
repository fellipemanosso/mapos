<?php
$url = static fn ($dia, $visao) => site_url('agenda') . '?' . http_build_query(['data' => $dia->format('Y-m-d'), 'modo' => $visao]);
$salto = $modo === 'dia' ? '1 day' : '7 days';
$dias = ['Mon' => 'Segunda', 'Tue' => 'Terça', 'Wed' => 'Quarta', 'Thu' => 'Quinta', 'Fri' => 'Sexta', 'Sat' => 'Sábado', 'Sun' => 'Domingo'];
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header>
        <h1 class="font-display text-heading-xl text-text">Agenda de atendimentos</h1>
        <p class="text-caption text-muted">Serviços, deslocamentos e bloqueios · horário de Brasília</p>
    </header>
    <div class="flex flex-wrap items-center gap-2">
        <?= component('button', ['label' => 'Anterior', 'variant' => 'outline', 'href' => $url($inicio->modify('-' . $salto), $modo)]) ?>
        <?= component('button', ['label' => 'Hoje', 'variant' => 'outline', 'href' => $url(new DateTimeImmutable('today'), $modo)]) ?>
        <?= component('button', ['label' => 'Próximo', 'variant' => 'outline', 'href' => $url($inicio->modify('+' . $salto), $modo)]) ?>
        <?= component('button', ['label' => 'Dia', 'variant' => $modo === 'dia' ? 'outline' : 'ghost', 'href' => $url($selecionado, 'dia')]) ?>
        <?= component('button', ['label' => 'Semana', 'variant' => $modo === 'semana' ? 'outline' : 'ghost', 'href' => $url($selecionado, 'semana')]) ?>
        <?php if ($pode_editar) { ?>
            <?= component('button', ['label' => 'Bloquear horário', 'variant' => 'outline', 'href' => site_url('agenda/formulario') . '?tipo=bloqueio']) ?>
        <?php } ?>
    </div>
    <form method="get" action="<?= e(site_url('agenda')) ?>" class="flex flex-wrap items-end gap-2">
        <input type="hidden" name="modo" value="<?= e($modo) ?>">
        <?= component('input', ['name' => 'data', 'label' => 'Ir para a data', 'type' => 'date', 'value' => $selecionado->format('Y-m-d'), 'required' => true]) ?>
        <?= component('button', ['label' => 'Ver data', 'type' => 'submit', 'variant' => 'outline']) ?>
    </form>
    <?php for ($dia = $inicio; $dia < $fim; $dia = $dia->modify('+1 day')) {
        $diaInicio = $dia->format('Y-m-d H:i:s');
        $diaFim = $dia->modify('+1 day')->format('Y-m-d H:i:s');
        $reservas = array_filter($eventos, static fn ($e) => $e->ocupado_inicio < $diaFim && $e->ocupado_fim > $diaInicio);
        ?>
        <section class="rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h2 class="text-heading-sm text-text"><?= e($dias[$dia->format('D')] . ', ' . $dia->format('d/m/Y')) ?></h2>
            <?php if ($reservas === []) { ?>
                <p class="mt-3 text-body-md text-muted">Sem reservas neste dia.</p>
            <?php } ?>
            <?php foreach ($reservas as $reserva) {
                $modalId = 'cancelar-agenda-' . (int) $reserva->id . '-' . $dia->format('Ymd');
                ?>
                <article class="mt-4 flex flex-wrap justify-between gap-3 border-t border-border pt-4">
                    <div class="flex flex-col gap-2">
                        <p class="text-label-md text-text"><?= e(substr($reserva->inicio, 11, 5) . ' – ' . substr($reserva->fim, 11, 5) . ' · ' . $reserva->duracao_min . ' min') ?></p>
                        <?= component('pill-status', ['label' => $reserva->tipo === 'bloqueio' ? 'Bloqueio' : 'Atendimento', 'variant' => $reserva->tipo === 'bloqueio' ? 'neutral' : 'info']) ?>
                        <h3 class="text-label-md text-text"><?= e($reserva->titulo) ?></h3>
                        <?php if ($reserva->os_id) { ?>
                            <p class="text-body-md text-text"><?= e(($reserva->beneficiario ?: $reserva->nomeCliente) . ($reserva->beneficiario ? ' · Responsável: ' . $reserva->nomeCliente : '')) ?></p>
                            <?= component('link', ['label' => 'Atendimento #' . $reserva->os_id, 'href' => site_url('os/visualizar/' . (int) $reserva->os_id)]) ?>
                        <?php } ?>
                        <p class="text-caption text-muted"><?= e('Profissional: ' . $reserva->profissional) ?></p>
                        <p class="text-caption text-muted"><?= e('Reservado de ' . date('d/m H:i', strtotime($reserva->ocupado_inicio)) . ' até ' . date('d/m H:i', strtotime($reserva->ocupado_fim)) . ' · Deslocamento: ' . $reserva->deslocamento_min . ' min · Intervalo: ' . $reserva->intervalo_min . ' min') ?></p>
                    </div>
                    <?php if ($pode_editar) { ?>
                        <div class="flex flex-wrap items-start gap-2">
                            <?= component('button', ['label' => 'Editar horário', 'variant' => 'outline', 'href' => site_url('agenda/formulario/' . (int) $reserva->id)]) ?>
                            <?= component('button', ['label' => 'Cancelar reserva', 'variant' => 'ghost', 'attrs' => ['data-modal-abrir' => $modalId]]) ?>
                        </div>
                        <form id="<?= e($modalId . '-form') ?>" method="post" action="<?= e(site_url('agenda/cancelar/' . (int) $reserva->id)) ?>" hidden>
                            <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                        </form>
                        <?= component('modal-confirm', ['id' => $modalId, 'title' => 'Cancelar esta reserva?', 'message' => 'O horário será liberado. O atendimento e seus pagamentos serão preservados.', 'confirm_label' => 'Cancelar reserva', 'confirm_attrs' => ['form' => $modalId . '-form']]) ?>
                    <?php } ?>
                </article>
            <?php } ?>
        </section>
    <?php } ?>
</div>
