import iniciarPadrao from '../formulario/padrao.js';

export default function iniciar(form) {
    iniciarPadrao(form);
    const campo = nome => form.querySelector(`[name="${nome}"]`);
    const numero = nome => {
        const texto = campo(nome).value.trim();
        return Number(texto.includes(',') ? texto.replaceAll('.', '').replace(',', '.') : texto) || 0;
    };
    const dinheiro = centavos => (centavos / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    function atualizar() {
        const modo = campo('modo').value;
        for (const tipo of ['avista', 'parcelada', 'recorrente']) {
            const grupo = form.querySelector(`[data-despesa-${tipo}]`);
            grupo.hidden = modo !== tipo;
            for (const input of grupo.querySelectorAll('input, select')) input.disabled = grupo.hidden;
        }
        const liquido = Math.round(numero('valor') * 100) - Math.round(numero('desconto') * 100);
        const n = modo === 'parcelada' ? numero('parcelas') : numero('ocorrencias');
        const entrada = Math.round(numero('entrada') * 100);
        let resumo = `Total: ${dinheiro(liquido)}.`;
        if (modo === 'parcelada' && n > 0) {
            const restante = liquido - entrada;
            const menor = Math.floor(restante / n);
            const maior = Math.ceil(restante / n);
            resumo = `${entrada > 0 ? `Entrada de ${dinheiro(entrada)} + ` : ''}${n} parcelas de ${dinheiro(menor)}${maior !== menor ? ` a ${dinheiro(maior)} (ajuste de centavos)` : ''}. Total da compra: ${dinheiro(liquido)}.`;
        } else if (modo === 'recorrente') {
            resumo = `${n} ocorrências de ${dinheiro(liquido)}. Total programado: ${dinheiro(liquido * n)}. Cada ocorrência começa em aberto.`;
        }
        form.querySelector('[data-despesa-resumo]').textContent = resumo;
        form.querySelector('label[for="campo-valor"]').firstChild.textContent = modo === 'recorrente' ? 'Valor por ocorrência (R$) ' : 'Valor total da compra (R$) ';
    }
    form.addEventListener('input', atualizar);
    form.addEventListener('change', atualizar);
    atualizar();
}
