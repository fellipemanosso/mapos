<?php

class Recebimentos_model extends CI_Model
{
    public ?object $osModel = null;

    public function disponivel(): bool
    {
        return $this->db->field_exists('atendimento_id', 'lancamentos');
    }

    public function historico(int $id): array
    {
        return $this->db->where('atendimento_id', $id)->where('recebimento_tipo !=', 'saldo')
            ->order_by('idLancamentos', 'desc')->get('lancamentos')->result();
    }

    public function recebido(int $id): int
    {
        $linha = $this->db->select_sum('recebimento_valor', 'recebido')->where('atendimento_id', $id)
            ->where('recebimento_tipo !=', 'saldo')->where('estornado_em', null)->get('lancamentos')->row();
        return financeiroCentavos((string) ($linha->recebido ?? '0'));
    }

    private function os(int $id): object
    {
        $sql = 'SELECT * FROM os WHERE idOs = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : '');
        $os = $this->db->query($sql, [$id])->row();
        if (! $os || $os->status === 'Cancelado') {
            throw new DomainException('Atendimento não encontrado ou cancelado.');
        }
        return $os;
    }

    public function total(object $os): int
    {
        if ($this->osModel === null) {
            $this->load->model('os_model');
            $this->osModel = $this->os_model;
        }
        return (int) round($this->osModel->totais($os)['total'] * 100);
    }

    public function orcamento(int $id, string $sinal, string $vencimento): void
    {
        $this->db->trans_begin();
        try {
            $os = $this->os($id);
            if ((int) $os->faturado || (int) $os->financeiro_confirmado) {
                throw new DomainException('O orçamento já foi confirmado. Estorne os recebimentos para rever os valores.');
            }
            if (financeiroCentavos($sinal) > $this->total($os)) {
                throw new DomainException('O sinal previsto não pode superar o total do orçamento.');
            }
            $this->db->where('idOs', $id)->update('os', ['sinal_previsto' => $sinal, 'saldo_vencimento' => $vencimento]);
            $this->concluir();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    /** Serializa por atendimento e usa chave única para não duplicar um POST repetido. */
    public function receber(int $id, array $dados, int $usuario): void
    {
        $this->db->trans_begin();
        try {
            $os = $this->os($id);
            $existente = $this->db->where('chave_operacao', $dados['chave'])->get('lancamentos')->row();
            if ($existente) {
                if ((int) $existente->atendimento_id !== $id) {
                    throw new DomainException('Esta operação pertence a outro atendimento. Recarregue a página.');
                }
                $this->db->trans_commit();
                return;
            }
            if ((int) $os->faturado && ! (int) $os->financeiro_confirmado) {
                throw new DomainException('Este atendimento já foi faturado no fluxo anterior. Consulte seu lançamento no financeiro.');
            }
            $total = $this->total($os);
            $recebido = $this->recebido($id);
            $valor = (int) $dados['centavos'];
            $saldo = $total - $recebido;
            if ($valor <= 0 || $valor > $saldo) {
                throw new DomainException('O recebimento deve ser maior que zero e não pode superar o saldo de ' . dinheiro($saldo / 100) . '.');
            }
            if (($dados['tipo'] === 'integral' && ($recebido > 0 || $valor !== $total))
                || ($dados['tipo'] === 'quitacao' && $valor !== $saldo)
                || ($dados['tipo'] === 'sinal' && ($recebido > 0 || $valor >= $total))) {
                throw new DomainException('Confira o tipo: sinal é o primeiro pagamento parcial; integral e quitação precisam cobrir o saldo completo.');
            }
            $cliente = $this->db->where('idClientes', $os->clientes_id)->get('clientes')->row();
            $base = [
                'atendimento_id' => $id, 'tipo' => 'receita', 'clientes_id' => $os->clientes_id,
                'cliente_fornecedor' => $cliente->nomeCliente, 'usuarios_id' => $usuario,
                'desconto' => 0, 'tipo_desconto' => 'real',
            ];
            $this->db->insert('lancamentos', $base + [
                'descricao' => recebimentoRotulo($dados['tipo']) . ' · Atendimento #' . $id,
                'valor' => financeiroDecimal($valor), 'valor_desconto' => financeiroDecimal($valor),
                'recebimento_valor' => financeiroDecimal($valor), 'recebimento_tipo' => $dados['tipo'],
                'data_vencimento' => $dados['data'], 'data_pagamento' => $dados['data'],
                'baixado' => 1, 'forma_pgto' => $dados['forma'], 'observacoes' => $dados['observacoes'],
                'chave_operacao' => $dados['chave'],
            ]);
            $idRecebimento = (int) $this->db->insert_id();
            if (in_array($os->status, ['Orçamento', 'Negociação'], true)) {
                $this->db->where('idOs', $id)->update('os', ['status' => 'Aprovado']);
                $this->osModel->registrarStatus($id, (string) $os->status, 'Aprovado', $usuario);
            }
            $this->sincronizarSaldo($os, $total - $recebido - $valor, $base, $idRecebimento);
            $this->concluir();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    /** Corrige o registro, preservando valor original e data do estorno no histórico. */
    public function estornar(int $id, int $pagamento, int $usuario): void
    {
        $this->db->trans_begin();
        try {
            $os = $this->os($id);
            $linha = $this->db->where('idLancamentos', $pagamento)->where('atendimento_id', $id)
                ->where('recebimento_tipo !=', 'saldo')->get('lancamentos')->row();
            if (! $linha) {
                throw new DomainException('Recebimento não encontrado neste atendimento.');
            }
            if ($linha->estornado_em) {
                $this->db->trans_commit();
                return;
            }
            $this->db->where('idLancamentos', $pagamento)->update('lancamentos', [
                'valor' => '0.00', 'valor_desconto' => '0.00', 'estornado_em' => date('Y-m-d H:i:s'),
                'observacoes' => trim((string) $linha->observacoes . "\nRegistro estornado pelo usuário #" . $usuario . '.'),
            ]);
            $recebido = $this->recebido($id);
            $base = ['atendimento_id' => $id, 'tipo' => 'receita', 'clientes_id' => $os->clientes_id,
                'cliente_fornecedor' => $linha->cliente_fornecedor, 'usuarios_id' => $usuario, 'desconto' => 0, 'tipo_desconto' => 'real'];
            if ($recebido === 0) {
                $this->db->where('idOs', $id)->update('os', ['lancamento' => null, 'faturado' => 0, 'financeiro_confirmado' => 0]);
                $this->db->where('atendimento_id', $id)->where('recebimento_tipo', 'saldo')->delete('lancamentos');
            } else {
                $ultimo = $this->db->where('atendimento_id', $id)->where('estornado_em', null)
                    ->where('recebimento_tipo !=', 'saldo')->order_by('idLancamentos', 'desc')->get('lancamentos')->row();
                $this->sincronizarSaldo($os, $this->total($os) - $recebido, $base, (int) $ultimo->idLancamentos);
            }
            $this->concluir();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    private function sincronizarSaldo(object $os, int $saldo, array $base, int $ultimo): void
    {
        $pendente = $this->db->where('atendimento_id', $os->idOs)->where('recebimento_tipo', 'saldo')->get('lancamentos')->row();
        if ($saldo > 0) {
            $dados = $base + [
                'descricao' => 'Saldo a receber · Atendimento #' . $os->idOs,
                'valor' => financeiroDecimal($saldo), 'valor_desconto' => financeiroDecimal($saldo),
                'recebimento_tipo' => 'saldo', 'baixado' => 0,
                'data_vencimento' => $os->saldo_vencimento ?: date('Y-m-d'),
            ];
            if ($pendente) {
                $this->db->where('idLancamentos', $pendente->idLancamentos)->update('lancamentos', $dados);
                $ultimo = (int) $pendente->idLancamentos;
            } else {
                $this->db->insert('lancamentos', $dados);
                $ultimo = (int) $this->db->insert_id();
            }
        }
        $this->db->where('idOs', $os->idOs)->update('os', ['lancamento' => $ultimo, 'faturado' => 1, 'financeiro_confirmado' => 1]);
        if ($saldo === 0 && $pendente) {
            $this->db->where('idLancamentos', $pendente->idLancamentos)->delete('lancamentos');
        }
    }

    private function concluir(): void
    {
        if (! $this->db->trans_status()) {
            throw new RuntimeException('Não foi possível salvar o recebimento. Nenhum valor foi alterado.');
        }
        $this->db->trans_commit();
    }
}
