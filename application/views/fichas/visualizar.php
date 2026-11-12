<?php
$csrf = $this->security->get_csrf_token_name();
$token = $this->security->get_csrf_hash();
$valor = static fn ($campo, $padrao = '') => is_string($valores[$campo] ?? null) ? $valores[$campo] : $padrao;
$campos = json_decode($ficha->campos_json, true);
$respostas = json_decode($ficha->respostas_json, true);
$opcoesAgenda = [];
foreach ($agendas as $agenda) {
    $opcoesAgenda[$agenda->id] = $agenda->inicio . ' · ' . $agenda->titulo;
}
?>
<div class="flex max-w-5xl flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="font-display text-heading-xl text-text"><?= e($ficha->modelo_nome . ' · ' . $ficha->paciente_nome) ?></h1><p class="mt-2 text-body-md text-muted"><?= e($ficha->servico_nome . ' · Responsável: ' . $ficha->responsavel_nome . ' · Ficha #' . $ficha->id) ?></p></div><div class="flex flex-wrap gap-2"><?= component('button', ['label' => 'Fichas do atendimento', 'variant' => 'outline', 'href' => site_url('fichas/atendimento/' . $ficha->os_id)]) ?><?= component('button', ['label' => 'Retornos', 'variant' => 'ghost', 'href' => site_url('fichas')]) ?></div></header>
    <?= component('pill-status', ['label' => 'Acesso restrito · registro interno', 'variant' => 'neutral']) ?>
    <?php if (isset($erros['_geral'])) { ?><?= component('alert', ['variant' => 'danger', 'message' => $erros['_geral']]) ?><?php } ?>
    <details open class="rounded-xl border border-border bg-surface p-4 sm:p-6"><summary class="cursor-pointer text-heading-sm text-text">Ficha inicial / anamnese</summary><p class="mt-3 text-caption text-muted"><?= e('Registrada em ' . $ficha->criado_em . ' · Usuário #' . $ficha->usuarios_id) ?></p>
        <?php foreach ($campos as $id => $rotulo) { ?><h3 class="mt-4 text-label-md text-text"><?= e($rotulo) ?></h3><p class="mt-2 whitespace-pre-wrap text-body-md text-text"><?= e($respostas[$id] ?: 'Não informado') ?></p><?php } ?>
    </details>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6"><h2 class="text-heading-sm text-text">Evolução e registros de sessão</h2><p class="mt-2 text-caption text-muted">Cada registro mantém sua data e autoria. Para complementar ou corrigir uma informação, acrescente um novo registro identificando o que está sendo retificado.</p>
        <?php if (! $evolucoes) { ?><p class="mt-3 text-body-md text-muted">Nenhum registro de evolução nesta ficha.</p><?php } ?>
        <?php foreach ($evolucoes as $registro) { ?>
            <article class="mt-4 border-t border-border pt-4"><h3 class="text-heading-sm text-text"><?= e('Registro #' . $registro->id . ' · ' . $registro->ocorrido_em) ?></h3><p class="mt-2 text-caption text-muted"><?= e('Por ' . ($registro->autor ?? ('Usuário #' . $registro->usuarios_id)) . ' · Gravado em ' . $registro->criado_em . ($registro->agenda_id ? ' · Horário #' . $registro->agenda_id : '')) ?></p>
                <?php foreach (['avaliacao' => 'Avaliação', 'procedimento' => 'Procedimento / atendimento realizado', 'orientacoes' => 'Orientações', 'observacoes' => 'Observações / retificações'] as $campo => $rotulo) {
                    if ($registro->{$campo} !== '') { ?><h4 class="mt-3 text-label-md text-text"><?= e($rotulo) ?></h4><p class="mt-1 whitespace-pre-wrap text-body-md text-text"><?= e($registro->{$campo}) ?></p><?php }
                    } ?>
                <?php if ($registro->retorno) { ?>
                    <div class="mt-4 flex flex-wrap items-center gap-3"><p class="text-body-md text-text"><?= e('Retorno previsto: ' . dataBr($registro->retorno)) ?></p><?= component('pill-status', ['label' => $registro->retorno_status === 'concluido' ? 'Concluído' : 'Pendente', 'variant' => $registro->retorno_status === 'concluido' ? 'success' : 'warning']) ?></div>
                    <form method="post" action="<?= e(site_url('fichas/retorno/' . $ficha->id)) ?>" class="mt-3 flex flex-wrap items-end gap-3" <?= js_module('formulario/padrao') ?>>
                        <input type="hidden" name="<?= e($csrf) ?>" value="<?= e($token) ?>"><input type="hidden" name="registro" value="<?= e($registro->id) ?>"><input type="hidden" name="status" value="<?= e($registro->retorno_status === 'concluido' ? 'pendente' : 'concluido') ?>">
                        <?= component('input', ['name' => 'nota', 'id' => 'retorno-nota-' . $registro->id, 'label' => 'Observação do acompanhamento #' . $registro->id, 'required' => true, 'attrs' => ['maxlength' => 1000]]) ?>
                        <?= component('button', ['label' => $registro->retorno_status === 'concluido' ? 'Reabrir retorno' : 'Concluir retorno', 'type' => 'submit', 'variant' => 'outline']) ?>
                    </form>
                    <?php foreach (json_decode($registro->retorno_historico, true) ?: [] as $evento) { ?><p class="mt-2 whitespace-pre-wrap text-caption text-muted"><?= e($evento['em'] . ' · Usuário #' . $evento['usuario'] . ' · ' . ($evento['status'] === 'concluido' ? 'Concluído' : 'Reaberto') . ': ' . $evento['nota']) ?></p><?php } ?>
                <?php } ?>
            </article>
        <?php } ?>
    </section>
    <details open class="rounded-xl border border-border bg-surface p-4 sm:p-6"><summary class="cursor-pointer text-heading-sm text-text">Novo registro de atendimento</summary>
        <form method="post" action="<?= e(site_url('fichas/evolucao/' . $ficha->id)) ?>" class="mt-4 grid gap-4 sm:grid-cols-2" novalidate <?= js_module('formulario/padrao') ?>>
            <input type="hidden" name="<?= e($csrf) ?>" value="<?= e($token) ?>"><input type="hidden" name="chave" value="<?= e($chave) ?>">
            <?= component('input', ['name' => 'ocorrido_em', 'label' => 'Data e hora do atendimento', 'type' => 'datetime-local', 'value' => $valor('ocorrido_em', date('Y-m-d\TH:i')), 'required' => true, 'error' => $erros['ocorrido_em'] ?? null, 'attrs' => ['max' => date('Y-m-d\TH:i')]]) ?>
            <?= component('select', ['name' => 'agenda_id', 'label' => 'Horário / sessão (opcional)', 'options' => $opcoesAgenda, 'selected' => $valor('agenda_id'), 'placeholder' => 'Registro sem vínculo de horário', 'error' => $erros['agenda_id'] ?? null, 'help' => 'Apenas horários já encerrados deste atendimento.']) ?>
            <?php foreach (['avaliacao' => 'Avaliação', 'procedimento' => 'Procedimento / atendimento realizado', 'orientacoes' => 'Orientações fornecidas', 'observacoes' => 'Observações / complementos e retificações'] as $campo => $rotulo) { ?>
                <?= component('textarea', ['name' => $campo, 'label' => $rotulo, 'value' => $valor($campo), 'rows' => 4, 'error' => $erros[$campo] ?? null, 'attrs' => ['maxlength' => 4000]]) ?>
            <?php } ?>
            <?= component('input', ['name' => 'retorno', 'label' => 'Retorno previsto (opcional)', 'type' => 'date', 'value' => $valor('retorno'), 'error' => $erros['retorno'] ?? null, 'help' => 'Aparece no acompanhamento de retornos. O horário deve ser reservado na agenda.']) ?>
            <div class="flex items-end"><?= component('button', ['label' => 'Registrar evolução', 'type' => 'submit']) ?></div>
        </form>
    </details>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-heading-sm text-text">Fotos, documentos e termos</h2><?php if ($ficha->termo !== '') { ?><?= component('button', ['label' => 'Imprimir termo para assinatura', 'variant' => 'outline', 'href' => site_url('fichas/termo/' . $ficha->id), 'attrs' => ['target' => '_blank', 'rel' => 'noopener']]) ?><?php } ?></div>
        <p class="mt-2 text-caption text-muted">Arquivos internos, disponíveis somente para usuários autorizados. O anexo de um termo registra o documento recebido; não verifica a assinatura.</p>
        <?php foreach ($arquivos as $arquivo) { ?><article class="mt-4 border-t border-border pt-3"><?= component('link', ['label' => $arquivo->descricao . ' · ' . $arquivo->nome, 'href' => site_url('fichas/arquivo/' . $ficha->id . '/' . $arquivo->id)]) ?><p class="mt-1 text-caption text-muted"><?= e((FICHA_TIPOS_ARQUIVO[$arquivo->tipo] ?? $arquivo->tipo) . ' · Anexado em ' . $arquivo->criado_em) ?></p><?php if ($arquivo->tipo === 'foto') { ?><img src="<?= e(site_url('fichas/arquivo/' . $ficha->id . '/' . $arquivo->id) . '?visualizar=1') ?>" alt="<?= e($arquivo->descricao) ?>" loading="lazy" class="mt-3 h-auto max-h-48 max-w-full rounded-sm border border-border"><?php } ?><?php if ($arquivo->tipo === 'termo_assinado') { ?><p class="mt-1 text-caption text-muted"><?= e('Assinante informado: ' . $arquivo->signatario . ' · Data informada: ' . dataBr($arquivo->assinado_em)) ?></p><?php } ?></article><?php } ?>
        <?php if (! $arquivos) { ?><p class="mt-3 text-body-md text-muted">Nenhum arquivo anexado.</p><?php } ?>
        <details class="mt-4 border-t border-border pt-4"><summary class="cursor-pointer text-label-md text-text">Anexar foto ou documento</summary>
            <form method="post" enctype="multipart/form-data" action="<?= e(site_url('fichas/anexar/' . $ficha->id)) ?>" class="mt-4 grid gap-4 sm:grid-cols-2" <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($csrf) ?>" value="<?= e($token) ?>">
                <?= component('select', ['name' => 'tipo', 'label' => 'Tipo de arquivo', 'options' => FICHA_TIPOS_ARQUIVO, 'selected' => 'documento']) ?>
                <?= component('input', ['name' => 'descricao', 'label' => 'Descrição do arquivo', 'required' => true, 'attrs' => ['maxlength' => 255]]) ?>
                <?= component('input', ['name' => 'signatario', 'label' => 'Nome de quem assinou (para termo)', 'attrs' => ['maxlength' => 150]]) ?>
                <?= component('input', ['name' => 'assinado_em', 'label' => 'Data da assinatura (para termo)', 'type' => 'date', 'attrs' => ['max' => date('Y-m-d')]]) ?>
                <label class="flex flex-col gap-2 text-label-md text-text sm:col-span-2">Arquivo (PDF, JPG ou PNG)<input type="file" name="userfile" required accept=".pdf,.jpg,.jpeg,.png" class="block max-w-full rounded-sm border border-input bg-field p-2 text-body-md text-text"></label>
                <div><?= component('button', ['label' => 'Anexar à ficha', 'type' => 'submit', 'variant' => 'outline']) ?></div>
            </form>
        </details>
    </section>
    <details class="rounded-xl border border-border bg-surface p-4 sm:p-6"><summary class="cursor-pointer text-heading-sm text-text">Outras fichas da mesma pessoa</summary><?php foreach ($historico as $item) { ?><p class="mt-3"><?= component('link', ['label' => $item->modelo_nome . ' · Atendimento #' . $item->os_id . ' · ' . $item->criado_em, 'href' => site_url('fichas/visualizar/' . $item->id)]) ?></p><?php } ?></details>
</div>
