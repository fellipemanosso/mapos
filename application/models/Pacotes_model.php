<?php

class Pacotes_model extends CI_Model
{
    public function listar(string $pesquisa = '', int $offset = 0, int $limite = 20): array
    {
        $this->filtrar($pesquisa);
        return $this->db->select('p.*, c.nomeCliente, d.nome as beneficiario, os.status')->order_by('p.id', 'desc')->limit($limite, $offset)->get()->result();
    }

    public function contar(string $pesquisa): int
    {
        $this->filtrar($pesquisa);
        return (int) $this->db->count_all_results();
    }

    private function filtrar(string $pesquisa): void
    {
        $this->db->from('pacotes p')
            ->join('os', 'os.idOs = p.os_id')->join('clientes c', 'c.idClientes = os.clientes_id')
            ->join('dependentes d', 'd.id = os.dependente_id', 'left');
        if ($pesquisa !== '') {
            $this->db->group_start()->like('p.nome', $pesquisa)->or_like('c.nomeCliente', $pesquisa)->or_like('d.nome', $pesquisa)->group_end();
        }
    }

    public function getById(int $id): ?object
    {
        return $this->db->select('p.*, os.clientes_id, os.dependente_id, os.status, c.nomeCliente, d.nome as beneficiario')
            ->from('pacotes p')->join('os', 'os.idOs = p.os_id')->join('clientes c', 'c.idClientes = os.clientes_id')
            ->join('dependentes d', 'd.id = os.dependente_id', 'left')->where('p.id', $id)->get()->row();
    }

    public function sessoes(int $id): array
    {
        return $this->db->select('s.*, a.inicio, a.fim, a.cancelado')->from('pacote_sessoes s')
            ->join('agenda a', 'a.id = s.agenda_id', 'left')->where('s.pacote_id', $id)->order_by('s.numero')->get()->result();
    }

    public function sessao(int $id): ?object
    {
        return $this->db->select('s.*, p.os_id, p.nome as pacote_nome, os.status')->from('pacote_sessoes s')
            ->join('pacotes p', 'p.id = s.pacote_id')->join('os', 'os.idOs = p.os_id')->where('s.id', $id)->get()->row();
    }

    public function criar(array $composicao, int $cliente, ?int $dependente, int $profissional, string $chave): int
    {
        $this->db->trans_begin();
        try {
            // Um cliente existente serializa reenvios do cadastro sem criar pacotes duplicados.
            $sql = 'SELECT idClientes FROM clientes WHERE idClientes = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : '');
            if (! $this->db->query($sql, [$cliente])->row()) {
                throw new DomainException('Escolha uma cliente cadastrada.');
            }
            $existente = $this->db->where('chave', $chave)->get('pacotes')->row();
            if ($existente) {
                $this->db->trans_commit();
                return (int) $existente->id;
            }
            if ($dependente && ! $this->db->where('id', $dependente)->where('clientes_id', $cliente)->get('dependentes')->row()) {
                throw new DomainException('O bebê ou dependente precisa pertencer à cliente responsável.');
            }
            if (! $this->db->where('idUsuarios', $profissional)->where('situacao', 1)->get('usuarios')->row()) {
                throw new DomainException('Escolha uma profissional ativa.');
            }
            $this->db->insert('os', ['clientes_id' => $cliente, 'dependente_id' => $dependente, 'usuarios_id' => $profissional,
                'status' => 'Orçamento', 'dataInicial' => date('Y-m-d'), 'dataFinal' => date('Y-m-d'), 'faturado' => 0,
                'descricaoProduto' => htmlspecialchars($composicao['nome'], ENT_QUOTES, 'UTF-8'), 'valorTotal' => financeiroDecimal($composicao['total'])]);
            $os = (int) $this->db->insert_id();
            $this->db->insert('pacotes', ['os_id' => $os, 'nome' => $composicao['nome'], 'chave' => $chave, 'criado_em' => date('Y-m-d H:i:s')]);
            $id = (int) $this->db->insert_id();
            $numero = 0;
            foreach ($composicao['itens'] as $item) {
                $this->db->insert('servicos_os', ['os_id' => $os, 'servicos_id' => $item['servicos_id'], 'servico' => $item['nome'],
                    'quantidade' => $item['quantidade'], 'preco' => $item['preco'],
                    'subTotal' => financeiroDecimal($item['quantidade'] * financeiroCentavos($item['preco']))]);
                for ($i = 0; $i < $item['quantidade']; $i++) {
                    $this->db->insert('pacote_sessoes', ['pacote_id' => $id, 'servicos_id' => $item['servicos_id'], 'nome_servico' => $item['nome'],
                        'duracao_min' => $item['duracao_min'], 'numero' => ++$numero]);
                }
            }
            $this->concluir();
            return $id;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    /** Concluir sessão muda só seu consumo; não cria nova receita. */
    public function concluirSessao(int $pacote, int $id, bool $realizar): void
    {
        $this->db->trans_begin();
        try {
            $sql = 'SELECT * FROM pacote_sessoes WHERE id = ? AND pacote_id = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : '');
            $sessao = $this->db->query($sql, [$id, $pacote])->row();
            $contrato = $this->getById($pacote);
            if (! $sessao || ! $contrato || $contrato->status === 'Cancelado') {
                throw new DomainException('Sessão não encontrada neste pacote ou atendimento cancelado.');
            }
            if ($realizar) {
                $reserva = $sessao->agenda_id ? $this->db->where('id', $sessao->agenda_id)->get('agenda')->row() : null;
                if (! $reserva || (int) $reserva->cancelado || $reserva->fim > date('Y-m-d H:i:s')) {
                    throw new DomainException('Agende a sessão e aguarde o término do horário antes de marcá-la como realizada.');
                }
            }
            $this->db->where('id', $id)->update('pacote_sessoes', ['realizado_em' => $realizar ? ($sessao->realizado_em ?: date('Y-m-d H:i:s')) : null]);
            $this->concluir();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    private function concluir(): void
    {
        if (! $this->db->trans_status()) {
            throw new RuntimeException('Não foi possível salvar. Nenhum registro foi alterado.');
        }
        $this->db->trans_commit();
    }
}
