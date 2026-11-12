<?php

class Conversas extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'automacoes']);
        $this->load->model('automacoes_model');
        $this->output->set_header('Cache-Control: no-store, private');
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['vWhatsapp'])) {
            show_error('Sem permissão para consultar conversas.', 403);
            return;
        }
        $filtros = listagemFiltros(['pesquisa' => 'texto', 'modo' => ['humano', 'ia']], $this->input->get());
        $filtrar = function () use ($filtros): void {
            if (! empty($filtros['pesquisa'])) {
                $this->db->group_start()->like('nome', $filtros['pesquisa'])->or_like('telefone', $filtros['pesquisa'])->group_end();
            }
            if (! empty($filtros['modo'])) {
                $this->db->where('modo', $filtros['modo']);
            }
        };
        $filtrar();
        $total = $this->db->count_all_results('wa_conversas');
        $offset = max(0, (int) $this->uri->segment(3));
        $filtrar();
        $conversas = $this->db->order_by('atualizado_em', 'desc')->limit(20, $offset)->get('wa_conversas')->result();
        $this->data['configuration']['per_page'] = 20;
        $this->data += ['conversas' => $conversas, 'filtros' => $filtros, 'paginacao' => $this->paginacao(site_url('conversas/index'), $total, $offset, null, $filtros)];
        $this->data['view'] = 'conversas/index';
        return $this->layout();
    }

    private function contato($id): object
    {
        $c = is_scalar($id) && ctype_digit((string) $id) ? $this->automacoes_model->conversa((int) $id) : null;
        if (! $c) {
            show_404();
            exit;
        }
        return $c;
    }

    public function visualizar($id = null)
    {
        if (! $this->hasAnyPermission(['vWhatsapp'])) {
            show_error('Sem permissão para consultar conversas.', 403);
            return;
        }
        $c = $this->contato($id);
        $offset = max(0, (int) $this->input->get('pagina'));
        $total = $this->db->where('conversa_id', $c->id)->count_all_results('wa_mensagens');
        $mensagens = $this->db->where('conversa_id', $c->id)->order_by('id', 'desc')->limit(30, $offset)->get('wa_mensagens')->result();
        // Só usuários com acesso ao cadastro podem procurar vínculos de clientes.
        $cliente = $c->cliente_id && $this->permite('vCliente') ? $this->db->select('idClientes, nomeCliente')->where('idClientes', $c->cliente_id)->get('clientes')->row() : null;
        $this->data['configuration']['per_page'] = 30;
        $this->data += ['conversa' => $c, 'mensagens' => $mensagens, 'cliente' => $cliente, 'pode_editar' => $this->permite('eWhatsapp'),
            'pode_cliente' => $this->permite('vCliente'), 'pode_lead' => $this->permite('vOs'),
            'chave' => bin2hex(random_bytes(32)), 'paginacao' => $this->paginacao(site_url('conversas/visualizar/' . $c->id), $total, $offset, 'pagina')];
        $this->data['view'] = 'conversas/visualizar';
        return $this->layout();
    }

    public function responder($id = null)
    {
        if (! $this->hasAnyPermission(['eWhatsapp'])) {
            show_error('Sem permissão para responder.', 403);
            return;
        }
        if ($this->input->method() !== 'post') {
            show_error('Use o formulário para responder.', 405);
            return;
        }
        $c = $this->contato($id);
        $texto = $this->input->post('texto');
        $chave = $this->input->post('chave');
        if (! is_string($texto) || trim($texto) === '' || mb_strlen($texto) > 4000 || ! is_string($chave) || ! preg_match('/\A[a-f0-9]{64}\z/', $chave)) {
            show_error('Mensagem inválida. Reabra a conversa.', 422);
            return;
        }
        $this->db->trans_begin();
        try {
            $this->automacoes_model->ajustar((int) $c->id, 'humano', $c->cliente_id ? (int) $c->cliente_id : null, (bool) $c->lembretes);
            $this->automacoes_model->fila((int) $c->id, trim($texto), 'manual', $chave);
            $this->db->trans_commit();
            log_info('Enfileirou resposta manual na conversa #' . $c->id);
            $this->session->set_flashdata('success', 'Mensagem na fila. A conversa está sob atendimento humano.');
        } catch (DomainException $e) {
            $this->db->trans_rollback();
            $this->session->set_flashdata('error', $e->getMessage());
        }
        return redirect('conversas/visualizar/' . $c->id);
    }

    public function ajustar($id = null)
    {
        if (! $this->hasAnyPermission(['eWhatsapp'])) {
            show_error('Sem permissão para alterar a conversa.', 403);
            return;
        }
        if ($this->input->method() !== 'post') {
            show_error('Use o formulário para alterar a conversa.', 405);
            return;
        }
        $c = $this->contato($id);
        $modo = $this->input->post('modo');
        $cliente = $this->input->post('cliente_id');
        if (! is_string($modo) || ! is_string($cliente) || ($cliente !== '' && (! ctype_digit($cliente) || (int) $cliente < 1))) {
            show_error('Dados inválidos.', 422);
            return;
        }
        $clienteId = $cliente !== '' ? (int) $cliente : null;
        if ($clienteId !== ($c->cliente_id ? (int) $c->cliente_id : null) && ! $this->permite('vCliente')) {
            show_error('Sem permissão para vincular clientes.', 403);
            return;
        }
        try {
            $this->automacoes_model->ajustar((int) $c->id, $modo, $clienteId, $this->input->post('lembretes') === '1');
            log_info('Alterou modo, vínculo ou autorização de lembretes da conversa #' . $c->id);
            $this->session->set_flashdata('success', 'Controle da conversa atualizado.');
        } catch (DomainException $e) {
            $this->session->set_flashdata('error', $e->getMessage());
        }
        return redirect('conversas/visualizar/' . $c->id);
    }

    public function cancelar($id = null)
    {
        if (! $this->hasAnyPermission(['eWhatsapp'])) {
            show_error('Sem permissão para cancelar envio.', 403);
            return;
        }
        if ($this->input->method() !== 'post') {
            show_error('Use o formulário para cancelar.', 405);
            return;
        }
        $c = $this->contato($id);
        $mensagem = $this->input->post('mensagem');
        if (! is_string($mensagem) || ! ctype_digit($mensagem)) {
            show_error('Mensagem inválida.', 422);
            return;
        }
        $this->db->where('id', (int) $mensagem)->where('conversa_id', $c->id)->where_in('estado', ['pendente', 'aguarda_ia'])->update('wa_mensagens', ['estado' => 'cancelado']);
        log_info('Solicitou cancelamento do envio #' . (int) $mensagem . ' na conversa #' . $c->id);
        return redirect('conversas/visualizar/' . $c->id);
    }
}
