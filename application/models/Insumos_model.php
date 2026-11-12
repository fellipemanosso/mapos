<?php

class Insumos_model extends CI_Model
{
    public function listar(string $pesquisa, int $offset, int $limite): array
    {
        return $this->db->select('i.*, COALESCE(SUM(CASE WHEN l.validade IS NULL OR l.validade >= ' . $this->db->escape(date('Y-m-d')) . ' THEN l.saldo ELSE 0 END), 0) AS disponivel', false)
            ->from('insumos i')->join('insumo_lotes l', 'l.insumo_id = i.id', 'left')->like('i.nome', $pesquisa)->group_by('i.id')->order_by('i.nome')->limit($limite, $offset)->get()->result();
    }

    public function contar(string $pesquisa): int
    {
        return (int) $this->db->like('nome', $pesquisa)->count_all_results('insumos');
    }

    public function getById(int $id): ?object
    {
        return $this->db->where('id', $id)->get('insumos')->row();
    }

    public function cadastrar(array $dados): int
    {
        $this->db->insert('insumos', $dados);
        return (int) $this->db->insert_id();
    }

    public function editar(int $id, array $dados): bool
    {
        return $this->db->where('id', $id)->update('insumos', ['nome' => $dados['nome'], 'minimo' => $dados['minimo']]);
    }

    public function lotes(int $insumo): array
    {
        return $this->db->where('insumo_id', $insumo)->order_by('validade IS NULL', 'asc', false)->order_by('validade')->order_by('id')->get('insumo_lotes')->result();
    }

    public function movimentos(int $insumo, int $offset, int $limite): array
    {
        return $this->db->select('m.*, l.lote')->from('insumo_movimentos m')->join('insumo_lotes l', 'l.id = m.lote_id')
            ->where('l.insumo_id', $insumo)->order_by('m.id', 'desc')->limit($limite, $offset)->get()->result();
    }

    public function contarMovimentos(int $insumo): int
    {
        return (int) $this->db->from('insumo_movimentos m')->join('insumo_lotes l', 'l.id = m.lote_id')->where('l.insumo_id', $insumo)->count_all_results();
    }

    public function movimentar(int $insumo, string $tipo, int $quantidade, array $dados, int $usuario, string $chave): int
    {
        if (! in_array($tipo, ['entrada', 'consumo', 'descarte'], true) || $quantidade < 1 || $quantidade > 999999999 || ! preg_match('/\A[a-f0-9]{32}\z/', $chave)) {
            throw new DomainException('Movimentação inválida.');
        }
        $this->db->trans_begin();
        try {
            $item = $this->db->query('SELECT * FROM insumos WHERE id = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : ''), [$insumo])->row();
            if (! $item) {
                throw new DomainException('Insumo não encontrado.');
            }
            $anterior = $this->db->where('chave', $chave)->get('insumo_movimentos')->row();
            if ($anterior) {
                $lote = $this->db->where('id', $anterior->lote_id)->get('insumo_lotes')->row();
                if ((int) $lote->insumo_id !== $insumo) {
                    throw new DomainException('Reabra o formulário.');
                }
                $this->db->trans_commit();
                return (int) $anterior->id;
            }
            $nota = $dados['nota'] ?? '';
            if (! is_string($nota) || trim($nota) === '' || mb_strlen($nota) > 255) {
                throw new DomainException('Informe uma observação de até 255 caracteres.');
            }
            $os = null;
            if ($tipo === 'entrada') {
                $nome = $dados['lote'] ?? '';
                $validade = $dados['validade'] ?? '';
                $data = $validade === '' ? null : fichaData($validade);
                $custo = valorDecimal($dados['custo'] ?? null);
                if (! is_string($nome) || trim($nome) === '' || mb_strlen($nome) > 100 || ($validade !== '' && ! $data) || $custo === null) {
                    throw new DomainException('Informe lote, validade válida (ou deixe vazia) e custo total da entrada.');
                }
                $unitario = (float) $custo * 1000 / $quantidade;
                if ($unitario > 99999999.999999) {
                    throw new DomainException('Custo unitário fora do limite. Confira a unidade e a quantidade da entrada.');
                }
                $this->db->insert('insumo_lotes', ['insumo_id' => $insumo, 'lote' => trim($nome), 'validade' => $data,
                    'saldo' => insumoDecimal($quantidade), 'custo_unitario' => number_format($unitario, 6, '.', ''), 'criado_em' => date('Y-m-d H:i:s')]);
                $loteId = (int) $this->db->insert_id();
            } else {
                $loteId = is_string($dados['lote_id'] ?? null) && ctype_digit($dados['lote_id']) ? (int) $dados['lote_id'] : 0;
                $lote = $this->db->where('id', $loteId)->where('insumo_id', $insumo)->get('insumo_lotes')->row();
                if (! $lote || (int) round((float) $lote->saldo * 1000) < $quantidade) {
                    throw new DomainException('Escolha um lote deste insumo com saldo suficiente.');
                }
                if ($tipo === 'consumo') {
                    if ($lote->validade && $lote->validade < date('Y-m-d')) {
                        throw new DomainException('Lote vencido não pode ser consumido em atendimento.');
                    }
                    $os = is_string($dados['os_id'] ?? null) && ctype_digit($dados['os_id']) ? (int) $dados['os_id'] : 0;
                    if (! $this->db->where('idOs', $os)->where('status !=', 'Cancelado')->get('os')->row()) {
                        throw new DomainException('Informe um atendimento válido e não cancelado.');
                    }
                }
                $saldo = (int) round((float) $lote->saldo * 1000) - $quantidade;
                $this->db->where('id', $loteId)->update('insumo_lotes', ['saldo' => insumoDecimal($saldo)]);
                $custo = number_format($quantidade / 1000 * (float) $lote->custo_unitario, 2, '.', '');
            }
            $this->db->insert('insumo_movimentos', ['lote_id' => $loteId, 'os_id' => $os, 'tipo' => $tipo, 'quantidade' => insumoDecimal($quantidade),
                'custo' => $custo, 'nota' => trim($nota), 'usuarios_id' => $usuario, 'criado_em' => date('Y-m-d H:i:s'), 'chave' => $chave]);
            $id = (int) $this->db->insert_id();
            $this->concluir();
            return $id;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    public function estornar(int $insumo, int $movimento, int $usuario): void
    {
        $this->db->trans_begin();
        try {
            $this->db->query('SELECT id FROM insumos WHERE id = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : ''), [$insumo])->row();
            $m = $this->db->select('m.*, l.saldo, l.insumo_id')->from('insumo_movimentos m')->join('insumo_lotes l', 'l.id = m.lote_id')
                ->where('m.id', $movimento)->where('l.insumo_id', $insumo)->get()->row();
            if (! $m) {
                throw new DomainException('Movimentação não encontrada neste insumo.');
            }
            if (! $m->estornado_em) {
                if ($m->tipo === 'entrada' && $this->db->where('lote_id', $m->lote_id)->where('id !=', $m->id)->where('estornado_em', null)->count_all_results('insumo_movimentos') > 0) {
                    throw new DomainException('Estorne as saídas deste lote antes de estornar sua entrada.');
                }
                $saldo = (int) round((float) $m->saldo * 1000) + ($m->tipo === 'entrada' ? -1 : 1) * (int) round((float) $m->quantidade * 1000);
                if ($saldo < 0 || $saldo > 999999999) {
                    throw new DomainException('O estorno deixaria o saldo inválido.');
                }
                $this->db->where('id', $m->lote_id)->update('insumo_lotes', ['saldo' => insumoDecimal($saldo)]);
                $this->db->where('id', $movimento)->update('insumo_movimentos', ['estornado_em' => date('Y-m-d H:i:s'), 'estornado_por' => $usuario]);
            }
            $this->concluir();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    private function concluir(): void
    {
        if (! $this->db->trans_status()) {
            throw new RuntimeException('Não foi possível salvar a movimentação.');
        }
        $this->db->trans_commit();
    }
}
