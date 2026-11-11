// Formulário de OS (views/os/formulario.php, #2842): liga os autocompletes de
// cliente, técnico e termo de garantia (lib/autocomplete.js). A validação, o
// carregamento do botão e o envio ficam com o módulo formulario/padrao, no
// <form>.

import { iniciarAutocompletes } from '../../lib/autocomplete.js';
import { get } from '../../lib/http.js';

export default function iniciar(elemento) {
    iniciarAutocompletes(elemento);
    const cliente = elemento.querySelector('[name="clientes_id"]');
    const dependente = elemento.querySelector('[name="dependente_id"]');
    if (!cliente || !dependente) return;
    let consulta = 0;
    cliente.addEventListener('change', async () => {
        const atual = ++consulta;
        dependente.replaceChildren(new Option('A própria cliente', ''));
        dependente.disabled = true;
        dependente.setCustomValidity('');
        if (!cliente.value) return;
        try {
            const url = new URL(dependente.dataset.dependentesUrl);
            url.searchParams.set('cliente', cliente.value);
            const dados = await get(url);
            if (atual !== consulta) return;
            for (const item of dados.dependentes) {
                dependente.add(new Option(item.nome, String(item.id)));
            }
            dependente.disabled = false;
        } catch {
            if (atual !== consulta) return;
            dependente.replaceChildren(new Option('Não foi possível carregar. Selecione a responsável novamente.', ''));
            dependente.disabled = false;
            dependente.setCustomValidity('Selecione a responsável novamente para carregar os dependentes.');
        }
    });
}
