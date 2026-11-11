<?php

class Agenda extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('agenda_model');
        $this->load->helper('agenda');
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['vOs'])) {
            show_error('Sem permissão para visualizar a agenda.', 403);
            return;
        }
        $data = $this->input->get('data');
        $dia = is_string($data) ? DateTimeImmutable::createFromFormat('!Y-m-d', $data) : false;
        if (! $dia || $dia->format('Y-m-d') !== $data) {
            $dia = new DateTimeImmutable('today');
        }
        $modo = in_array($this->input->get('modo'), ['dia', 'semana', 'calendario'], true) ? $this->input->get('modo') : 'calendario';
        [$inicio, $fim] = agendaIntervalo($dia, $modo);
        $this->data['inicio'] = $inicio;
        $this->data['selecionado'] = $dia;
        $this->data['fim'] = $fim;
        $this->data['modo'] = $modo;
        $this->data['eventos'] = $this->agenda_model->listar($inicio->format('Y-m-d H:i:s'), $fim->format('Y-m-d H:i:s'));
        $this->data['pode_editar'] = $this->permite('eOs');
        if ($this->data['pode_editar']) {
            $this->data['topbar_acao'] = ['label' => 'Agendar atendimento', 'icon' => 'plus', 'href' => site_url('agenda/formulario')];
        }
        $this->data['view'] = 'agenda/index';
        return $this->layout();
    }

    public function formulario($id = null)
    {
        if (! $this->hasAnyPermission(['eOs'])) {
            show_error('Sem permissão para editar a agenda.', 403);
            return;
        }
        $evento = $id === null ? null : $this->agenda_model->getById((int) $id);
        if ($id !== null && (! ctype_digit((string) $id) || ! $evento || $evento->cancelado)) {
            show_404();
            return;
        }
        $servicos = $this->db->order_by('nome')->get('servicos')->result();
        $profissionais = $this->db->where('situacao', 1)->get('usuarios')->result();
        $atendimentos = $this->db->select('os.idOs, clientes.nomeCliente, dependentes.nome as beneficiario')
            ->from('os')->join('clientes', 'clientes.idClientes = os.clientes_id')
            ->join('dependentes', 'dependentes.id = os.dependente_id', 'left')->where('os.status !=', 'Cancelado')->order_by('os.idOs', 'desc')->get()->result();
        $valores = $evento ? (array) $evento : [
            'tipo' => $this->input->get('tipo') === 'bloqueio' ? 'bloqueio' : 'atendimento', 'os_id' => is_string($this->input->get('os')) ? $this->input->get('os') : '',
            'usuarios_id' => (string) $this->session->userdata('id_admin'),
            'titulo' => '', 'inicio' => date('Y-m-d') . 'T09:00', 'duracao_min' => '', 'deslocamento_min' => '0', 'intervalo_min' => '0', 'servicos' => [],
        ];
        if ($evento) {
            $valores['inicio'] = substr(str_replace(' ', 'T', $evento->inicio), 0, 16);
            $valores['servicos'] = array_column(json_decode($evento->servicos_json, true) ?: [], 'id');
        } elseif (ctype_digit((string) $valores['os_id'])) {
            $valores['servicos'] = array_column($this->db->select('servicos_id')->where('os_id', (int) $valores['os_id'])->get('servicos_os')->result_array(), 'servicos_id');
        }
        $erros = [];
        if ($this->input->method() === 'post') {
            $post = $this->input->post();
            $texto = static fn ($campo) => is_string($post[$campo] ?? null) ? trim($post[$campo]) : '';
            foreach (['tipo', 'os_id', 'usuarios_id', 'titulo', 'inicio', 'duracao_min', 'deslocamento_min', 'intervalo_min'] as $campo) {
                $valores[$campo] = $texto($campo);
            }
            $ids = is_array($post['servicos'] ?? null) ? array_values(array_unique(array_filter($post['servicos'], static fn ($v) => is_string($v) && ctype_digit($v)))) : [];
            $valores['servicos'] = $ids;
            $selecionados = array_values(array_filter($servicos, static fn ($s) => in_array((string) $s->idServicos, $ids, true)));
            $bloqueio = $texto('tipo') === 'bloqueio';
            if (! in_array($texto('tipo'), ['atendimento', 'bloqueio'], true)) {
                $erros['tipo'] = 'Escolha o tipo de reserva.';
            }
            if (! in_array($texto('usuarios_id'), array_map('strval', array_column($profissionais, 'idUsuarios')), true)) {
                $erros['usuarios_id'] = 'Escolha uma profissional ativa.';
            }
            if (! $bloqueio && ! in_array($texto('os_id'), array_map('strval', array_column($atendimentos, 'idOs')), true)) {
                $erros['os_id'] = 'Escolha um atendimento. Cadastre-o primeiro em Atendimentos.';
            }
            if (! $bloqueio && (count($selecionados) !== count($ids) || $ids === [])) {
                $erros['servicos'] = 'Escolha ao menos um serviço do catálogo.';
            }
            if ($bloqueio && ($texto('titulo') === '' || mb_strlen($texto('titulo')) > 255)) {
                $erros['titulo'] = 'Informe o motivo do bloqueio, com até 255 caracteres.';
            }
            foreach (['duracao_min', 'deslocamento_min', 'intervalo_min'] as $campo) {
                if ($texto($campo) !== '' && ! ctype_digit($texto($campo))) {
                    $erros[$campo] = 'Use minutos inteiros, sem valores negativos.';
                }
            }
            $duracao = $texto('duracao_min') === '' ? (int) array_sum(array_column($selecionados, 'duracao_min')) : (int) $texto('duracao_min');
            $periodo = agendaPeriodo($texto('inicio'), $duracao, (int) $texto('deslocamento_min'), (int) $texto('intervalo_min'));
            if ($periodo === null) {
                $erros['inicio'] = 'Informe data e hora válidas, duração de 1 a 1.440 minutos e intervalos de até 720 minutos.';
            }
            if ($erros === []) {
                $dados = $periodo + [
                    'os_id' => $bloqueio ? null : (int) $texto('os_id'), 'usuarios_id' => (int) $texto('usuarios_id'),
                    'tipo' => $texto('tipo'), 'titulo' => $bloqueio ? $texto('titulo') : implode(' + ', array_column($selecionados, 'nome')),
                    'duracao_min' => $duracao, 'deslocamento_min' => (int) $texto('deslocamento_min'), 'intervalo_min' => (int) $texto('intervalo_min'),
                    'servicos_json' => json_encode(array_map(static fn ($s) => ['id' => (int) $s->idServicos, 'nome' => $s->nome, 'duracao_min' => (int) $s->duracao_min], $selecionados)),
                ];
                if (mb_strlen($dados['titulo']) > 255) {
                    $dados['titulo'] = mb_substr($dados['titulo'], 0, 252) . '...';
                }
                try {
                    $salvo = $this->agenda_model->reservar($dados, $evento ? (int) $evento->id : null);
                    if ($salvo) {
                        $this->session->set_flashdata('success', 'Horário reservado.');
                        return redirect('agenda?modo=dia&data=' . substr($dados['inicio'], 0, 10));
                    }
                    $erros['_geral'] = 'Não foi possível reservar. Tente novamente.';
                } catch (DomainException $e) {
                    $erros['_geral'] = $e->getMessage();
                }
            }
        }
        $this->data += ['evento' => $evento, 'valores' => $valores, 'erros' => $erros, 'servicos' => $servicos, 'profissionais' => $profissionais, 'atendimentos' => $atendimentos];
        $this->data['view'] = 'agenda/formulario';
        return $this->layout();
    }

    public function cancelar($id = null)
    {
        if (! $this->hasAnyPermission(['eOs'])) {
            show_error('Sem permissão para editar a agenda.', 403);
            return;
        }
        if ($this->input->method() !== 'post' || ! ctype_digit((string) $id)) {
            show_error('Método não permitido.', 405);
            return;
        }
        if (! $this->agenda_model->getById((int) $id)) {
            show_404();
            return;
        }
        $this->db->where('id', (int) $id)->update('agenda', ['cancelado' => 1]);
        $this->session->set_flashdata('success', 'Reserva cancelada. O atendimento e seus pagamentos foram preservados.');
        return redirect('agenda');
    }
}
