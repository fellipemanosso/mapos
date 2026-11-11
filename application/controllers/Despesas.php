<?php

class Despesas extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('despesas_model');
        $this->load->helper(['form', 'financeiro', 'despesas']);
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['vLancamento'])) {
            show_error('Sem permissão para visualizar despesas.', 403);
            return;
        }
        $filtros = listagemFiltros(['pesquisa' => 'texto', 'categoria' => array_keys(DESPESAS_CATEGORIAS)], $this->input->get());
        $offset = max(0, (int) $this->uri->segment(3));
        $grupos = $this->despesas_model->listar($filtros['pesquisa'] ?? '', $filtros['categoria'] ?? '', (int) $this->data['configuration']['per_page'], $offset);
        foreach ($grupos as $grupo) {
            $grupo->resumo = $this->despesas_model->resumo((int) $grupo->id);
        }
        if ($this->permite('aLancamento')) {
            $this->data['topbar_acao'] = ['label' => 'Nova despesa', 'icon' => 'plus', 'href' => site_url('despesas/formulario')];
        }
        $this->data += ['grupos' => $grupos, 'filtros' => $filtros, 'paginacao' => $this->paginacao(site_url('despesas/index'), $this->despesas_model->contar($filtros['pesquisa'] ?? '', $filtros['categoria'] ?? ''), $offset, null, $filtros)];
        $this->data['view'] = 'despesas/index';
        return $this->layout();
    }

    public function formulario()
    {
        if (! $this->hasAnyPermission(['aLancamento'])) {
            show_error('Sem permissão para adicionar despesas.', 403);
            return;
        }
        $valores = $this->input->method() === 'post' ? $this->input->post() : [];
        $erros = [];
        if ($this->input->method() === 'post') {
            [$grupo, $linhas, $erros] = despesaDoFormulario($valores, date('Y-m-d'), ($this->data['configuration']['control_baixa'] ?? '0') == '1');
            if ($erros === []) {
                try {
                    $id = $this->despesas_model->criar($grupo, $linhas, (int) $this->session->userdata('id_admin'));
                    log_info('Criou despesa #' . $id . ' com ' . count($linhas) . ' lançamento(s).');
                    $this->session->set_flashdata('success', 'Despesa registrada. As parcelas ou ocorrências já estão no financeiro.');
                    return redirect('despesas/visualizar/' . $id);
                } catch (DomainException $e) {
                    $erros['_geral'] = $e->getMessage();
                }
            }
        }
        $this->data += ['valores' => $valores, 'erros' => $erros, 'chave' => bin2hex(random_bytes(16))];
        $this->data['view'] = 'despesas/formulario';
        return $this->layout();
    }

    public function visualizar($id = null)
    {
        if (! $this->hasAnyPermission(['vLancamento'])) {
            show_error('Sem permissão para visualizar despesas.', 403);
            return;
        }
        $grupo = ctype_digit((string) $id) ? $this->despesas_model->getById((int) $id) : null;
        if (! $grupo) {
            show_404();
            return;
        }
        $this->data += ['grupo' => $grupo, 'resumo' => $this->despesas_model->resumo((int) $id), 'linhas' => $this->despesas_model->linhas((int) $id),
            'anexos' => $this->db->where('grupo_id', (int) $id)->order_by('id', 'desc')->get('despesa_anexos')->result(), 'pode_editar' => $this->permite('eLancamento')];
        $this->data['view'] = 'despesas/visualizar';
        return $this->layout();
    }

    public function anexar($id = null)
    {
        if (! $this->hasAnyPermission(['eLancamento'])) {
            show_error('Sem permissão para anexar comprovantes.', 403);
            return;
        }
        if ($this->input->method() !== 'post') {
            show_error('Use o formulário de comprovantes.', 405);
            return;
        }
        $grupo = ctype_digit((string) $id) ? $this->despesas_model->getById((int) $id) : null;
        if (! $grupo) {
            show_404();
            return;
        }
        $pasta = armazenamentoPasta('despesas/' . (int) $id);
        if ($pasta === null) {
            show_error('Não foi possível preparar a pasta do comprovante.', 500);
            return;
        }
        $this->load->library('upload', armazenamentoConfigUpload($pasta, ['pdf', 'jpg', 'jpeg', 'png']));
        if (! armazenamentoReceber($this->upload)) {
            $this->session->set_flashdata('error', 'Envie PDF, JPG ou PNG de até ' . tamanhoDeArquivo(armazenamentoLimiteKb()) . '.');
            return redirect('despesas/visualizar/' . (int) $id);
        }
        $arquivo = $this->upload->data();
        $erro = armazenamentoValidarEnvio($arquivo);
        $relativo = armazenamentoRelativo($arquivo['full_path']);
        if ($erro !== null || $relativo === null) {
            $this->session->set_flashdata('error', $erro ?? 'Caminho de arquivo inválido.');
            return redirect('despesas/visualizar/' . (int) $id);
        }
        $salvou = $this->db->insert('despesa_anexos', ['grupo_id' => (int) $id, 'nome' => mb_substr(basename($arquivo['client_name']), 0, 255), 'caminho' => $relativo, 'criado_em' => date('Y-m-d H:i:s')]);
        if (! $salvou) {
            unlink($arquivo['full_path']);
        }
        $this->session->set_flashdata($salvou ? 'success' : 'error', $salvou ? 'Comprovante anexado.' : 'Não foi possível registrar o comprovante.');
        return redirect('despesas/visualizar/' . (int) $id);
    }

    public function comprovante($id = null)
    {
        if (! $this->hasAnyPermission(['vLancamento'])) {
            show_error('Sem permissão para visualizar comprovantes.', 403);
            return;
        }
        $anexo = ctype_digit((string) $id) ? $this->db->where('id', (int) $id)->get('despesa_anexos')->row() : null;
        $arquivo = $anexo ? armazenamentoLocalizar($anexo->caminho) : null;
        if (! $arquivo) {
            show_404();
            return;
        }
        armazenamentoEnviar($arquivo, $anexo->nome, true);
    }
}
