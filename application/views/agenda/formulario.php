<?php
$campo = static fn ($nome, $label, $extras = []) => $extras + ['name' => $nome, 'label' => $label, 'value' => $valores[$nome] ?? null, 'error' => $erros[$nome] ?? null];
$opcoesOs = [];
foreach ($atendimentos as $atendimento) {
    $opcoesOs[$atendimento->idOs] = '#' . $atendimento->idOs . ' · ' . $atendimento->nomeCliente . ($atendimento->beneficiario ? ' · ' . $atendimento->beneficiario : '');
}
?>
<div class="flex max-w-4xl flex-col gap-4 pt-2 pb-8">
    <header>
        <h1 class="font-display text-heading-xl text-text"><?= e($evento ? 'Editar horário' : 'Reservar horário') ?></h1>
        <p class="text-caption text-muted">Horário de Brasília. Cada sessão tem sua própria reserva na agenda.</p>
    </header>
    <?php if (isset($erros['_geral'])) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => $erros['_geral']]) ?>
    <?php } ?>
    <form method="post" action="<?= e(current_url()) ?>" class="flex flex-col gap-4" novalidate <?= js_module('agenda/formulario') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <section class="grid gap-4 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 sm:p-6">
            <?= component('select', ['name' => 'tipo', 'label' => 'Tipo de reserva', 'options' => ['atendimento' => 'Atendimento', 'bloqueio' => 'Bloqueio de horário'], 'selected' => $valores['tipo'], 'error' => $erros['tipo'] ?? null]) ?>
            <?= component('select', ['name' => 'usuarios_id', 'label' => 'Profissional', 'options' => array_column($profissionais, 'nome', 'idUsuarios'), 'selected' => $valores['usuarios_id'], 'required' => true, 'error' => $erros['usuarios_id'] ?? null]) ?>
            <div data-agenda-atendimento class="sm:col-span-2">
                <?= component('select', ['name' => 'os_id', 'label' => 'Atendimento e cliente', 'placeholder' => 'Selecione o atendimento', 'options' => $opcoesOs, 'selected' => $valores['os_id'], 'error' => $erros['os_id'] ?? null, 'help' => 'O orçamento e os pagamentos continuam no atendimento.']) ?>
                <?= component('link', ['label' => 'Cadastrar novo atendimento', 'href' => site_url('os/adicionar')]) ?>
            </div>
            <div data-agenda-bloqueio class="sm:col-span-2">
                <?= component('input', $campo('titulo', 'Motivo do bloqueio', ['help' => 'Ex.: almoço, compromisso pessoal ou indisponibilidade.', 'attrs' => ['maxlength' => 255]])) ?>
            </div>
        </section>
        <section data-agenda-atendimento class="rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h2 class="text-heading-sm text-text">Serviços deste horário</h2>
            <p class="mt-3 text-caption text-muted">Marque um ou mais serviços. A duração padrão é somada automaticamente.</p>
            <?php if (isset($erros['servicos'])) { ?>
                <?= component('alert', ['variant' => 'danger', 'message' => $erros['servicos']]) ?>
            <?php } ?>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <?php foreach ($servicos as $servico) { ?>
                    <?= component('checkbox', ['name' => 'servicos[]', 'id' => 'agenda-servico-' . (int) $servico->idServicos, 'value' => (string) $servico->idServicos, 'label' => $servico->nome . ' · ' . (int) $servico->duracao_min . ' min', 'checked' => in_array((string) $servico->idServicos, array_map('strval', $valores['servicos']), true), 'attrs' => ['data-duracao-min' => (int) $servico->duracao_min]]) ?>
                <?php } ?>
            </div>
        </section>
        <section class="grid gap-4 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 sm:p-6">
            <?= component('input', $campo('inicio', 'Início do atendimento ou bloqueio', ['type' => 'datetime-local', 'required' => true])) ?>
            <?= component('input', $campo('duracao_min', 'Duração neste horário (minutos)', ['type' => 'number', 'help' => 'Deixe em branco para somar os serviços. Para bloqueios, informe a duração.', 'attrs' => ['min' => 1, 'max' => 1440, 'step' => 1]])) ?>
            <?= component('input', $campo('deslocamento_min', 'Deslocamento antes (minutos)', ['type' => 'number', 'help' => 'Reserva tempo antes do início; não altera o valor cobrado.', 'attrs' => ['min' => 0, 'max' => 720, 'step' => 1]])) ?>
            <?= component('input', $campo('intervalo_min', 'Intervalo após (minutos)', ['type' => 'number', 'help' => 'Tempo para organizar materiais, descanso ou retorno.', 'attrs' => ['min' => 0, 'max' => 720, 'step' => 1]])) ?>
            <p data-agenda-previa class="text-label-md text-text sm:col-span-2" aria-live="polite">Informe os serviços e o horário para conferir a duração.</p>
        </section>
        <div class="flex flex-wrap justify-end gap-2">
            <?= component('button', ['label' => 'Voltar à agenda', 'variant' => 'ghost', 'href' => site_url('agenda')]) ?>
            <?= component('button', ['label' => 'Salvar horário', 'type' => 'submit', 'icon' => 'save']) ?>
        </div>
    </form>
</div>
