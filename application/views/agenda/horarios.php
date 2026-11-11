<?php if ($agendamentos !== []) { ?>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6">
        <h2 class="text-heading-sm text-text">Horários agendados</h2>
        <?php foreach ($agendamentos as $horario) { ?>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-body-md text-text"><?= e(date('d/m/Y H:i', strtotime($horario->inicio)) . ' até ' . date('d/m/Y H:i', strtotime($horario->fim)) . ' · ' . $horario->titulo) ?></p>
                <?php if ($editar_agenda) { ?>
                    <?= component('link', ['label' => 'Editar horário', 'href' => site_url('agenda/formulario/' . (int) $horario->id)]) ?>
                <?php } ?>
            </div>
        <?php } ?>
    </section>
<?php } ?>
