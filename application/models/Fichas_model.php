<?php

class Fichas_model extends CI_Model
{
    public function modelos(): array
    {
        return $this->db->order_by('nome')->get('ficha_modelos')->result();
    }

    public function modelo(int $id): ?object
    {
        return $this->db->where('id', $id)->get('ficha_modelos')->row();
    }

    public function salvarModelo(?int $id, array $dados, int $versao): int
    {
        if ($id === null) {
            $this->db->insert('ficha_modelos', $dados + ['versao' => 1]);
            return (int) $this->db->insert_id();
        }
        $this->db->where('id', $id)->where('versao', $versao)->update('ficha_modelos', $dados + ['versao' => $versao + 1]);
        if ($this->db->affected_rows() !== 1) {
            throw new DomainException('O modelo foi alterado. Reabra a página antes de salvar.');
        }
        return $id;
    }

    public function ficha(int $id): ?object
    {
        return $this->db->where('id', $id)->get('fichas')->row();
    }

    public function doAtendimento(int $os): array
    {
        return $this->db->select('id, os_id, modelo_nome, servico_nome, paciente_nome, criado_em')->where('os_id', $os)->order_by('id', 'desc')->get('fichas')->result();
    }

    public function historico(int $cliente, ?int $dependente): array
    {
        return $this->db->select('id, os_id, modelo_nome, servico_nome, paciente_nome, criado_em')
            ->where('clientes_id', $cliente)->where('dependente_id', $dependente)->order_by('id', 'desc')->get('fichas')->result();
    }

    public function criar(int $osId, int $servico, int $modeloId, int $versaoModelo, array $respostas, int $usuario, string $chave, int $clienteEsperado, ?int $dependenteEsperado): int
    {
        $this->db->trans_begin();
        try {
            $os = $this->db->query('SELECT * FROM os WHERE idOs = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : ''), [$osId])->row();
            if (! $os) {
                throw new DomainException('Atendimento não encontrado.');
            }
            if ((int) $os->clientes_id !== $clienteEsperado || (int) $os->dependente_id !== (int) $dependenteEsperado) {
                throw new DomainException('A pessoa atendida mudou. Reabra o atendimento e confira a ficha antes de continuar.');
            }
            $existente = $this->db->where('chave', $chave)->get('fichas')->row();
            if ($existente) {
                if ((int) $existente->os_id !== $osId) {
                    throw new DomainException('Reabra o formulário.');
                }
                $this->db->trans_commit();
                return (int) $existente->id;
            }
            $modelo = $this->modelo($modeloId);
            $servicoOs = $this->db->where('os_id', $osId)->where('servicos_id', $servico)->get('servicos_os')->row();
            $cliente = $this->db->where('idClientes', (int) $os->clientes_id)->get('clientes')->row();
            $dependente = $os->dependente_id ? $this->db->where('id', (int) $os->dependente_id)->where('clientes_id', (int) $os->clientes_id)->get('dependentes')->row() : null;
            if (! $modelo || (int) $modelo->versao !== $versaoModelo || ! $servicoOs || ! $cliente || ($os->dependente_id && ! $dependente)) {
                throw new DomainException('Confira o serviço, o modelo e a pessoa atendida antes de criar a ficha.');
            }
            [$respostas, $erros] = fichaRespostas(['respostas' => $respostas], json_decode($modelo->campos_json, true));
            if ($erros !== []) {
                throw new DomainException('Confira as respostas da ficha.');
            }
            $this->db->insert('fichas', ['os_id' => $osId, 'clientes_id' => (int) $os->clientes_id, 'dependente_id' => $os->dependente_id ?: null,
                'servicos_id' => $servico, 'paciente_nome' => $dependente->nome ?? $cliente->nomeCliente, 'responsavel_nome' => $cliente->nomeCliente,
                'servico_nome' => $servicoOs->servico, 'modelo_nome' => $modelo->nome, 'campos_json' => $modelo->campos_json,
                'respostas_json' => json_encode($respostas, JSON_UNESCAPED_UNICODE), 'termo' => $modelo->termo,
                'usuarios_id' => $usuario, 'criado_em' => date('Y-m-d H:i:s'), 'chave' => $chave]);
            $id = (int) $this->db->insert_id();
            $this->concluir();
            return $id;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    public function evolucoes(int $ficha): array
    {
        return $this->db->select('e.*, u.nome as autor')->from('ficha_evolucoes e')->join('usuarios u', 'u.idUsuarios = e.usuarios_id', 'left')
            ->where('e.ficha_id', $ficha)->order_by('e.ocorrido_em', 'desc')->order_by('e.id', 'desc')->get()->result();
    }

    public function evoluir(int $fichaId, array $dados, int $usuario, string $chave): int
    {
        $this->db->trans_begin();
        try {
            $ficha = $this->db->query('SELECT * FROM fichas WHERE id = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : ''), [$fichaId])->row();
            if (! $ficha) {
                throw new DomainException('Ficha não encontrada.');
            }
            $existente = $this->db->where('chave', $chave)->get('ficha_evolucoes')->row();
            if ($existente) {
                if ((int) $existente->ficha_id !== $fichaId) {
                    throw new DomainException('Reabra o formulário.');
                }
                $this->db->trans_commit();
                return (int) $existente->id;
            }
            if ($dados['agenda_id']) {
                $agenda = $this->db->where('id', $dados['agenda_id'])->where('os_id', $ficha->os_id)->where('cancelado', 0)->get('agenda')->row();
                if (! $agenda || $agenda->fim > date('Y-m-d H:i:s')) {
                    throw new DomainException('Escolha uma sessão deste atendimento que já terminou e não esteja cancelada.');
                }
            }
            $this->db->insert('ficha_evolucoes', $dados + ['ficha_id' => $fichaId, 'usuarios_id' => $usuario, 'criado_em' => date('Y-m-d H:i:s'), 'chave' => $chave,
                'retorno_status' => 'pendente', 'retorno_historico' => '[]']);
            $id = (int) $this->db->insert_id();
            $this->concluir();
            return $id;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    public function retornos(string $status, string $ate, int $offset, int $limite): array
    {
        return $this->db->select('e.id, e.ficha_id, e.retorno, e.retorno_status, f.paciente_nome, f.responsavel_nome, f.servico_nome, f.os_id')->from('ficha_evolucoes e')
            ->join('fichas f', 'f.id = e.ficha_id')->where('e.retorno IS NOT NULL', null, false)->where('e.retorno_status', $status)->where('e.retorno <=', $ate)
            ->order_by('e.retorno')->order_by('e.id')->limit($limite, $offset)->get()->result();
    }

    public function contarRetornos(string $status, string $ate): int
    {
        return (int) $this->db->where('retorno IS NOT NULL', null, false)->where('retorno_status', $status)->where('retorno <=', $ate)->count_all_results('ficha_evolucoes');
    }

    public function retorno(int $ficha, int $registro, string $status, string $nota, int $usuario): void
    {
        if (! in_array($status, ['pendente', 'concluido'], true) || trim($nota) === '' || mb_strlen($nota) > 1000) {
            throw new DomainException('Informe a situação e uma observação de até 1000 caracteres.');
        }
        $this->db->trans_begin();
        try {
            $evolucao = $this->db->query('SELECT * FROM ficha_evolucoes WHERE id = ? AND ficha_id = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : ''), [$registro, $ficha])->row();
            if (! $evolucao || ! $evolucao->retorno) {
                throw new DomainException('Retorno não encontrado nesta ficha.');
            }
            if ($evolucao->retorno_status !== $status) {
                $historico = json_decode($evolucao->retorno_historico, true) ?: [];
                $historico[] = ['status' => $status, 'nota' => $nota, 'usuario' => $usuario, 'em' => date('Y-m-d H:i:s')];
                $this->db->where('id', $registro)->where('ficha_id', $ficha)->update('ficha_evolucoes', ['retorno_status' => $status, 'retorno_historico' => json_encode($historico, JSON_UNESCAPED_UNICODE)]);
            }
            $this->concluir();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    public function arquivos(int $ficha): array
    {
        return $this->db->select('id, ficha_id, tipo, descricao, signatario, assinado_em, nome, criado_em')->where('ficha_id', $ficha)->order_by('id', 'desc')->get('ficha_arquivos')->result();
    }

    public function arquivo(int $ficha, int $id): ?object
    {
        return $this->db->where('id', $id)->where('ficha_id', $ficha)->get('ficha_arquivos')->row();
    }

    public function anexar(int $ficha, array $dados): bool
    {
        if (! $this->ficha($ficha)) {
            return false;
        }
        return $this->db->insert('ficha_arquivos', ['ficha_id' => $ficha] + $dados);
    }

    private function concluir(): void
    {
        if (! $this->db->trans_status()) {
            throw new RuntimeException('Não foi possível salvar. Nenhum registro foi alterado.');
        }
        $this->db->trans_commit();
    }
}
