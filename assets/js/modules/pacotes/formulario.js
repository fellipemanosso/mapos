export default function iniciar(form) {
  const resumo = form.querySelector('[data-pacote-total]');
  const atualizar = () => {
    let total = 0;
    let sessoes = 0;
    for (const item of form.querySelectorAll('[data-pacote-item]')) {
      const quantidade = Number(item.querySelector('[data-pacote-quantidade]').value) || 0;
      const texto = item.querySelector('[data-pacote-preco]').value.trim();
      const preco = Number(texto.includes(',') ? texto.replaceAll('.', '').replace(',', '.') : texto) || 0;
      total += quantidade * Math.round(preco * 100);
      sessoes += quantidade;
    }
    resumo.textContent = `${sessoes} sessões · Total do pacote: ${(total / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}`;
  };
  form.addEventListener('input', atualizar);
  atualizar();
}
