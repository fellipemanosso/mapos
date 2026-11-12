export default function iniciar(painel) {
    const formulario = painel.querySelector('form');
    if (!formulario) return;
    const chave = `mapos:conferencia:${painel.dataset.rascunhoChave}`;
    const assinatura = formulario.elements.namedItem('assinatura').value;
    const campos = [...formulario.querySelectorAll('input[name^="materiais["]')];
    const aviso = (texto) => {
        const mensagem = document.createElement('p');
        mensagem.className = 'mt-3 text-body-md text-text';
        mensagem.setAttribute('role', 'status');
        mensagem.textContent = texto;
        painel.querySelector('summary').after(mensagem);
    };
    try {
        if (new URL(location.href).searchParams.get('retomar') === '1') {
            const bruto = sessionStorage.getItem(chave);
            sessionStorage.removeItem(chave);
            if (bruto) {
                const salvo = JSON.parse(bruto);
                painel.open = true;
                if (salvo.assinatura === assinatura && Date.now() - salvo.em < 3600000) {
                    for (const campo of campos) {
                        const valor = salvo.valores?.[campo.name];
                        if (typeof valor === 'string' && valor.length <= 30) campo.value = valor;
                    }
                    aviso('Quantidades digitadas recuperadas. Confira o estoque atualizado antes de confirmar.');
                } else {
                    aviso('Os dados da conferência mudaram ou o rascunho expirou. Confira as quantidades atualizadas antes de confirmar.');
                }
            }
        }
    } catch { /* O formulário continua disponível se o navegador bloquear o armazenamento. */ }
    painel.addEventListener('click', (evento) => {
        if (!evento.target.closest('[data-guardar-conferencia]')) return;
        try {
            sessionStorage.setItem(chave, JSON.stringify({assinatura, em: Date.now(), valores: Object.fromEntries(campos.map((campo) => [campo.name, campo.value]))}));
        } catch { /* A navegação e o retorno continuam funcionando sem rascunho. */ }
    });
    formulario.addEventListener('submit', () => {
        try { sessionStorage.removeItem(chave); } catch { /* Sem armazenamento disponível. */ }
    });
}
