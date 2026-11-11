export default function iniciar(form) {
    const tipo = form.querySelector('[name="tipo"]');
    const previa = form.querySelector('[data-agenda-previa]');
    const valor = nome => form.querySelector(`[name="${nome}"]`).value;
    function atualizar() {
        const bloqueio = tipo.value === 'bloqueio';
        for (const grupo of form.querySelectorAll('[data-agenda-atendimento]')) {
            grupo.hidden = bloqueio;
            for (const campo of grupo.querySelectorAll('input, select')) campo.disabled = bloqueio;
        }
        const motivo = form.querySelector('[data-agenda-bloqueio]');
        motivo.hidden = !bloqueio;
        motivo.querySelector('input').disabled = !bloqueio;
        const padrao = [...form.querySelectorAll('[data-duracao-min]:checked')].reduce((soma, campo) => soma + Number(campo.dataset.duracaoMin), 0);
        const duracao = Number(valor('duracao_min') || (bloqueio ? 0 : padrao));
        const inicio = new Date(valor('inicio'));
        if (!(duracao > 0) || Number.isNaN(inicio.getTime())) {
            previa.textContent = 'Escolha os serviços ou informe a duração e o horário.';
            return;
        }
        const fim = new Date(inicio.getTime() + duracao * 60000);
        const ocupadoInicio = new Date(inicio.getTime() - Number(valor('deslocamento_min') || 0) * 60000);
        const ocupadoFim = new Date(fim.getTime() + Number(valor('intervalo_min') || 0) * 60000);
        const formatar = data => data.toLocaleString('pt-BR', {day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
        previa.textContent = `Duração: ${duracao} min · Término: ${formatar(fim)} · Agenda ocupada: ${formatar(ocupadoInicio)} até ${formatar(ocupadoFim)}`;
    }
    form.addEventListener('input', atualizar);
    form.addEventListener('change', atualizar);
    atualizar();
}
