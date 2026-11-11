<?php

class Resumo_model extends CI_Model
{
    private function soma(string $inicio, string $fim, string $tipo, bool $pago): float
    {
        $data = $pago ? 'data_pagamento' : 'data_vencimento';
        $this->db->select('COALESCE(SUM(' . Financeiro_model::LIQUIDO . '), 0) AS valor', false)
            ->where('tipo', $tipo)->where($data . ' >=', $inicio)->where($data . ' <', $fim);
        if ($pago) {
            $this->db->where('baixado', 1);
        } else {
            $this->db->where('COALESCE(baixado, 0) = 0', null, false);
        }
        return round((float) $this->db->get('lancamentos')->row()->valor, 2);
    }

    public function totais(string $inicio, string $fim): array
    {
        $recebido = $this->soma($inicio, $fim, 'receita', true);
        $pago = $this->soma($inicio, $fim, 'despesa', true);
        return ['recebido' => $recebido, 'pago' => $pago, 'resultado' => round($recebido - $pago, 2),
            'a_receber' => $this->soma($inicio, $fim, 'receita', false), 'a_pagar' => $this->soma($inicio, $fim, 'despesa', false)];
    }

    public function categorias(string $inicio, string $fim): array
    {
        $temGrupos = $this->db->table_exists('despesa_grupos') && $this->db->field_exists('despesa_grupo_id', 'lancamentos');
        $categoria = $temGrupos ? "COALESCE(g.categoria, 'sem_categoria')" : "'sem_categoria'";
        $this->db->select($categoria . ' AS categoria, SUM(' . Financeiro_model::LIQUIDO . ') AS valor', false)->from('lancamentos');
        if ($temGrupos) {
            $this->db->join('despesa_grupos g', 'g.id = lancamentos.despesa_grupo_id', 'left');
        }
        return $this->db->where('tipo', 'despesa')->where('baixado', 1)
            ->where('data_pagamento >=', $inicio)->where('data_pagamento <', $fim)
            ->group_by($categoria, false)->order_by('valor', 'desc')->get()->result();
    }

    public function movimento(string $inicio, string $fim): array
    {
        $valor = Financeiro_model::LIQUIDO;
        return $this->db->select("SUBSTR(data_pagamento, 1, 10) AS dia, SUM(CASE WHEN tipo = 'receita' THEN {$valor} ELSE 0 END) AS receitas, SUM(CASE WHEN tipo = 'despesa' THEN {$valor} ELSE 0 END) AS despesas", false)
            ->where('baixado', 1)->where('data_pagamento >=', $inicio)->where('data_pagamento <', $fim)
            ->group_by('SUBSTR(data_pagamento, 1, 10)', false)->order_by('dia')->get('lancamentos')->result();
    }

    public function pendencias(string $inicio, string $fim): array
    {
        return $this->db->select('lancamentos.*, ' . Financeiro_model::LIQUIDO . ' AS liquido', false)
            ->where('COALESCE(baixado, 0) = 0', null, false)->where('data_vencimento >=', $inicio)->where('data_vencimento <', $fim)
            ->order_by('data_vencimento')->order_by('idLancamentos')->limit(10)->get('lancamentos')->result();
    }
}
