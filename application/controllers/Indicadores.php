<?php

class Indicadores extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'resumo', 'financeiro', 'leads']);
        $this->load->model(['indicadores_model', 'resumo_model', 'financeiro_model']);
    }

    public function index()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'rOs')
            || ! $this->permission->checkPermission($this->session->userdata('permissao'), 'rFinanceiro')) {
            show_error('Sem permissão para consultar indicadores comerciais e financeiros.', 403);
            return;
        }
        $mes = resumoMes($this->input->get('mes'), date('Y-m-d'));
        $inicio = $mes->format('Y-m-d');
        $fim = $mes->modify('+1 month')->format('Y-m-d');
        $origens = $this->indicadores_model->origens($inicio, $fim);
        $contatos = array_sum(array_column($origens, 'contatos'));
        $convertidos = array_sum(array_column($origens, 'convertidos'));
        $this->data += ['mes' => $mes, 'origens' => $origens, 'contatos' => $contatos, 'convertidos' => $convertidos,
            'conversao' => $contatos ? round($convertidos * 100 / $contatos, 1) : 0, 'totais' => $this->resumo_model->totais($inicio, $fim),
            'servicos' => $this->indicadores_model->servicos($inicio, $fim), 'consumo' => $this->indicadores_model->consumo($inicio, $fim)];
        $this->data['view'] = 'indicadores/index';
        return $this->layout();
    }
}
