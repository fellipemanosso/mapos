<?php

class Pacotes extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('pacotes_model');
        $this->load->helper(['form', 'pacotes', 'financeiro']);
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['vOs'])) {
            show_error('Sem permissão para visualizar pacotes.', 403);
            return;
        }
        $pesquisa = $this->input->get('pesquisa');
        $pesquisa = is_string($pesquisa) ? mb_substr(trim($pesquisa), 0, 100) : '';
        $offset = max(0, (int) $this->uri->segment(3));
        $pacotes = $this->pacotes_model->listar($pesquisa, $offset, (int) $this->data['configuration']['per_page']);
        foreach ($pacotes as $pacote) {
            $pacote->progresso = pacoteProgresso($this->pacotes_model->sessoes((int) $pacote->id));
        }
        if ($this->permite('aOs') && $this->permite('eOs')) {
            $this->data['topbar_acao'] = ['label' => 'Novo pacote', 'icon' => 'plus', 'href' => site_url('pacotes/formulario')];
        }
        $this->data += ['pacotes' => $pacotes, 'pesquisa' => $pesquisa, 'offset' => $offset, 'paginacao' => $this->paginacao(site_url('pacotes/index'), $this->pacotes_model->contar($pesquisa), $offset, null, ['pesquisa' => $pesquisa])];
        $this->data['view'] = 'pacotes/index';
        return $this->layout();
    }

    public function vincular($id = null)
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aOs')
            || ! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            show_error('Sem permissão para criar sessões de pacote.', 403);
            return;
        }
        $os = is_scalar($id) && ctype_digit((string) $id) ? $this->db->where('idOs', (int) $id)->get('os')->row() : null;
        if (! $os) {
            show_404();
            return;
        }
        $erro = null;
        if ($this->input->method() === 'post') {
            $ids = $this->input->post('servicos');
            $chave = $this->input->post('chave');
            try {
                if (! is_array($ids) || array_filter($ids, static fn ($v) => ! is_string($v) || ! ctype_digit($v)) || ! is_string($chave)) {
                    throw new DomainException('Escolha os serviços que geram sessões.');
                }
                $pacote = $this->pacotes_model->vincular((int) $os->idOs, $ids, $chave);
                log_info('Criou sessões do atendimento #' . $os->idOs);
                return redirect('pacotes/visualizar/' . $pacote);
            } catch (DomainException $e) {
                $erro = $e->getMessage();
                $this->output->set_status_header(422);
            }
        }
        $this->data += ['os' => $os, 'itens' => $this->pacotes_model->itensDoAtendimento((int) $os->idOs), 'erro' => $erro, 'chave' => bin2hex(random_bytes(16))];
        $this->data['view'] = 'pacotes/vincular';
        return $this->layout();
    }

    public function formulario()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aOs')
            || ! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            show_error('É preciso poder criar e editar atendimentos para cadastrar pacotes.', 403);
            return;
        }
        $servicos = $this->db->where('duracao_min >', 0)->order_by('nome')->get('servicos')->result();
        $post = $this->input->method() === 'post' ? $this->input->post() : [];
        $erros = [];
        if ($this->input->method() === 'post') {
            [$composicao, $erros] = pacoteDoFormulario($post, $servicos);
            $id = static fn ($campo) => is_string($post[$campo] ?? null) && ctype_digit($post[$campo]) ? (int) $post[$campo] : 0;
            if (! $id('clientes_id')) {
                $erros['clientes_id'] = 'Escolha uma cliente responsável.';
            }
            if (! $id('usuarios_id')) {
                $erros['usuarios_id'] = 'Escolha uma profissional.';
            }
            if (($post['dependente_id'] ?? '') !== '' && ! $id('dependente_id')) {
                $erros['dependente_id'] = 'Escolha um bebê ou dependente da lista.';
            }
            if (! is_string($post['chave'] ?? null) || ! preg_match('/^[a-f0-9]{32}$/D', $post['chave'])) {
                $erros['_geral'] = 'Recarregue a página antes de cadastrar o pacote.';
            }
            if ($erros === []) {
                try {
                    $pacote = $this->pacotes_model->criar($composicao, $id('clientes_id'), $id('dependente_id') ?: null, $id('usuarios_id'), $post['chave']);
                    log_info('Criou pacote #' . $pacote);
                    $this->session->set_flashdata('success', 'Pacote criado. As sessões estão disponíveis para agendamento.');
                    return redirect('pacotes/visualizar/' . $pacote);
                } catch (DomainException $e) {
                    $erros['_geral'] = $e->getMessage();
                }
            }
        }
        $this->data += [
            'servicos' => $servicos, 'erros' => $erros, 'valores' => $post,
            'clientes' => $this->db->order_by('nomeCliente')->get('clientes')->result(),
            'dependentes' => $this->db->select('dependentes.*, clientes.nomeCliente')->from('dependentes')->join('clientes', 'clientes.idClientes = dependentes.clientes_id')->order_by('dependentes.nome')->get()->result(),
            'profissionais' => $this->db->where('situacao', 1)->order_by('nome')->get('usuarios')->result(),
            'chave' => bin2hex(random_bytes(16)),
        ];
        $this->data['view'] = 'pacotes/formulario';
        return $this->layout();
    }

    public function visualizar($id = null)
    {
        if (! $this->hasAnyPermission(['vOs'])) {
            show_error('Sem permissão para visualizar pacotes.', 403);
            return;
        }
        $pacote = ctype_digit((string) $id) ? $this->pacotes_model->getById((int) $id) : null;
        if (! $pacote) {
            show_404();
            return;
        }
        $sessoes = $this->pacotes_model->sessoes((int) $id);
        $this->data += ['pacote' => $pacote, 'sessoes' => $sessoes, 'progresso' => pacoteProgresso($sessoes), 'pode_editar' => $this->permite('eOs') && $pacote->status !== 'Cancelado'];
        $this->data['view'] = 'pacotes/visualizar';
        return $this->layout();
    }

    public function realizar($id = null)
    {
        if (! $this->hasAnyPermission(['eOs'])) {
            show_error('Sem permissão para registrar sessões.', 403);
            return;
        }
        $sessao = $this->input->post('sessao');
        $acao = $this->input->post('acao');
        if ($this->input->method() !== 'post' || ! ctype_digit((string) $id) || ! is_string($sessao) || ! ctype_digit($sessao) || ! in_array($acao, ['realizar', 'reabrir'], true)) {
            show_error('Operação inválida.', 422);
            return;
        }
        try {
            $this->pacotes_model->concluirSessao((int) $id, (int) $sessao, $acao === 'realizar');
            log_info('Atualizou sessão #' . $sessao . ' do pacote #' . $id . ': ' . $acao);
            $this->session->set_flashdata('success', $acao === 'realizar' ? 'Sessão realizada. O saldo de sessões foi atualizado.' : 'Registro reaberto. A sessão voltou às restantes.');
        } catch (DomainException $e) {
            $this->session->set_flashdata('error', $e->getMessage());
        }
        return redirect('pacotes/visualizar/' . (int) $id);
    }
}
