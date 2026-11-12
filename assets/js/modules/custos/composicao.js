import iniciarFormulario from '../formulario/padrao.js';

export default function iniciar(formulario) {
    iniciarFormulario(formulario);
    const campos = [...formulario.querySelectorAll('input:not([type=hidden])')];
    const original = campos.map((campo) => campo.type === 'checkbox' ? campo.checked : campo.value);
    const status = formulario.querySelector('[data-composicao-status]');
    let enviando = false;
    const alterado = () => formulario.dataset.pendente === 'true' || campos.some((campo, indice) => (campo.type === 'checkbox' ? campo.checked : campo.value) !== original[indice]);
    const atualizar = () => {
        status.textContent = alterado() ? 'Alterações pendentes. Salve a composição para aplicá-las.' : 'Sem alterações pendentes.';
        for (const remover of formulario.querySelectorAll('input[type=checkbox]')) {
            const quantidade = remover.closest('.grid').querySelector('input:not([type=checkbox])');
            quantidade.required = !remover.checked;
        }
    };
    formulario.addEventListener('input', atualizar);
    formulario.addEventListener('change', atualizar);
    formulario.addEventListener('submit', (evento) => { enviando = !evento.defaultPrevented; });
    window.addEventListener('beforeunload', (evento) => {
        if (alterado() && !enviando) {
            evento.preventDefault();
        }
    });
    window.addEventListener('pageshow', () => { enviando = false; });
    atualizar();
}
