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
        if ($this->db->table_exists('pacote_sessoes')) {
            $this->db->join('pacote_sessoes ps', 'ps.id = a.pacote_sessao_id', 'left')->select('ps.pacote_id, ps.realizado_em as sessao_realizada_em');
        }

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
        try {
            $this->validarSessao($dados, $id);
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
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
        if (! empty($dados['pacote_sessao_id'])) {
            $this->db->where('id', $dados['pacote_sessao_id'])->update('pacote_sessoes', ['agenda_id' => $id]);
        }
        if (! $this->db->trans_status()) {
            $this->db->trans_rollback();
            return null;
        }
        $this->db->trans_commit();

        return $id;
    }

    private function validarSessao(array &$dados, ?int $id): void
    {
        if (empty($dados['pacote_sessao_id'])) {
            if (! empty($dados['os_id']) && $this->db->table_exists('pacotes')
                && $this->db->where('os_id', $dados['os_id'])->get('pacotes')->row()) {
                throw new DomainException('Para este atendimento, agende a sessão pela tela do pacote.');
            }
            return;
        }
        $sql = 'SELECT * FROM pacote_sessoes WHERE id = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : '');
        $sessao = $this->db->query($sql, [$dados['pacote_sessao_id']])->row();
        $pacote = $sessao ? $this->db->where('id', $sessao->pacote_id)->get('pacotes')->row() : null;
        $os = $pacote ? $this->db->where('idOs', $pacote->os_id)->get('os')->row() : null;
        $servicos = json_decode($dados['servicos_json'], true);
        if (! $sessao || ! $pacote || ! $os || $os->status === 'Cancelado' || $sessao->realizado_em
            || (int) $dados['os_id'] !== (int) $pacote->os_id || $dados['tipo'] !== 'atendimento'
            || ! is_array($servicos) || count($servicos) !== 1 || (int) ($servicos[0]['id'] ?? 0) !== (int) $sessao->servicos_id) {
            throw new DomainException('Esta sessão não está disponível ou não corresponde ao atendimento e serviço selecionados.');
        }
        $anterior = $sessao->agenda_id ? $this->getById((int) $sessao->agenda_id) : null;
        if ($anterior && ! (int) $anterior->cancelado && (int) $anterior->id !== $id) {
            throw new DomainException('Esta sessão já está agendada. Edite ou cancele o horário existente.');
        }
        $dados['titulo'] = mb_substr('Sessão ' . $sessao->numero . ' · ' . $sessao->nome_servico, 0, 255);
        $dados['servicos_json'] = json_encode([['id' => (int) $sessao->servicos_id, 'nome' => $sessao->nome_servico, 'duracao_min' => (int) $sessao->duracao_min]]);
    }

    public function cancelar(int $id): void
    {
        $this->db->trans_begin();
        try {
            $evento = $this->getById($id);
            if (! $evento) {
                throw new DomainException('Reserva não encontrada.');
            }
            if (! empty($evento->pacote_sessao_id)) {
                $sql = 'SELECT * FROM pacote_sessoes WHERE id = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : '');
                $sessao = $this->db->query($sql, [$evento->pacote_sessao_id])->row();
                if ($sessao && $sessao->realizado_em) {
                    throw new DomainException('Reabra o registro da sessão realizada antes de cancelar seu horário.');
                }
            }
            $this->db->where('id', $id)->update('agenda', ['cancelado' => 1]);
            if (! $this->db->trans_status()) {
                throw new RuntimeException('Não foi possível cancelar a reserva.');
            }
            $this->db->trans_commit();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }
}
