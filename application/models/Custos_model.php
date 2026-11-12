<?php

/** Dados privados: não carregar este modelo nos controllers de cliente, impressão ou API. */
class Custos_model extends CI_Model
{
    public ?object $estoque = null;

    public function custos(int $os): array
    {
        return $this->db->where('os_id', $os)->order_by('id', 'desc')->get('atendimento_custos')->result();
    }

    public function consumos(int $os): array
    {
        return $this->db->select('m.*, i.nome, i.unidade, l.lote, l.insumo_id')->from('insumo_movimentos m')
            ->join('insumo_lotes l', 'l.id = m.lote_id')->join('insumos i', 'i.id = l.insumo_id')
            ->where('m.os_id', $os)->where('m.tipo', 'consumo')->order_by('m.id', 'desc')->get()->result();
    }

    public function lotes(): array
    {
        return $this->db->select('l.*, i.nome, i.unidade')->from('insumo_lotes l')->join('insumos i', 'i.id = l.insumo_id')
            ->where('l.saldo >', 0)->group_start()->where('l.validade', null)->or_where('l.validade >=', date('Y-m-d'))->group_end()
            ->order_by('i.nome')->order_by('l.validade IS NULL', 'asc', false)->order_by('l.validade')->get()->result();
    }

    private function atendimento(int $id): object
    {
        $os = $this->db->query('SELECT * FROM os WHERE idOs = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : ''), [$id])->row();
        if (! $os || $os->status === 'Cancelado') {
            throw new DomainException('Atendimento não encontrado ou cancelado.');
        }
        return $os;
    }

    private function transacao(callable $acao): void
    {
        $this->db->trans_begin();
        try {
            $acao();
            if (! $this->db->trans_status()) {
                throw new RuntimeException('Não foi possível salvar os custos.');
            }
            $this->db->trans_commit();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    public function adicionar(int $id, array $post, int $usuario, string $chave): void
    {
        $dados = custoDados($post);
        if (! preg_match('/\A[a-f0-9]{32}\z/', $chave)) {
            throw new DomainException('Reabra o formulário.');
        }
        $this->transacao(function () use ($id, $dados, $usuario, $chave) {
            $this->atendimento($id);
            $anterior = $this->db->where('chave', $chave)->get('atendimento_custos')->row();
            if ($anterior) {
                if ((int) $anterior->os_id !== $id) {
                    throw new DomainException('Operação de outro atendimento.');
                }
                return;
            }
            $this->db->insert('atendimento_custos', $dados + ['os_id' => $id, 'usuarios_id' => $usuario, 'chave' => $chave, 'criado_em' => date('Y-m-d H:i:s')]);
        });
    }

    public function estornar(int $id, int $custo, int $usuario): void
    {
        $this->transacao(function () use ($id, $custo, $usuario) {
            $this->atendimento($id);
            $linha = $this->db->where('id', $custo)->where('os_id', $id)->get('atendimento_custos')->row();
            if (! $linha) {
                throw new DomainException('Custo não pertence a este atendimento.');
            }
            $this->db->where('id', $custo)->where('estornado_em', null)->update('atendimento_custos', ['estornado_em' => date('Y-m-d H:i:s'), 'estornado_por' => $usuario]);
        });
    }

    private function estoque(): object
    {
        if ($this->estoque === null) {
            $this->load->model('insumos_model');
            $this->estoque = $this->insumos_model;
        }
        return $this->estoque;
    }

    public function consumir(int $id, int $loteId, int $quantidade, int $usuario, string $chave): void
    {
        $this->transacao(function () use ($id, $loteId, $quantidade, $usuario, $chave) {
            $this->atendimento($id);
            $lote = $this->db->where('id', $loteId)->get('insumo_lotes')->row();
            if (! $lote) {
                throw new DomainException('Lote não encontrado.');
            }
            $anterior = $this->db->where('chave', $chave)->get('insumo_movimentos')->row();
            if ($anterior && ((int) $anterior->os_id !== $id || $anterior->tipo !== 'consumo')) {
                throw new DomainException('Operação de outro atendimento.');
            }
            $this->estoque()->movimentar(
                (int) $lote->insumo_id,
                'consumo',
                $quantidade,
                ['lote_id' => (string) $loteId, 'os_id' => (string) $id, 'nota' => 'Consumo registrado nos custos do atendimento'],
                $usuario,
                $chave
            );
        });
    }

    public function estornarConsumo(int $id, int $movimento, int $usuario): void
    {
        $this->transacao(function () use ($id, $movimento, $usuario) {
            $this->atendimento($id);
            $m = $this->db->where('id', $movimento)->where('os_id', $id)->where('tipo', 'consumo')->get('insumo_movimentos')->row();
            if (! $m) {
                throw new DomainException('Consumo não pertence a este atendimento.');
            }
            $lote = $this->db->where('id', $m->lote_id)->get('insumo_lotes')->row();
            $this->estoque()->estornar((int) $lote->insumo_id, $movimento, $usuario);
        });
    }

    public function padrao(int $servico): array
    {
        return $this->db->select('p.*, i.nome, i.unidade')->from('servico_insumos p')->join('insumos i', 'i.id = p.insumo_id')
            ->where('p.servico_id', $servico)->order_by('i.nome')->get()->result();
    }

    public function salvarPadrao(int $servico, int $insumo, int $quantidade): void
    {
        if ($quantidade < 0 || $quantidade > 999999999 || ! $this->db->where('idServicos', $servico)->get('servicos')->row()
            || ! $this->db->where('id', $insumo)->get('insumos')->row()) {
            throw new DomainException('Escolha um serviço, um insumo e uma quantidade válida.');
        }
        $this->transacao(function () use ($servico, $insumo, $quantidade) {
            // Serializa a edição da composição e a aplicação aos atendimentos.
            $this->db->query('SELECT idServicos FROM servicos WHERE idServicos = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : ''), [$servico]);
            $this->db->where('servico_id', $servico)->where('insumo_id', $insumo)->delete('servico_insumos');
            if ($quantidade > 0) {
                $this->db->insert('servico_insumos', ['servico_id' => $servico, 'insumo_id' => $insumo, 'quantidade' => insumoDecimal($quantidade)]);
            }
        });
    }

    /** Aplicação explícita, uma vez por item de serviço; tudo ou nada em falta de estoque. */
    public function aplicarPadroes(int $id, int $usuario): void
    {
        $this->transacao(function () use ($id, $usuario) {
            $this->atendimento($id);
            $itens = $this->db->where('os_id', $id)->order_by('servicos_id')->get('servicos_os')->result();
            foreach ($itens as $item) {
                if ($this->db->where('os_id', $id)->where('item_id', $item->idServicos_os)->get('atendimento_insumos_padrao')->row()) {
                    continue;
                }
                $this->db->query('SELECT idServicos FROM servicos WHERE idServicos = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : ''), [$item->servicos_id]);
                $padrao = $this->db->where('servico_id', $item->servicos_id)->order_by('insumo_id')->get('servico_insumos')->result();
                if (! $padrao) {
                    continue;
                }
                foreach ($padrao as $p) {
                    $restante = (int) round((float) $p->quantidade * ((float) $item->quantidade ?: 1) * 1000);
                    if ($restante < 1 || $restante > 999999999) {
                        throw new DomainException('Quantidade padrão fora do limite. Confira a composição do serviço.');
                    }
                    $this->db->query('SELECT id FROM insumos WHERE id = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : ''), [$p->insumo_id]);
                    $lotes = $this->db->where('insumo_id', $p->insumo_id)->where('saldo >', 0)
                        ->group_start()->where('validade', null)->or_where('validade >=', date('Y-m-d'))->group_end()
                        ->order_by('validade IS NULL', 'asc', false)->order_by('validade')->order_by('id')->get('insumo_lotes')->result();
                    foreach ($lotes as $lote) {
                        $qtd = min($restante, (int) round((float) $lote->saldo * 1000));
                        if ($qtd > 0) {
                            $this->estoque()->movimentar(
                                (int) $p->insumo_id,
                                'consumo',
                                $qtd,
                                ['lote_id' => (string) $lote->id, 'os_id' => (string) $id, 'nota' => 'Padrão do serviço #' . $item->servicos_id . ' · item #' . $item->idServicos_os],
                                $usuario,
                                bin2hex(random_bytes(16))
                            );
                            $restante -= $qtd;
                        }
                        if ($restante === 0) {
                            break;
                        }
                    }
                    if ($restante > 0) {
                        throw new DomainException('Estoque insuficiente para o insumo #' . $p->insumo_id . '. Nenhum consumo padrão foi registrado.');
                    }
                }
                $this->db->insert('atendimento_insumos_padrao', ['os_id' => $id, 'item_id' => $item->idServicos_os, 'criado_em' => date('Y-m-d H:i:s')]);
            }
        });
    }
}
