<?php
/**
 * Formulário de OS, para cadastrar e editar os dados (#2842), no padrão dos
 * formulários da v5 (#2851, views/clientes/formulario.php).
 *
 * - POST comum, com o token CSRF; o módulo formulario/padrao valida no
 *   navegador e o servidor valida de novo (Os::formulario()).
 * - Cliente, técnico e termo de garantia são autocompletes (módulo
 *   os/formulario, lib/autocomplete.js): o texto fica no campo visível e o id
 *   escolhido no campo oculto ao lado. Digitar sem escolher da lista deixa o
 *   campo inválido.
 * - Os textos livres são texto simples; a formatação do editor antigo
 *   (Trumbowyg) sai ao salvar, e as quebras de linha ficam.
 * - Produtos, serviços, anexos, anotações, desconto e faturamento ficam na
 *   tela do atendimento (os/visualizar), linkada no topo ao editar.
 *
 * @var object|null             $os         null ao cadastrar
 * @var array<string, string>   $valores    osValoresDoFormulario()
 * @var array<string, string>   $erros      Por campo; _geral para falha ao salvar
 * @var list<string>            $formatados Campos de texto com formatação do editor antigo
 * @var array<string, bool>     $pode
 */
$editando = $os !== null;
$erroGeral = $erros['_geral'] ?? null;
$errosDeCampo = array_diff_key($erros, ['_geral' => true]);

$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$grade = 'mt-4 grid gap-4 sm:grid-cols-2';

/*
 * Autocomplete: input visível (o texto) + oculto (o id), num wrapper que diz
 * ao módulo de onde vêm as opções (data-autocomplete) e qual campo guarda o
 * id (data-autocomplete-alvo).
 */
$visivel = static fn (string $nome, array $props) => $props + [
    'name' => $nome,
    'value' => $valores[$nome] !== '' ? $valores[$nome] : null,
    'error' => $errosDeCampo[$nome] ?? null,
    'autocomplete' => 'off',
];
$wrapper = static fn (string $url, string $oculto, array $extras = []) => ['class' => 'relative', 'data-autocomplete' => $url, 'data-autocomplete-alvo' => componenteId($oculto)] + $extras;
$oculto = static fn (string $nome) => ['type' => 'hidden', 'id' => componenteId($nome), 'name' => $nome, 'value' => $valores[$nome]];

$texto = static fn (string $nome, string $label, ?string $ajuda = null) => [
    'name' => $nome,
    'label' => $label,
    'rows' => 4,
    'value' => $valores[$nome] !== '' ? $valores[$nome] : null,
    'error' => $errosDeCampo[$nome] ?? null,
    'help' => in_array($nome, $formatados, true)
        ? 'A formatação do editor antigo (negrito, listas) sai ao salvar. O texto e as quebras de linha ficam.'
        : $ajuda,
];

$statusOpcoes = array_combine(array_keys(OS_STATUS_VARIANTES), array_keys(OS_STATUS_VARIANTES));
?>
<div class="flex max-w-4xl flex-col gap-4 pt-2 pb-8" <?= js_module('os/formulario') ?>>
    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex flex-col gap-1">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-display text-heading-xl text-text"><?= e($editando ? 'Editar atendimento #' . $os->idOs : 'Novo atendimento') ?></h1>
                <?php if ($editando) { ?>
                    <?= component('pill-status', osStatusPill($os->status)) ?>
                <?php } ?>
            </div>
            <p class="text-caption text-muted"><?= e($editando ? $os->nomeCliente : 'Os produtos e serviços são adicionados depois de criar o atendimento.') ?></p>
        </div>
        <?php if ($pode['ver_os']) { ?>
            <?= component('button', [
                'label' => 'Ver atendimento, itens e faturamento',
                'icon' => 'eye',
                'variant' => 'outline',
                'href' => site_url('os/visualizar/' . (int) $os->idOs),
            ]) ?>
        <?php } ?>
    </header>

    <?php if ($erroGeral !== null) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => $erroGeral]) ?>
    <?php } elseif ($errosDeCampo !== []) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => count($errosDeCampo) === 1 ? 'Confira o campo destacado.' : 'Confira os ' . count($errosDeCampo) . ' campos destacados.']) ?>
    <?php } ?>

    <form method="post" action="<?= e(current_url()) ?>" novalidate class="flex flex-col gap-4" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-responsaveis">
            <h2 id="secao-responsaveis" class="text-heading-sm text-text">Quem contrata e quem recebe o atendimento</h2>
            <div class="<?= e($grade) ?>">
                <div<?= componenteAtributos($wrapper(site_url('os/autoCompleteCliente'), 'clientes_id', $pode['cadastrar_cliente'] ? [
                    'data-autocomplete-novo' => site_url('clientes/adicionar'),
                    'data-autocomplete-novo-modal' => 'cliente-rapido',
                    'data-autocomplete-novo-rotulo' => 'Cadastrar novo cliente',
                ] : [])) ?>>
                    <?= component('input', $visivel('cliente', [
                        'label' => 'Cliente ou responsável pelo pagamento',
                        'required' => true,
                        'placeholder' => 'Nome, documento ou telefone',
                        'attrs' => [
                            'autofocus' => ! $editando,
                            'data-msg-vazio' => 'Escolha o cliente do atendimento.',
                            'data-msg-invalido' => 'Escolha um cliente da lista.',
                        ],
                    ])) ?>
                    <input<?= componenteAtributos($oculto('clientes_id')) ?>>
                </div>
                <?= component('select', [
                    'name' => 'dependente_id',
                    'label' => 'Quem recebe o atendimento',
                    'placeholder' => 'A própria cliente',
                    'options' => array_column($dependentes, 'nome', 'id'),
                    'selected' => $valores['dependente_id'] ?: null,
                    'error' => $errosDeCampo['dependente_id'] ?? null,
                    'help' => 'Para bebês, cadastre o dependente na ficha da responsável. Em branco, o atendimento é para a própria cliente.',
                    'attrs' => ['data-dependentes-url' => site_url('os/dependentes')],
                ]) ?>
                <div<?= componenteAtributos($wrapper(site_url('os/autoCompleteUsuario'), 'usuarios_id')) ?>>
                    <?= component('input', $visivel('tecnico', [
                        'label' => 'Profissional responsável',
                        'required' => true,
                        'placeholder' => 'Nome da profissional',
                        'attrs' => [
                            'data-msg-vazio' => 'Escolha a profissional responsável.',
                            'data-msg-invalido' => 'Escolha uma profissional da lista.',
                        ],
                    ])) ?>
                    <input<?= componenteAtributos($oculto('usuarios_id')) ?>>
                </div>
            </div>
        </section>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-situacao">
            <h2 id="secao-situacao" class="text-heading-sm text-text">Situação e prazos</h2>
            <div class="<?= e($grade) ?>">
                <?= component('select', [
                    'name' => 'status',
                    'label' => 'Status',
                    'required' => true,
                    'options' => $statusOpcoes,
                    'selected' => $valores['status'] !== '' ? $valores['status'] : null,
                    'error' => $errosDeCampo['status'] ?? null,
                    'help' => $editando ? 'Cancelar o atendimento devolve os produtos ao estoque.' : null,
                ]) ?>
                <div class="hidden sm:block" aria-hidden="true"></div>
                <?= component('input', [
                    'name' => 'dataInicial',
                    'label' => 'Data inicial',
                    'type' => 'date',
                    'required' => true,
                    'value' => $valores['dataInicial'] !== '' ? $valores['dataInicial'] : null,
                    'error' => $errosDeCampo['dataInicial'] ?? null,
                    'attrs' => ['data-msg-vazio' => 'Informe a data inicial.'],
                ]) ?>
                <?= component('input', [
                    'name' => 'dataFinal',
                    'label' => 'Data final',
                    'type' => 'date',
                    'required' => true,
                    'value' => $valores['dataFinal'] !== '' ? $valores['dataFinal'] : null,
                    'error' => $errosDeCampo['dataFinal'] ?? null,
                    'help' => 'Data prevista para conclusão do atendimento.',
                    'attrs' => ['data-msg-vazio' => 'Informe a data final.'],
                ]) ?>
                <?= component('input', [
                    'name' => 'garantia',
                    'label' => 'Garantia (dias)',
                    'type' => 'number',
                    'value' => $valores['garantia'] !== '' ? $valores['garantia'] : null,
                    'error' => $errosDeCampo['garantia'] ?? null,
                    'help' => 'Em branco ou 0 quando não há garantia.',
                    'attrs' => ['min' => 0, 'max' => 9999, 'step' => 1, 'inputmode' => 'numeric', 'data-msg-invalido' => 'Informe a garantia em dias, de 0 a 9999.'],
                ]) ?>
                <div<?= componenteAtributos($wrapper(site_url('os/autoCompleteTermoGarantia'), 'garantias_id', ['data-autocomplete-min' => '1'])) ?>>
                    <?= component('input', $visivel('termoGarantia', [
                        'label' => 'Termo de garantia',
                        'placeholder' => 'Opcional',
                        'help' => 'Texto impresso no termo de garantia do atendimento.',
                        'attrs' => ['data-msg-invalido' => 'Escolha um termo da lista ou deixe o campo em branco.'],
                    ])) ?>
                    <input<?= componenteAtributos($oculto('garantias_id')) ?>>
                </div>
            </div>
        </section>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-equipamento">
            <h2 id="secao-equipamento" class="text-heading-sm text-text">Serviço e informações do atendimento</h2>
            <div class="<?= e($grade) ?>">
                <?= component('textarea', $texto('descricaoProduto', 'Serviço solicitado', 'Ex.: perfuração de orelha, consultoria de amamentação ou doula.')) ?>
                <?= component('textarea', $texto('defeito', 'Motivo do atendimento', 'O que o cliente relatou.')) ?>
                <?= component('textarea', $texto('observacoes', 'Observações')) ?>
                <?= component('textarea', $texto('laudoTecnico', 'Registro do atendimento', 'O que foi realizado e orientações fornecidas.')) ?>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('os')]) ?>
            <?= component('button', ['label' => $editando ? 'Salvar alterações' : 'Criar atendimento', 'icon' => 'save', 'type' => 'submit', 'attrs' => ['data-rotulo-carregando' => 'Salvando…']]) ?>
        </div>
    </form>

    <?php if ($pode['cadastrar_cliente']) { ?>
        <?php $this->load->view('clientes/partes/cadastro-rapido') ?>
    <?php } ?>
</div>
