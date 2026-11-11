<?php
/**
 * Solicitar atendimento pela área do cliente (#2848), no padrão dos formulários
 * (#2851): POST comum, validação no navegador (formulario/padrao) e no
 * servidor, erro em cada campo. Só a descrição é obrigatória.
 *
 * @var array<string, string> $valores
 * @var array<string, string> $erros  Por campo; _geral para falha ao salvar
 */
$erroGeral = $erros['_geral'] ?? null;
$errosDeCampo = array_diff_key($erros, ['_geral' => true]);
// Props do textarea com o valor e o erro do campo, para component('textarea', $texto(...)).
$texto = static fn (string $nome, array $props) => [
    'name' => $nome,
    'value' => ($valores[$nome] ?? '') !== '' ? $valores[$nome] : null,
    'error' => $errosDeCampo[$nome] ?? null,
    'attrs' => ['maxlength' => 2000] + ($props['attrs'] ?? []),
] + $props;
?>
<div class="flex max-w-3xl flex-col gap-4">
    <div>
        <?= component('button', ['label' => 'Atendimentos', 'icon' => 'chevron-left', 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('mine/os'), 'class' => '-ml-3']) ?>
    </div>

    <header>
        <h1 class="font-display text-heading-xl text-text">Abrir atendimento</h1>
        <p class="text-caption text-muted">Conte o que precisa. A profissional recebe o pedido e entra em contato.</p>
    </header>

    <?php if ($erroGeral !== null) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => $erroGeral]) ?>
    <?php } elseif ($errosDeCampo !== []) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => count($errosDeCampo) === 1 ? 'Confira o campo destacado.' : 'Confira os ' . count($errosDeCampo) . ' campos destacados.']) ?>
    <?php } ?>

    <form method="post" action="<?= e(site_url('mine/adicionarOs')) ?>" novalidate class="flex flex-col gap-4" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6" aria-label="Pedido">
            <?= component('select', [
                'name' => 'dependente_id',
                'label' => 'Quem recebe o atendimento',
                'placeholder' => 'Eu mesma',
                'options' => array_column($dependentes ?? [], 'nome', 'id'),
                'selected' => $dependente_id ?? null,
                'error' => $errosDeCampo['dependente_id'] ?? null,
                'help' => 'Se o bebê não aparecer, solicite o cadastro à profissional.',
            ]) ?>
            <?= component('textarea', $texto('descricaoProduto', [
                'label' => 'Serviço solicitado',
                'rows' => 3,
                'required' => true,
                'placeholder' => 'Ex.: consultoria de amamentação ou perfuração de orelha',
                'help' => 'Informe o serviço que deseja contratar.',
                'attrs' => ['autofocus' => true, 'data-msg-vazio' => 'Informe o serviço desejado.'],
            ])) ?>
            <?= component('textarea', $texto('defeito', [
                'label' => 'Motivo do atendimento',
                'rows' => 4,
                'placeholder' => 'Conte o que precisa e suas dúvidas.',
            ])) ?>
            <?= component('textarea', $texto('observacoes', [
                'label' => 'Observações',
                'rows' => 3,
                'help' => 'Melhor horário para contato e outras informações…',
            ])) ?>
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('mine/os')]) ?>
            <?= component('button', ['label' => 'Solicitar atendimento', 'icon' => 'plus', 'type' => 'submit', 'attrs' => ['data-rotulo-carregando' => 'Enviando…']]) ?>
        </div>
    </form>
</div>
