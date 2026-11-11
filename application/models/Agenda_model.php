<?php

class Agenda_model extends CI_Model
{
    private function ativos(): void
    {
        $this->db->from('agenda a')->join('os', 'os.idOs = a.os_id', 'left')
            ->where('a.cancelado', 0)->group_start()->where('a.os_id', null)->or_where('os.status !=', 'Cancelado')->group_end();
    }

    public function conflito(int $profissional, string $inicio, string $fim, ?int $ignorar = null): ?object
    {
        $this->ativos();
        $this->db->select('a.*')->where('a.usuarios_id', $profissional)
            ->where('a.ocupado_inicio <', $fim)->where('a.ocupado_fim >', $inicio);
        if ($ignorar !== null) {
            $this->db->where('a.id !=', $ignorar);
        }

        return $this->db->get()->row();
    }

    public function listar(string $inicio, string $fim): array
    {
        $this->ativos();

        return $this->db->select('a.*, clientes.nomeCliente, dependentes.nome as beneficiario, usuarios.nome as profissional')
            ->join('clientes', 'clientes.idClientes = os.clientes_id', 'left')
            ->join('dependentes', 'dependentes.id = os.dependente_id', 'left')
            ->join('usuarios', 'usuarios.idUsuarios = a.usuarios_id')
            ->where('a.ocupado_inicio <', $fim)->where('a.ocupado_fim >', $inicio)
            ->order_by('a.inicio', 'asc')->get()->result();
    }

    public function getById(int $id): ?object
    {
        return $this->db->where('id', $id)->get('agenda')->row();
    }

    /** Serializa reservas por profissional para duas gravações simultâneas não ocuparem o mesmo horário. */
    public function reservar(array $dados, ?int $id): ?int
    {
        $this->db->trans_begin();
        if ($this->db->dbdriver === 'mysqli') {
            // Bloqueio de uma linha existente, antes de consultar conflitos.
            $profissional = $this->db->query('SELECT idUsuarios FROM usuarios WHERE idUsuarios = ? FOR UPDATE', [$dados['usuarios_id']])->row();
            if (! $profissional) {
                $this->db->trans_rollback();
                throw new RuntimeException('Profissional não encontrada.');
            }
        }
        $conflito = $this->conflito((int) $dados['usuarios_id'], $dados['ocupado_inicio'], $dados['ocupado_fim'], $id);
        if ($conflito) {
            $this->db->trans_rollback();
            throw new DomainException('Este horário conflita com uma reserva ou bloqueio da profissional, incluindo deslocamento e intervalo.');
        }
        if ($id === null) {
            $this->db->insert('agenda', $dados);
            $id = (int) $this->db->insert_id();
        } else {
            $this->db->where('id', $id)->where('cancelado', 0)->update('agenda', $dados);
        }
        if (! $this->db->trans_status()) {
            $this->db->trans_rollback();
            return null;
        }
        $this->db->trans_commit();

        return $id;
    }
}
