<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e('Orçamento #' . $lead->id) ?></title><link rel="stylesheet" href="<?= e(base_url('assets/dist/imprimir.css')) ?>"></head>
<body><main class="doc-folha">
    <header class="doc-cabecalho"><div><h1 class="text-xl font-bold"><?= e($emitente->nome ?? $this->config->item('app_name')) ?></h1><?php if ($emitente) { ?><p><?= e('CNPJ: ' . $emitente->cnpj) ?></p><p><?= e($emitente->telefone . ' · ' . $emitente->email) ?></p><?php } ?></div></header>
    <h2 class="doc-titulo"><?= e('ORÇAMENTO #' . $lead->id) ?></h2>
    <section class="py-4"><p><?= e('Cliente / responsável: ' . $lead->nome) ?></p><?php if ($lead->bebe) { ?><p><?= e('Bebê: ' . $lead->bebe) ?></p><?php } ?><p><?= e($lead->interesse) ?></p><?php if ($lead->validade) { ?><p><?= e('Válido até ' . dataBr($lead->validade)) ?></p><?php } ?></section>
    <table class="w-full border-collapse"><caption class="text-left font-bold">Serviços propostos</caption><thead><tr><th class="border p-2 text-left">Serviço</th><th class="border p-2">Qtd.</th><th class="border p-2 text-right">Valor unitário</th><th class="border p-2 text-right">Subtotal</th></tr></thead><tbody>
    <?php foreach ($itens as $item) { ?><tr><td class="border p-2"><?= e($item['nome']) ?></td><td class="border p-2 text-center"><?= e($item['quantidade']) ?></td><td class="border p-2 text-right"><?= e(dinheiro($item['preco'])) ?></td><td class="border p-2 text-right"><?= e(dinheiro($item['quantidade'] * (float) $item['preco'])) ?></td></tr><?php } ?>
    </tbody></table>
    <section class="py-4"><p class="text-lg font-bold"><?= e('Total: ' . dinheiro($lead->total)) ?></p><p><?= e('Sinal previsto: ' . dinheiro($lead->sinal)) ?></p><p><?= e('Saldo após o sinal: ' . dinheiro((float) $lead->total - (float) $lead->sinal)) ?></p><p class="mt-4 whitespace-pre-wrap"><?= e($lead->condicoes) ?></p></section>
    <p>Orçamento sujeito à confirmação de disponibilidade. Não comprova pagamento nem reserva de horário.</p>
    <p class="doc-so-tela mt-4">Use a opção Imprimir do navegador (Ctrl+P) para imprimir ou salvar em PDF.</p>
</main></body></html>
