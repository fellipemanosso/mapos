<?php

class Leads extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'leads', 'financeiro']);
        $this->load->model('leads_model');
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['vOs'])) {
            show_error('Sem permissão para visualizar contatos.', 403);
            return;
        }
        $filtros = listagemFiltros(['pesquisa' => 'texto', 'etapa' => array_keys(LEADS_ETAPAS), 'retornos' => ['hoje']], $this->input->get());
        $offset = max(0, (int) $this->uri->segment(3));
        $total = $this->leads_model->contar($filtros);
        if ($this->permite('aOs') && $this->permite('eOs')) {
            $this->data['topbar_acao'] = ['label' => 'Novo contato', 'icon' => 'plus', 'href' => site_url('leads/formulario')];
        }
        $this->data += ['leads' => $this->leads_model->listar($filtros, $offset, (int) $this->data['configuration']['per_page']), 'filtros' => $filtros, 'total' => $total,
            'paginacao' => $this->paginacao(site_url('leads/index'), $total, $offset, null, $filtros)];
        $this->data['view'] = 'leads/index';
        return $this->layout();
    }

    private function contato($id): object
    {
        $lead = is_scalar($id) && ctype_digit((string) $id) ? $this->leads_model->getById((int) $id) : null;
        if (! $lead) {
            show_404();
            exit;
        }
        return $lead;
    }

    public function formulario($id = null)
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aOs')
            || ! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            show_error('Sem permissão para cadastrar ou editar contatos.', 403);
            return;
        }
        $lead = $id !== null ? $this->contato($id) : null;
        if ($lead && $lead->os_id) {
            $this->session->set_flashdata('error', 'Contato convertido. Edite o atendimento vinculado.');
            return redirect('leads/visualizar/' . $lead->id);
        }
        $servicos = $this->db->select('idServicos, nome, preco')->order_by('nome')->get('servicos')->result();
        $valores = $lead ? (array) $lead : ['origem' => 'whatsapp', 'sinal' => '0,00'];
        $valores['servicos'] = [];
        foreach ($lead ? json_decode($lead->itens_json, true) : [] as $item) {
            $valores['servicos'][$item['servicos_id']] = ['quantidade' => (string) $item['quantidade'], 'preco' => number_format((float) $item['preco'], 2, ',', '')];
        }
        $erros = [];
        if ($this->input->method() === 'post') {
            $valores = $this->input->post();
            [$dados, $erros] = leadFormulario($valores, $servicos);
            $chave = $valores['chave'] ?? '';
            $versao = $valores['versao'] ?? '';
            if (! is_string($chave) || ! preg_match('/\A[a-f0-9]{32}\z/', $chave) || ! is_string($versao) || ! ctype_digit($versao)) {
                $erros['_geral'] = 'Reabra o formulário antes de salvar.';
            }
            if ($erros === []) {
                try {
                    $salvo = $this->leads_model->salvar($lead ? (int) $lead->id : null, $dados, $chave, (int) $versao);
                    log_info('Salvou contato comercial #' . $salvo);
                    $this->session->set_flashdata('success', 'Contato e orçamento salvos.');
                    return redirect('leads/visualizar/' . $salvo);
                } catch (DomainException $e) {
                    $erros['_geral'] = $e->getMessage();
                }
            }
            $this->output->set_status_header(422);
        }
        $this->data += ['lead' => $lead, 'valores' => $valores, 'erros' => $erros, 'servicos' => $servicos,
            'chave' => $lead->chave ?? (is_string($valores['chave'] ?? null) ? $valores['chave'] : bin2hex(random_bytes(16))),
            'versao' => $this->input->method() === 'post' && is_string($valores['versao'] ?? null) ? $valores['versao'] : ($lead->versao ?? 0)];
        $this->data['view'] = 'leads/formulario';
        return $this->layout();
    }

    public function visualizar($id = null)
    {
        if (! $this->hasAnyPermission(['vOs'])) {
            show_error('Sem permissão para visualizar contatos.', 403);
            return;
        }
        return $this->tela($this->contato($id));
    }

    private function tela(object $lead, ?string $erro = null)
    {
        $this->data += ['lead' => $lead, 'itens' => json_decode($lead->itens_json, true), 'erro' => $erro,
            'clientes' => $this->db->select('idClientes, nomeCliente')->where('fornecedor', 0)->order_by('nomeCliente')->get('clientes')->result(),
            'candidatas' => $this->leads_model->candidatas($lead->telefone),
            'profissionais' => $this->db->select('idUsuarios, nome')->where('situacao', 1)->order_by('nome')->get('usuarios')->result(),
            'pode_editar' => $this->permite('eOs'),
            'pode_formulario' => $this->permite('aOs') && $this->permite('eOs'),
            'pode_converter' => $this->permite('aOs') && $this->permite('eOs') && $this->permite('aCliente')];
        $this->data['view'] = 'leads/visualizar';
        return $this->layout();
    }

    private function post(): bool
    {
        if ($this->input->method() !== 'post') {
            show_error('Use o formulário para esta operação.', 405);
            return false;
        }
        return true;
    }

    public function etapa($id = null)
    {
        if (! $this->hasAnyPermission(['eOs'])) {
            show_error('Sem permissão para atualizar contatos.', 403);
            return;
        }
        if (! $this->post()) {
            return;
        }
        $lead = $this->contato($id);
        $etapa = $this->input->post('etapa');
        $motivo = $this->input->post('motivo_perda');
        $versao = $this->input->post('versao');
        try {
            if (! is_string($etapa) || ! is_string($motivo) || ! is_string($versao) || ! ctype_digit($versao)) {
                throw new DomainException('Confira os dados da etapa.');
            }
            $this->leads_model->etapa((int) $lead->id, $etapa, trim($motivo), (int) $versao);
            log_info('Atualizou etapa do contato #' . $lead->id);
            return redirect('leads/visualizar/' . $lead->id);
        } catch (DomainException $e) {
            $this->output->set_status_header(422);
            return $this->tela($lead, $e->getMessage());
        }
    }

    public function converter($id = null)
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aOs')
            || ! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')
            || ! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCliente')) {
            show_error('Sem permissão para converter contatos em clientes e atendimentos.', 403);
            return;
        }
        if (! $this->post()) {
            return;
        }
        $lead = $this->contato($id);
        try {
            $ids = [];
            foreach (['cliente', 'profissional', 'versao'] as $campo) {
                $valor = $this->input->post($campo);
                if (! is_string($valor) || ! ctype_digit($valor)) {
                    throw new DomainException('Escolha a cliente e a profissional, depois confira o orçamento.');
                }
                $ids[$campo] = (int) $valor;
            }
            $os = $this->leads_model->converter((int) $lead->id, $ids['cliente'], $ids['profissional'], $ids['versao']);
            log_info('Converteu contato #' . $lead->id . ' em atendimento #' . $os);
            $this->session->set_flashdata('success', 'Atendimento criado. Registre o pagamento recebido e agende o horário.');
            return redirect('os/visualizar/' . $os);
        } catch (DomainException $e) {
            $this->output->set_status_header(422);
            return $this->tela($lead, $e->getMessage());
        }
    }

    public function imprimir($id = null)
    {
        if (! $this->hasAnyPermission(['vOs'])) {
            show_error('Sem permissão para visualizar orçamentos.', 403);
            return;
        }
        $lead = $this->contato($id);
        $this->load->model('mapos_model');
        return $this->load->view('leads/imprimir', ['lead' => $lead, 'itens' => json_decode($lead->itens_json, true), 'emitente' => $this->mapos_model->getEmitente()]);
    }
}
