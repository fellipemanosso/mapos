<?php

class Indicadores_model extends CI_Model
{
    public function origens(string $inicio, string $fim): array
    {
        return $this->db->select("origem, COUNT(*) AS contatos, SUM(CASE WHEN os_id IS NOT NULL THEN 1 ELSE 0 END) AS convertidos", false)
            ->where('criado_em >=', $inicio)->where('criado_em <', $fim)->group_by('origem')->order_by('contatos', 'desc')->get('leads')->result();
    }

    public function servicos(string $inicio, string $fim): array
    {
        return $this->db->select('s.nome, SUM(i.quantidade) AS quantidade, SUM(i.quantidade * COALESCE(NULLIF(i.preco, 0), s.preco, 0)) AS contratado', false)
            ->from('servicos_os i')->join('os', 'os.idOs = i.os_id')->join('servicos s', 's.idServicos = i.servicos_id')
            ->where('os.status !=', 'Cancelado')->where('os.dataInicial >=', $inicio)->where('os.dataInicial <', $fim)
            ->group_start()->where_in('os.status', ['Aprovado', 'Em Andamento', 'Finalizado', 'Faturado'])->or_where('os.faturado', 1)->or_where('os.financeiro_confirmado', 1)->group_end()
            ->group_by(['s.idServicos', 's.nome'])->order_by('contratado', 'desc')->get()->result();
    }

    public function consumo(string $inicio, string $fim): float
    {
        return (float) $this->db->select('COALESCE(SUM(custo), 0) AS custo', false)->where('tipo', 'consumo')->where('estornado_em', null)
            ->where('criado_em >=', $inicio)->where('criado_em <', $fim)->get('insumo_movimentos')->row()->custo;
    }
}
