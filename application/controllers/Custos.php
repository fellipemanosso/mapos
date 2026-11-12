<?php

class Custos extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'financeiro', 'insumos', 'fichas', 'custos', 'os']);
        $this->load->model(['custos_model', 'os_model', 'recebimentos_model']);
    }

    private function id($valor): int
    {
        return is_scalar($valor) && ctype_digit((string) $valor) ? (int) $valor : 0;
    }

    public function index($id = null)
    {
        if (! $this->hasAnyPermission(['vOs']) || ! $this->hasAnyPermission(['rFinanceiro'])) {
            show_error('Sem permissão para consultar custos internos.', 403);
            return;
        }
        return $this->tela($this->id($id));
    }

    private function tela(int $id, ?string $erro = null, array $valores = [])
    {
        $os = $this->os_model->getById($id);
        if (! $os) {
            show_404();
            return;
        }
        $consumos = $this->custos_model->consumos($id);
        $custos = $this->custos_model->custos($id);
        $somar = static fn (array $linhas, string $campo): int => array_sum(array_map(
            static fn ($linha) => $linha->estornado_em ? 0 : financeiroCentavos((string) $linha->{$campo}),
            $linhas
        ));
        $recebido = $this->recebimentos_model->recebido($id);
        if (empty($os->financeiro_confirmado) && ! empty($os->lancamento)) {
            $legado = $this->db->where('idLancamentos', $os->lancamento)->where('tipo', 'receita')->where('baixado', 1)->get('lancamentos')->row();
            if ($legado) {
                $recebido = (int) round(((float) $legado->valor_desconto > 0 ? (float) $legado->valor_desconto : (float) $legado->valor) * 100);
            }
        }
        $this->data += ['os' => $os, 'consumos' => $consumos, 'custos' => $custos,
            'resultado' => custosResultado((int) round($this->os_model->totais($os)['total'] * 100), $recebido, $somar($consumos, 'custo'), $somar($custos, 'valor')),
            'lotes' => $this->permite('eInsumo') ? $this->custos_model->lotes() : [],
            'editar' => $this->permite('eOs') && $os->status !== 'Cancelado',
            'editar_insumos' => $this->permite('eOs') && $this->permite('eInsumo') && $os->status !== 'Cancelado',
            'previa' => $this->custos_model->previa($id),
            'erro' => $erro, 'valores' => $valores, 'chave' => bin2hex(random_bytes(16))];
        $this->data['view'] = 'custos/index';
        return $this->layout();
    }

    public function salvar($id = null)
    {
        if (! $this->hasAnyPermission(['vOs']) || ! $this->hasAnyPermission(['rFinanceiro']) || ! $this->hasAnyPermission(['eOs'])) {
            show_error('Sem permissão para alterar custos internos.', 403);
            return;
        }
        if ($this->input->method() !== 'post') {
            show_error('Use o formulário para esta operação.', 405);
            return;
        }
        $id = $this->id($id);
        $post = $this->input->post();
        $acao = is_string($post['acao'] ?? null) ? $post['acao'] : '';
        if (in_array($acao, ['consumo', 'estornar_consumo', 'padroes'], true) && ! $this->permite('eInsumo')) {
            show_error('Sem permissão para movimentar insumos.', 403);
            return;
        }
        $usuario = (int) $this->session->userdata('id_admin');
        $chave = is_string($post['chave'] ?? null) ? $post['chave'] : '';
        try {
            switch ($acao) {
                case 'custo':
                    $this->custos_model->adicionar($id, $post, $usuario, $chave);
                    break;
                case 'estornar_custo':
                    $this->custos_model->estornar($id, $this->id($post['registro'] ?? null), $usuario);
                    break;
                case 'consumo':
                    $qtd = insumoQuantidade($post['quantidade'] ?? null);
                    if ($qtd === null || $qtd < 1) {
                        throw new DomainException('Informe uma quantidade maior que zero, com até três casas decimais.');
                    }
                    $this->custos_model->consumir($id, $this->id($post['lote_id'] ?? null), $qtd, $usuario, $chave);
                    break;
                case 'estornar_consumo':
                    $this->custos_model->estornarConsumo($id, $this->id($post['registro'] ?? null), $usuario);
                    break;
                case 'padroes':
                    $this->custos_model->confirmarMateriais($id, is_array($post['materiais'] ?? null) ? $post['materiais'] : [], is_string($post['assinatura'] ?? null) ? $post['assinatura'] : '', $usuario);
                    break;
                default:
                    throw new DomainException('Operação inválida.');
            }
            log_info('Atualizou custos internos do atendimento #' . $id . ' · ' . $acao);
            $this->session->set_flashdata('success', 'Custos internos atualizados. O valor cobrado e o financeiro não foram alterados.');
            return redirect('custos/index/' . $id);
        } catch (DomainException $e) {
            $this->output->set_status_header(422);
            return $this->tela($id, $e->getMessage(), $post);
        }
    }

    public function padrao($id = null)
    {
        if (! $this->hasAnyPermission(['eServico']) || ! $this->hasAnyPermission(['eInsumo'])) {
            show_error('Sem permissão para configurar insumos padrão.', 403);
            return;
        }
        $id = $this->id($id);
        $servico = $this->db->where('idServicos', $id)->get('servicos')->row();
        if (! $servico) {
            show_404();
            return;
        }
        $erro = null;
        if ($this->input->method() === 'post') {
            try {
                $remover = $this->id($this->input->post('remover'));
                $qtd = $remover ? 0 : insumoQuantidade($this->input->post('quantidade'));
                if ($qtd === null) {
                    throw new DomainException('Informe uma quantidade com até três casas decimais. Zero remove o insumo do padrão.');
                }
                $this->custos_model->salvarPadrao($id, $remover ?: $this->id($this->input->post('insumo_id')), $qtd);
                log_info('Atualizou insumos padrão do serviço #' . $id);
                $this->session->set_flashdata('success', 'Composição salva. Consumos já registrados permanecem iguais.');
                return redirect('custos/padrao/' . $id);
            } catch (DomainException $e) {
                $erro = $e->getMessage();
                $this->output->set_status_header(422);
            }
        }
        $this->data += ['servico' => $servico, 'itens' => $this->custos_model->padrao($id),
            'insumos' => $this->db->order_by('nome')->get('insumos')->result(), 'erro' => $erro];
        $this->data['view'] = 'custos/padrao';
        return $this->layout();
    }
}
