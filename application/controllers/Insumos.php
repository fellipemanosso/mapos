<?php

class Insumos extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'insumos', 'financeiro', 'fichas']);
        $this->load->model('insumos_model');
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['vInsumo'])) {
            show_error('Sem permissão para consultar insumos.', 403);
            return;
        }
        $filtros = listagemFiltros(['pesquisa' => 'texto'], $this->input->get());
        $pesquisa = $filtros['pesquisa'] ?? '';
        $offset = max(0, (int) $this->uri->segment(3));
        $this->data += ['itens' => $this->insumos_model->listar($pesquisa, $offset, (int) $this->data['configuration']['per_page']), 'pesquisa' => $pesquisa,
            'paginacao' => $this->paginacao(site_url('insumos/index'), $this->insumos_model->contar($pesquisa), $offset, null, $filtros)];
        if ($this->permite('eInsumo')) {
            $this->data['topbar_acao'] = ['label' => 'Novo insumo', 'icon' => 'plus', 'href' => site_url('insumos/formulario')];
        }
        $this->data['view'] = 'insumos/index';
        return $this->layout();
    }

    public function formulario($id = null)
    {
        if (! $this->hasAnyPermission(['eInsumo'])) {
            show_error('Sem permissão para cadastrar insumos.', 403);
            return;
        }
        $item = $id !== null ? $this->item($id) : null;
        $valores = $this->input->method() === 'post' ? $this->input->post() : ($item ? (array) $item : []);
        if ($item) {
            $valores['unidade'] = $item->unidade;
            if ($this->input->method() !== 'post') { $valores['minimo'] = number_format((float) $item->minimo, 3, ',', ''); }
        }
        $erros = [];
        if ($this->input->method() === 'post') {
            [$dados, $erros] = insumoDados($valores);
            if ($erros === []) {
                if ($item) {
                    $this->insumos_model->editar((int) $item->id, $dados);
                    $salvo = (int) $item->id;
                } else {
                    $salvo = $this->insumos_model->cadastrar($dados);
                }
                log_info('Salvou cadastro de insumo #' . $salvo);
                return redirect('insumos/visualizar/' . $salvo);
            }
            $this->output->set_status_header(422);
        }
        $this->data += ['item' => $item, 'valores' => $valores, 'erros' => $erros];
        $this->data['view'] = 'insumos/formulario';
        return $this->layout();
    }

    private function item($id): object
    {
        $item = is_scalar($id) && ctype_digit((string) $id) ? $this->insumos_model->getById((int) $id) : null;
        if (! $item) {
            show_404();
            exit;
        } return $item;
    }

    public function visualizar($id = null)
    {
        if (! $this->hasAnyPermission(['vInsumo'])) {
            show_error('Sem permissão para consultar insumos.', 403);
            return;
        }
        return $this->tela($this->item($id));
    }

    private function tela(object $item, ?string $erro = null, array $valores = [])
    {
        $offset = max(0, (int) $this->input->get('offset'));
        $this->data += ['item' => $item, 'lotes' => $this->insumos_model->lotes((int) $item->id),
            'movimentos' => $this->insumos_model->movimentos((int) $item->id, $offset, 30), 'offset' => $offset,
            'tem_mais' => $this->insumos_model->contarMovimentos((int) $item->id) > $offset + 30,
            'erro' => $erro, 'valores' => $valores, 'pode_editar' => $this->permite('eInsumo'), 'chave' => bin2hex(random_bytes(16))];
        $this->data['view'] = 'insumos/visualizar';
        return $this->layout();
    }

    private function post(): bool
    {
        if ($this->input->method() !== 'post') {
            show_error('Use o formulário para esta operação.', 405);
            return false;
        } return true;
    }

    public function movimentar($id = null)
    {
        if (! $this->hasAnyPermission(['eInsumo'])) {
            show_error('Sem permissão para movimentar insumos.', 403);
            return;
        }
        if (! $this->post()) {
            return;
        }
        $item = $this->item($id);
        $post = $this->input->post();
        try {
            $qtd = insumoQuantidade($post['quantidade'] ?? null);
            if ($qtd === null || ! is_string($post['tipo'] ?? null) || ! is_string($post['chave'] ?? null)) {
                throw new DomainException('Confira a quantidade e o tipo de movimentação.');
            }
            $this->insumos_model->movimentar((int) $item->id, $post['tipo'], $qtd, $post, (int) $this->session->userdata('id_admin'), $post['chave']);
            log_info('Movimentou insumo #' . $item->id);
            $this->session->set_flashdata('success', 'Movimentação registrada. Compras devem ser lançadas também em Despesas.');
            return redirect('insumos/visualizar/' . $item->id);
        } catch (DomainException $e) {
            $this->output->set_status_header(422);
            return $this->tela($item, $e->getMessage(), $post);
        }
    }

    public function estornar($id = null)
    {
        if (! $this->hasAnyPermission(['eInsumo'])) {
            show_error('Sem permissão para estornar insumos.', 403);
            return;
        }
        if (! $this->post()) {
            return;
        }
        $item = $this->item($id);
        $movimento = $this->input->post('movimento');
        try {
            if (! is_string($movimento) || ! ctype_digit($movimento)) {
                throw new DomainException('Movimentação inválida.');
            }
            $this->insumos_model->estornar((int) $item->id, (int) $movimento, (int) $this->session->userdata('id_admin'));
            log_info('Estornou movimento de insumo #' . $movimento);
            return redirect('insumos/visualizar/' . $item->id);
        } catch (DomainException $e) {
            $this->output->set_status_header(422);
            return $this->tela($item, $e->getMessage());
        }
    }
}
