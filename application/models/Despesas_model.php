<?php

class Despesas_model extends CI_Model
{
    private function filtrar(string $pesquisa, string $categoria): void
    {
        $this->db->from('despesa_grupos g');
        if ($pesquisa !== '') {
            $this->db->like('g.descricao', $pesquisa);
        }
        if ($categoria !== '') {
            $this->db->where('g.categoria', $categoria);
        }
    }

    public function listar(string $pesquisa, string $categoria, int $limite, int $offset): array
    {
        $this->filtrar($pesquisa, $categoria);
        return $this->db->select('g.*')->order_by('g.id', 'desc')->limit($limite, $offset)->get()->result();
    }

    public function contar(string $pesquisa, string $categoria): int
    {
        $this->filtrar($pesquisa, $categoria);
        return (int) $this->db->count_all_results();
    }

    public function getById(int $id): ?object
    {
        return $this->db->where('id', $id)->get('despesa_grupos')->row();
    }

    public function linhas(int $id): array
    {
        return $this->db->where('despesa_grupo_id', $id)->order_by('data_vencimento')->order_by('idLancamentos')->get('lancamentos')->result();
    }

    public function resumo(int $id): array
    {
        $linhas = $this->linhas($id);
        $total = 0;
        $pago = 0;
        $pagas = 0;
        foreach ($linhas as $linha) {
            $valor = (int) round(financeiroLiquido($linha) * 100);
            $total += $valor;
            if ((int) $linha->baixado === 1) {
                $pago += $valor;
                $pagas++;
            }
        }
        return ['total' => $total, 'pago' => $pago, 'pendente' => $total - $pago, 'pagas' => $pagas, 'quantidade' => count($linhas)];
    }

    public function criar(array $grupo, array $linhas, int $usuario): int
    {
        $this->db->trans_begin();
        try {
            $sql = 'SELECT idUsuarios FROM usuarios WHERE idUsuarios = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : '');
            if (! $this->db->query($sql, [$usuario])->row()) {
                throw new DomainException('Usuário não encontrado.');
            }
            $existente = $this->db->where('chave', $grupo['chave'])->get('despesa_grupos')->row();
            if ($existente) {
                $this->db->trans_commit();
                return (int) $existente->id;
            }
            if ($linhas === []) {
                throw new DomainException('A despesa deve ter ao menos um lançamento.');
            }
            $this->db->insert('despesa_grupos', $grupo);
            $id = (int) $this->db->insert_id();
            foreach ($linhas as $linha) {
                $linha['usuarios_id'] = $usuario;
                $linha['despesa_grupo_id'] = $id;
                $linha['tipo'] = 'despesa';
                $this->db->insert('lancamentos', $linha);
            }
            if (! $this->db->trans_status()) {
                throw new RuntimeException('Não foi possível lançar a despesa. Nenhuma parcela foi gravada.');
            }
            $this->db->trans_commit();
            return $id;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }
}
