<?php

class Notasfiscais extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'notas_fiscais']);
        $this->load->model(['os_model', 'notas_fiscais_model']);
    }

    public function index($id = null)
    {
        if (! $this->hasAnyPermission(['vOs'])) {
            show_error('Sem permissão para visualizar notas fiscais.', 403);
            return;
        }
        $os = $this->atendimento($id);
        $this->data += ['os' => $os, 'notas' => $this->notas_fiscais_model->listar((int) $os->idOs), 'pode_editar' => $this->permite('eOs')];
        $this->data['view'] = 'notas_fiscais/index';
        return $this->layout();
    }

    private function atendimento($id): object
    {
        $os = is_scalar($id) && ctype_digit((string) $id) ? $this->os_model->getById((int) $id) : null;
        if (! $os) {
            show_404();
            exit;
        }
        return $os;
    }

    private function somentePost(): void
    {
        if ($this->input->method() !== 'post') {
            show_error('Use o formulário para esta operação.', 405);
            exit;
        }
    }

    public function solicitar($id = null)
    {
        if (! $this->hasAnyPermission(['eOs'])) {
            show_error('Sem permissão para atualizar notas fiscais.', 403);
            return;
        }
        $this->somentePost();
        $os = $this->atendimento($id);
        $valor = $this->input->post('solicitada');
        if (! in_array($valor, ['0', '1'], true)) {
            show_error('Informe se a nota fiscal foi solicitada.', 422);
            return;
        }
        $salvou = $this->notas_fiscais_model->solicitar((int) $os->idOs, $valor === '1');
        log_info('Atualizou solicitação de NF do atendimento #' . $os->idOs . ': ' . $valor);
        $this->session->set_flashdata($salvou ? 'success' : 'error', $salvou ? 'Solicitação de NF atualizada.' : 'Não foi possível salvar.');
        return redirect('notasfiscais/index/' . $os->idOs);
    }

    public function anexar($id = null)
    {
        if (! $this->hasAnyPermission(['eOs'])) {
            show_error('Sem permissão para anexar notas fiscais.', 403);
            return;
        }
        $this->somentePost();
        $os = $this->atendimento($id);
        [$dados, $erros] = notaFiscalDados($this->input->post(), date('Y-m-d'));
        if ($erros !== []) {
            $this->session->set_flashdata('error', implode(' ', $erros));
            return redirect('notasfiscais/index/' . $os->idOs);
        }
        $pasta = armazenamentoPasta('notas-fiscais/' . (int) $os->idOs);
        if ($pasta === null) {
            show_error('Não foi possível preparar a pasta da nota fiscal.', 500);
            return;
        }
        $this->load->library('upload', armazenamentoConfigUpload($pasta, ['pdf', 'jpg', 'jpeg', 'png']));
        if (! armazenamentoReceber($this->upload)) {
            $this->session->set_flashdata('error', 'Envie PDF, JPG ou PNG de até ' . tamanhoDeArquivo(armazenamentoLimiteKb()) . '.');
            return redirect('notasfiscais/index/' . $os->idOs);
        }
        $arquivo = $this->upload->data();
        $erro = armazenamentoValidarEnvio($arquivo);
        $relativo = armazenamentoRelativo($arquivo['full_path']);
        if ($erro !== null || $relativo === null) {
            $this->session->set_flashdata('error', $erro ?? 'Caminho de arquivo inválido.');
            return redirect('notasfiscais/index/' . $os->idOs);
        }
        $salvou = $this->notas_fiscais_model->registrar($dados + ['os_id' => (int) $os->idOs,
            'nome' => mb_substr(basename($arquivo['client_name']), 0, 255), 'caminho' => $relativo,
            'ativo' => 1, 'usuarios_id' => (int) $this->session->userdata('id_admin'), 'criado_em' => date('Y-m-d H:i:s')]);
        if (! $salvou) {
            unlink($arquivo['full_path']);
        } else {
            log_info('Anexou documento de NF ao atendimento #' . $os->idOs);
        }
        $this->session->set_flashdata($salvou ? 'success' : 'error', $salvou ? 'Nota anexada e disponível para a cliente.' : 'Não foi possível registrar a nota.');
        return redirect('notasfiscais/index/' . $os->idOs);
    }

    public function disponibilidade($id = null)
    {
        if (! $this->hasAnyPermission(['eOs'])) {
            show_error('Sem permissão para atualizar notas fiscais.', 403);
            return;
        }
        $this->somentePost();
        $os = $this->atendimento($id);
        $nota = $this->input->post('nota');
        $ativo = $this->input->post('ativo');
        if (! is_string($nota) || ! ctype_digit($nota) || ! in_array($ativo, ['0', '1'], true)) {
            show_error('Operação inválida.', 422);
            return;
        }
        if (! $this->notas_fiscais_model->alterarDisponibilidade((int) $os->idOs, (int) $nota, $ativo === '1')) {
            show_404();
            return;
        }
        log_info('Atualizou disponibilidade da NF #' . $nota . ' do atendimento #' . $os->idOs . ': ' . $ativo);
        $this->session->set_flashdata('success', $ativo === '1' ? 'Documento disponível para a cliente.' : 'Documento retirado da área da cliente. O histórico foi preservado.');
        return redirect('notasfiscais/index/' . $os->idOs);
    }

    public function arquivo($id = null)
    {
        if (! $this->hasAnyPermission(['vOs'])) {
            show_error('Sem permissão para visualizar notas fiscais.', 403);
            return;
        }
        $nota = is_scalar($id) && ctype_digit((string) $id) ? $this->notas_fiscais_model->arquivo((int) $id) : null;
        $arquivo = $nota ? armazenamentoLocalizar($nota->caminho) : null;
        if (! $arquivo) {
            show_404();
            return;
        }
        armazenamentoEnviar($arquivo, $nota->nome, true);
    }
}
