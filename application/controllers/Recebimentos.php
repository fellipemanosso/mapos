<?php

class Recebimentos extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'os', 'financeiro', 'recebimentos']);
        $this->load->model(['os_model', 'recebimentos_model']);
        $this->data['menuOs'] = 'OS';
    }

    public function index($id = null)
    {
        if (! $this->hasAnyPermission(['vOs'])) {
            show_error('Sem permissão para visualizar atendimentos.', 403);
            return;
        }
        return $this->tela($this->atendimento($id));
    }

    private function atendimento($id): object
    {
        $os = ctype_digit((string) $id) ? $this->os_model->getById((int) $id) : null;
        if (! $os) {
            show_404();
            exit;
        }
        if (! $this->recebimentos_model->disponivel()) {
            show_error('Atualize o banco de dados antes de usar os recebimentos.', 503);
            exit;
        }
        return $os;
    }

    private function tela(object $os, array $erros = [], string $formulario = '')
    {
        $total = $this->recebimentos_model->total($os);
        $recebido = $this->recebimentos_model->recebido((int) $os->idOs);
        $legado = (int) $os->faturado === 1 && ! (int) $os->financeiro_confirmado;
        $this->data += [
            'os' => $os, 'total' => $total, 'recebido' => $recebido, 'saldo' => max(0, $total - $recebido),
            'historico' => $this->recebimentos_model->historico((int) $os->idOs),
            'erros' => $erros, 'formulario' => $formulario, 'legado' => $legado,
            'editar_orcamento' => $this->permite('eOs') && ! (int) $os->faturado && $os->status !== 'Cancelado',
            'pode_receber' => $this->permite('eOs') && $this->permite('aLancamento') && ! $legado && $os->status !== 'Cancelado',
            'pode_estornar' => $this->permite('eOs') && $this->permite('eLancamento') && $os->status !== 'Cancelado',
            'chave' => bin2hex(random_bytes(16)),
        ];
        $this->data['view'] = 'recebimentos/index';
        return $this->layout();
    }

    public function orcamento($id = null)
    {
        if (! $this->hasAnyPermission(['eOs'])) {
            show_error('Sem permissão para editar o orçamento.', 403);
            return;
        }
        $this->somentePost();
        $os = $this->atendimento($id);
        $post = $this->input->post();
        $sinal = valorDecimal($post['sinal_previsto'] ?? null);
        $vencimento = dataIsoParaYmd($post['saldo_vencimento'] ?? null);
        $erros = [];
        if ($sinal === null) {
            $erros['sinal_previsto'] = 'Informe o sinal previsto, ou zero quando não houver.';
        }
        if ($vencimento === null) {
            $erros['saldo_vencimento'] = 'Informe o vencimento do saldo.';
        }
        if ($erros === []) {
            try {
                $this->recebimentos_model->orcamento((int) $os->idOs, $sinal, $vencimento);
                $this->session->set_flashdata('success', 'Condições do orçamento salvas.');
                return redirect('recebimentos/index/' . $os->idOs);
            } catch (DomainException $e) {
                $erros['_geral'] = $e->getMessage();
            }
        }
        return $this->tela($os, $erros, 'orcamento');
    }

    public function receber($id = null)
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')
            || ! $this->permission->checkPermission($this->session->userdata('permissao'), 'aLancamento')) {
            show_error('É preciso poder editar atendimentos e adicionar lançamentos.', 403);
            return;
        }
        $this->somentePost();
        $os = $this->atendimento($id);
        [$dados, $erros] = recebimentoDoFormulario($this->input->post(), date('Y-m-d'));
        if ($erros === []) {
            try {
                $this->recebimentos_model->receber((int) $os->idOs, $dados, (int) $this->session->userdata('id_admin'));
                log_info('Registrou recebimento no atendimento #' . $os->idOs);
                $this->session->set_flashdata('success', 'Recebimento registrado. Saldo e financeiro atualizados.');
                return redirect('recebimentos/index/' . $os->idOs);
            } catch (DomainException $e) {
                $erros['_geral'] = $e->getMessage();
            }
        }
        return $this->tela($os, $erros, 'receber');
    }

    public function estornar($id = null)
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')
            || ! $this->permission->checkPermission($this->session->userdata('permissao'), 'eLancamento')) {
            show_error('É preciso poder editar atendimentos e lançamentos.', 403);
            return;
        }
        $this->somentePost();
        $os = $this->atendimento($id);
        $pagamento = $this->input->post('pagamento');
        if (! is_string($pagamento) || ! ctype_digit($pagamento)) {
            show_error('Recebimento inválido.', 422);
            return;
        }
        try {
            $this->recebimentos_model->estornar((int) $os->idOs, (int) $pagamento, (int) $this->session->userdata('id_admin'));
            log_info('Estornou registro de recebimento #' . $pagamento . ' do atendimento #' . $os->idOs);
            $this->session->set_flashdata('success', 'Registro estornado. O saldo foi recalculado.');
            return redirect('recebimentos/index/' . $os->idOs);
        } catch (DomainException $e) {
            return $this->tela($os, ['_geral' => $e->getMessage()], 'estornar');
        }
    }

    private function somentePost(): void
    {
        if ($this->input->method() !== 'post') {
            show_error('Use o formulário para esta operação.', 405);
            exit;
        }
    }
}
