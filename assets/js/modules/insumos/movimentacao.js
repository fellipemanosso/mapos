import iniciarFormulario from '../formulario/padrao.js';

export default function iniciar(formulario) {
    const tipo = formulario.querySelector('[name="tipo"]');
    const atualizar = () => {
        for (const grupo of formulario.querySelectorAll('[data-operacoes]')) {
            const ativo = grupo.dataset.operacoes.split(' ').includes(tipo.value);
            grupo.hidden = !ativo;
            for (const campo of grupo.querySelectorAll('input, select, textarea')) {
                campo.disabled = !ativo;
            }
        }
    };
    tipo.addEventListener('change', atualizar);
    atualizar();
    iniciarFormulario(formulario);
}
