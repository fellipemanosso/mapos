<?php

class Fichas extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'fichas']);
        $this->load->model(['fichas_model', 'os_model']);
        $this->output->set_header('Cache-Control: no-store, private');
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para acessar fichas e retornos.', 403);
            return;
        }
        $filtros = listagemFiltros(['status' => ['pendente', 'concluido']], $this->input->get());
        $ate = fichaData($this->input->get('ate')) ?? date('Y-m-d');
        $status = $filtros['status'] ?? 'pendente';
        $filtros += ['ate' => $ate];
        $offset = max(0, (int) $this->uri->segment(3));
        $this->data += ['retornos' => $this->fichas_model->retornos($status, $ate, $offset, (int) $this->data['configuration']['per_page']),
            'status' => $status, 'ate' => $ate,
            'paginacao' => $this->paginacao(site_url('fichas/index'), $this->fichas_model->contarRetornos($status, $ate), $offset, null, $filtros)];
        $this->data['view'] = 'fichas/index';
        return $this->layout();
    }

    private function id($id): int
    {
        if (! is_scalar($id) || ! ctype_digit((string) $id) || (int) $id < 1) {
            show_404();
            exit;
        }
        return (int) $id;
    }

    private function ficha($id): object
    {
        $ficha = $this->fichas_model->ficha($this->id($id));
        if (! $ficha) {
            show_404();
            exit;
        }
        return $ficha;
    }

    private function os($id): object
    {
        $os = $this->os_model->getById($this->id($id));
        if (! $os) {
            show_404();
            exit;
        }
        return $os;
    }

    private function post(): bool
    {
        if ($this->input->method() !== 'post') {
            show_error('Use o formulário para esta operação.', 405);
            return false;
        }
        return true;
    }

    private function chave($valor): bool
    {
        return is_string($valor) && preg_match('/\A[a-f0-9]{32}\z/', $valor) === 1;
    }

    public function atendimento($id = null)
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para acessar fichas e retornos.', 403);
            return;
        }
        $os = $this->os($id);
        $this->data += ['os' => $os, 'fichas' => $this->fichas_model->doAtendimento((int) $os->idOs),
            'historico' => $this->fichas_model->historico((int) $os->clientes_id, $os->dependente_id ? (int) $os->dependente_id : null)];
        $this->data['view'] = 'fichas/atendimento';
        return $this->layout();
    }

    public function modelo($id = null)
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para configurar modelos de fichas.', 403);
            return;
        }
        $modelo = $id !== null ? $this->fichas_model->modelo($this->id($id)) : null;
        if ($id !== null && ! $modelo) {
            show_404();
            return;
        }
        $valores = $modelo ? ['nome' => $modelo->nome, 'campos' => implode("\n", json_decode($modelo->campos_json, true)), 'termo' => $modelo->termo, 'versao' => $modelo->versao] : [];
        $erros = [];
        if ($this->input->method() === 'post') {
            $valores = $this->input->post();
            [$dados, $erros] = fichaModeloDados($valores);
            if (! is_string($valores['versao'] ?? null) || ! ctype_digit($valores['versao'])) {
                $erros['_geral'] = 'Reabra o formulário.';
            }
            if ($erros === []) {
                try {
                    $salvo = $this->fichas_model->salvarModelo($modelo ? (int) $modelo->id : null, $dados, (int) $valores['versao']);
                    log_info('Salvou modelo de ficha #' . $salvo);
                    $this->session->set_flashdata('success', 'Modelo salvo. Fichas anteriores mantêm os campos e o termo originais.');
                    return redirect('fichas/modelo/' . $salvo);
                } catch (DomainException $e) {
                    $erros['_geral'] = $e->getMessage();
                }
            }
            $this->output->set_status_header(422);
        }
        $this->data += ['modelos' => $this->fichas_model->modelos(), 'modelo' => $modelo, 'valores' => $valores, 'erros' => $erros];
        $this->data['view'] = 'fichas/modelo';
        return $this->layout();
    }

    public function criar($id = null)
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para criar fichas.', 403);
            return;
        }
        $os = $this->os($id);
        $post = $this->input->method() === 'post' ? $this->input->post() : [];
        $modeloId = $post['modelo_id'] ?? $this->input->get('modelo');
        $modelo = is_scalar($modeloId) && ctype_digit((string) $modeloId) ? $this->fichas_model->modelo((int) $modeloId) : null;
        $erros = [];
        if ($post !== []) {
            $servico = $post['servico'] ?? null;
            $versao = $post['versao'] ?? null;
            [$respostas, $erros] = fichaRespostas($post, $modelo ? json_decode($modelo->campos_json, true) : []);
            if (! $modelo || ! is_string($servico) || ! ctype_digit($servico) || ! is_string($versao) || ! ctype_digit($versao) || ! $this->chave($post['chave'] ?? null)) {
                $erros['_geral'] = 'Escolha o modelo e o serviço e reabra o formulário se necessário.';
            }
            if ($modelo && (int) $versao !== (int) $modelo->versao) {
                $erros['_geral'] = 'O modelo foi alterado. Carregue o modelo novamente antes de preencher as respostas.';
                $modelo = null;
            }
            foreach (['cliente_esperado', 'dependente_esperado'] as $campo) {
                if (! is_string($post[$campo] ?? null) || ! ctype_digit($post[$campo])) {
                    $erros['_geral'] = 'Reabra o atendimento antes de preencher a ficha.';
                }
            }
            if ($erros === []) {
                try {
                    $ficha = $this->fichas_model->criar((int) $os->idOs, (int) $servico, (int) $modelo->id, (int) $versao, $respostas, (int) $this->session->userdata('id_admin'), $post['chave'], (int) $post['cliente_esperado'], (int) $post['dependente_esperado'] ?: null);
                    log_info('Criou ficha #' . $ficha . ' do atendimento #' . $os->idOs);
                    return redirect('fichas/visualizar/' . $ficha);
                } catch (DomainException $e) {
                    $erros['_geral'] = $e->getMessage();
                }
            }
            $this->output->set_status_header(422);
        }
        $this->data += ['os' => $os, 'modelo' => $modelo, 'modelos' => $this->fichas_model->modelos(), 'valores' => $post, 'erros' => $erros,
            'servicos' => $this->db->select('servicos_id, servico')->distinct()->where('os_id', (int) $os->idOs)->get('servicos_os')->result(),
            'chave' => $this->chave($post['chave'] ?? null) ? $post['chave'] : bin2hex(random_bytes(16))];
        $this->data['view'] = 'fichas/criar';
        return $this->layout();
    }

    public function visualizar($id = null)
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para acessar fichas.', 403);
            return;
        }
        $ficha = $this->ficha($id);
        log_info('Consultou ficha #' . $ficha->id);
        return $this->tela($ficha);
    }

    private function tela(object $ficha, array $erros = [], array $valores = [])
    {
        $this->data += ['ficha' => $ficha, 'erros' => $erros, 'valores' => $valores,
            'evolucoes' => $this->fichas_model->evolucoes((int) $ficha->id), 'arquivos' => $this->fichas_model->arquivos((int) $ficha->id),
            'historico' => $this->fichas_model->historico((int) $ficha->clientes_id, $ficha->dependente_id ? (int) $ficha->dependente_id : null),
            'agendas' => $this->db->select('id, titulo, inicio')->where('os_id', (int) $ficha->os_id)->where('cancelado', 0)->where('fim <=', date('Y-m-d H:i:s'))->order_by('inicio', 'desc')->get('agenda')->result(),
            'chave' => $this->chave($valores['chave'] ?? null) ? $valores['chave'] : bin2hex(random_bytes(16))];
        $this->data['view'] = 'fichas/visualizar';
        return $this->layout();
    }

    public function evolucao($id = null)
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para registrar evolução.', 403);
            return;
        }
        if (! $this->post()) {
            return;
        }
        $ficha = $this->ficha($id);
        $post = $this->input->post();
        [$dados, $erros] = fichaEvolucaoDados($post, date('Y-m-d H:i:s'));
        if (! $this->chave($post['chave'] ?? null)) {
            $erros['_geral'] = 'Reabra o formulário antes de salvar.';
        }
        if ($erros === []) {
            try {
                $registro = $this->fichas_model->evoluir((int) $ficha->id, $dados, (int) $this->session->userdata('id_admin'), $post['chave']);
                log_info('Registrou evolução #' . $registro . ' da ficha #' . $ficha->id);
                $this->session->set_flashdata('success', 'Registro preservado no histórico da ficha.');
                return redirect('fichas/visualizar/' . $ficha->id);
            } catch (DomainException $e) {
                $erros['_geral'] = $e->getMessage();
            }
        }
        $this->output->set_status_header(422);
        return $this->tela($ficha, $erros, $post);
    }

    public function retorno($id = null)
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para acompanhar retornos.', 403);
            return;
        }
        if (! $this->post()) {
            return;
        }
        $ficha = $this->ficha($id);
        $post = $this->input->post();
        try {
            if (! is_string($post['registro'] ?? null) || ! ctype_digit($post['registro']) || ! is_string($post['status'] ?? null) || ! is_string($post['nota'] ?? null)) {
                throw new DomainException('Dados do retorno inválidos.');
            }
            $this->fichas_model->retorno((int) $ficha->id, (int) $post['registro'], $post['status'], trim($post['nota']), (int) $this->session->userdata('id_admin'));
            log_info('Atualizou retorno da ficha #' . $ficha->id);
            return redirect('fichas/visualizar/' . $ficha->id);
        } catch (DomainException $e) {
            $this->output->set_status_header(422);
            return $this->tela($ficha, ['_geral' => $e->getMessage()]);
        }
    }

    public function anexar($id = null)
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para anexar documentos da ficha.', 403);
            return;
        }
        if (! $this->post()) {
            return;
        }
        $ficha = $this->ficha($id);
        [$dados, $erros] = fichaArquivoDados($this->input->post(), date('Y-m-d'));
        if ($erros !== []) {
            $this->session->set_flashdata('error', implode(' ', $erros));
            return redirect('fichas/visualizar/' . $ficha->id);
        }
        $pasta = armazenamentoPasta('fichas/' . (int) $ficha->id);
        if ($pasta === null) {
            show_error('Não foi possível preparar o armazenamento.', 500);
            return;
        }
        $this->load->library('upload', armazenamentoConfigUpload($pasta, $dados['tipo'] === 'foto' ? ['jpg', 'jpeg', 'png'] : ['pdf', 'jpg', 'jpeg', 'png']));
        if (! armazenamentoReceber($this->upload)) {
            $this->session->set_flashdata('error', 'Envie PDF, JPG ou PNG dentro do limite de ' . tamanhoDeArquivo(armazenamentoLimiteKb()) . '. Para fotos, envie JPG ou PNG.');
            return redirect('fichas/visualizar/' . $ficha->id);
        }
        $arquivo = $this->upload->data();
        $erro = armazenamentoValidarEnvio($arquivo);
        $relativo = armazenamentoRelativo($arquivo['full_path']);
        if ($erro !== null || $relativo === null) {
            $this->session->set_flashdata('error', $erro ?? 'Caminho de arquivo inválido.');
            return redirect('fichas/visualizar/' . $ficha->id);
        }
        $ok = $this->fichas_model->anexar((int) $ficha->id, $dados + ['nome' => mb_substr(basename($arquivo['client_name']), 0, 255), 'caminho' => $relativo,
            'usuarios_id' => (int) $this->session->userdata('id_admin'), 'criado_em' => date('Y-m-d H:i:s')]);
        if (! $ok) {
            unlink($arquivo['full_path']);
        } else {
            log_info('Anexou documento à ficha #' . $ficha->id);
        }
        $this->session->set_flashdata($ok ? 'success' : 'error', $ok ? 'Documento anexado à ficha com acesso restrito.' : 'Não foi possível registrar o documento.');
        return redirect('fichas/visualizar/' . $ficha->id);
    }

    public function arquivo($ficha = null, $id = null)
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para acessar documentos da ficha.', 403);
            return;
        }
        $registro = $this->fichas_model->arquivo($this->id($ficha), $this->id($id));
        $arquivo = $registro ? armazenamentoLocalizar($registro->caminho) : null;
        if (! $arquivo) {
            show_404();
            return;
        }
        log_info('Acessou documento #' . $registro->id . ' da ficha #' . $registro->ficha_id);
        armazenamentoEnviar($arquivo, $registro->nome, $registro->tipo !== 'foto' || $this->input->get('visualizar') !== '1');
    }

    public function termo($id = null)
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para imprimir termos.', 403);
            return;
        }
        $ficha = $this->ficha($id);
        if (trim($ficha->termo) === '') {
            show_error('Esta ficha não possui texto de termo configurado.', 422);
            return;
        }
        return $this->load->view('fichas/termo', ['ficha' => $ficha]);
    }
}
