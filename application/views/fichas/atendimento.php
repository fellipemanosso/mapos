<div class="flex max-w-5xl flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="font-display text-heading-xl text-text"><?= e('Fichas · Atendimento #' . $os->idOs) ?></h1><p class="mt-2 text-body-md text-muted"><?= e(($os->beneficiario_nome ?? $os->nomeCliente) . ' · Cliente / responsável: ' . $os->nomeCliente) ?></p></div><?= component('button', ['label' => 'Voltar ao atendimento', 'variant' => 'outline', 'href' => site_url('os/visualizar/' . $os->idOs)]) ?></header>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-heading-sm text-text">Fichas deste atendimento</h2><?= component('button', ['label' => 'Nova ficha', 'href' => site_url('fichas/criar/' . $os->idOs)]) ?></div>
        <?php if (! $fichas) { ?><p class="mt-3 text-body-md text-muted">Nenhuma ficha iniciada. Escolha um serviço e um modelo para começar.</p><?php } ?>
        <?php foreach ($fichas as $ficha) { ?><div class="mt-3 border-t border-border pt-3"><?= component('link', ['label' => $ficha->modelo_nome . ' · ' . $ficha->servico_nome . ' · ' . $ficha->criado_em, 'href' => site_url('fichas/visualizar/' . $ficha->id)]) ?></div><?php } ?>
    </section>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6"><h2 class="text-heading-sm text-text">Histórico da mesma pessoa atendida</h2><p class="mt-2 text-caption text-muted">O histórico de cada bebê é separado do histórico da responsável e dos demais dependentes.</p>
        <?php foreach ($historico as $registro) { ?><div class="mt-3 border-t border-border pt-3"><?= component('link', ['label' => $registro->modelo_nome . ' · Atendimento #' . $registro->os_id . ' · ' . $registro->criado_em, 'href' => site_url('fichas/visualizar/' . $registro->id)]) ?></div><?php } ?>
        <?php if (! $historico) { ?><p class="mt-3 text-body-md text-muted">Nenhum registro anterior para esta pessoa.</p><?php } ?>
    </section>
</div>
