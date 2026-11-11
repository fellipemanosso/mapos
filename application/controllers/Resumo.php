<?php

class Resumo extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['resumo', 'financeiro', 'despesas']);
        $this->load->model(['financeiro_model', 'resumo_model']);
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['vLancamento'])) {
            show_error('Sem permissão para visualizar o financeiro.', 403);
            return;
        }
        $mes = resumoMes($this->input->get('mes'), date('Y-m-d'));
        $inicio = $mes->format('Y-m-d');
        $fim = $mes->modify('+1 month')->format('Y-m-d');
        $anterior = $mes->modify('-1 month');
        $this->data += [
            'mes' => $mes, 'totais' => $this->resumo_model->totais($inicio, $fim),
            'anterior' => $this->resumo_model->totais($anterior->format('Y-m-d'), $inicio),
            'categorias' => $this->resumo_model->categorias($inicio, $fim),
            'movimento' => $this->resumo_model->movimento($inicio, $fim),
            'pendencias' => $this->resumo_model->pendencias($inicio, $fim),
            'pode_editar' => $this->permite('eLancamento'),
        ];
        $this->data['view'] = 'resumo/index';
        return $this->layout();
    }
}
