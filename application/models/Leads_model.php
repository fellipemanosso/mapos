<?php

class Leads_model extends CI_Model
{
    private function filtro(array $filtros): void
    {
        $this->db->from('leads');
        if (! empty($filtros['etapa'])) {
            $this->db->where('etapa', $filtros['etapa']);
        }
        if (! empty($filtros['pesquisa'])) {
            $this->db->group_start()->like('nome', $filtros['pesquisa'])->or_like('telefone', $filtros['pesquisa'])->or_like('bebe', $filtros['pesquisa'])->or_like('interesse', $filtros['pesquisa'])->group_end();
        }
        if (($filtros['retornos'] ?? '') === 'hoje') {
            $this->db->where('retorno <=', date('Y-m-d'))->where_not_in('etapa', ['convertido', 'perdido']);
        }
    }

    public function listar(array $filtros, int $offset, int $limite): array
    {
        $this->filtro($filtros);
        return $this->db->order_by('id', 'desc')->limit($limite, $offset)->get()->result();
    }

    public function contar(array $filtros): int
    {
        $this->filtro($filtros);
        return (int) $this->db->count_all_results();
    }

    public function getById(int $id): ?object
    {
        return $this->db->where('id', $id)->get('leads')->row();
    }

    public function salvar(?int $id, array $dados, string $chave, int $versao): int
    {
        if ($id === null) {
            $existente = $this->db->where('chave', $chave)->get('leads')->row();
            if ($existente) {
                return (int) $existente->id;
            }
            if (! $this->db->insert('leads', $dados + ['chave' => $chave, 'etapa' => 'novo', 'versao' => 1, 'criado_em' => date('Y-m-d H:i:s')])) {
                throw new RuntimeException('Não foi possível cadastrar o contato.');
            }
            return (int) $this->db->insert_id();
        }
        $this->db->where('id', $id)->where('versao', $versao)->where('os_id', null)
            ->update('leads', $dados + ['versao' => $versao + 1]);
        if ($this->db->affected_rows() !== 1) {
            throw new DomainException('O contato foi alterado ou convertido. Reabra a página antes de continuar.');
        }
        return $id;
    }

    public function etapa(int $id, string $etapa, string $motivo, int $versao): void
    {
        $lead = $this->getById($id);
        if (! $lead || ! isset(LEADS_ETAPAS[$etapa]) || $etapa === 'convertido' || $lead->os_id) {
            throw new DomainException('Etapa inválida. A conversão deve ser feita pelo formulário de atendimento.');
        }
        if ($etapa === 'perdido' && ($motivo === '' || mb_strlen($motivo) > 255)) {
            throw new DomainException('Informe o motivo da perda, com até 255 caracteres.');
        }
        if (in_array($etapa, ['enviado', 'sinal'], true) && financeiroCentavos((string) $lead->total) <= 0) {
            throw new DomainException('Prepare um orçamento com serviços antes de avançar para esta etapa.');
        }
        $this->db->where('id', $id)->where('versao', $versao)->where('os_id', null)
            ->update('leads', ['etapa' => $etapa, 'motivo_perda' => $etapa === 'perdido' ? $motivo : '', 'versao' => $versao + 1]);
        if ($this->db->affected_rows() !== 1) {
            throw new DomainException('O contato foi alterado. Reabra a página.');
        }
    }

    public function candidatas(string $telefone): array
    {
        $clientes = $this->db->select('idClientes, nomeCliente, celular, telefone')->where('fornecedor', 0)->get('clientes')->result();
        return array_values(array_filter($clientes, static fn ($cliente) => leadTelefone($cliente->celular ?? '') === $telefone || leadTelefone($cliente->telefone ?? '') === $telefone));
    }

    public function converter(int $id, int $cliente, int $profissional, int $versao): int
    {
        $anterior = $this->getById($id);
        if (! $anterior) {
            throw new DomainException('Contato não encontrado.');
        }
        $this->db->trans_begin();
        try {
            // Serializa conversões de contatos com o mesmo telefone, inclusive em leads distintos.
            $this->db->query('SELECT id FROM leads WHERE telefone = ? ORDER BY id' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : ''), [$anterior->telefone])->result();
            $lead = $this->db->query('SELECT * FROM leads WHERE id = ?' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : ''), [$id])->row();
            if ($lead->os_id) {
                $this->db->trans_commit();
                return (int) $lead->os_id;
            }
            if ((int) $lead->versao !== $versao || $lead->etapa === 'perdido') {
                throw new DomainException('Reabra o contato e confira a etapa e os valores antes de converter.');
            }
            $itens = json_decode($lead->itens_json, true);
            if (! is_array($itens) || $itens === [] || financeiroCentavos((string) $lead->total) <= 0) {
                throw new DomainException('Inclua serviços no orçamento antes de converter.');
            }
            if ($lead->validade && $lead->validade < date('Y-m-d')) {
                throw new DomainException('O orçamento venceu. Atualize a validade antes de converter.');
            }
            if (! $this->db->where('idUsuarios', $profissional)->where('situacao', 1)->get('usuarios')->row()) {
                throw new DomainException('Escolha uma profissional ativa.');
            }
            if ($cliente === 0) {
                if ($this->candidatas($lead->telefone) !== []) {
                    throw new DomainException('Já há uma cliente com este telefone. Selecione o cadastro existente para evitar duplicidade.');
                }
                $this->db->insert('clientes', ['nomeCliente' => $lead->nome, 'telefone' => $lead->telefone, 'celular' => $lead->telefone,
                    'documento' => '', 'email' => '', 'senha' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    'dataCadastro' => date('Y-m-d'), 'fornecedor' => 0]);
                $cliente = (int) $this->db->insert_id();
            } elseif (! $this->db->where('idClientes', $cliente)->where('fornecedor', 0)->get('clientes')->row()) {
                throw new DomainException('Escolha uma cliente válida.');
            }
            $dependente = null;
            if ($lead->bebe !== '') {
                $existente = $this->db->where('clientes_id', $cliente)->where('nome', $lead->bebe)->get('dependentes')->row();
                if ($existente) {
                    $dependente = (int) $existente->id;
                } else {
                    $this->db->insert('dependentes', ['clientes_id' => $cliente, 'nome' => $lead->bebe, 'parentesco' => 'Filho(a)']);
                    $dependente = (int) $this->db->insert_id();
                }
            }
            foreach ($itens as $item) {
                if (! $this->db->where('idServicos', $item['servicos_id'])->get('servicos')->row()) {
                    throw new DomainException('Um serviço foi removido. Revise o orçamento antes de converter.');
                }
            }
            $this->db->insert('os', ['clientes_id' => $cliente, 'dependente_id' => $dependente, 'usuarios_id' => $profissional,
                'status' => 'Aprovado', 'dataInicial' => date('Y-m-d'), 'faturado' => 0, 'valorTotal' => $lead->total,
                'sinal_previsto' => $lead->sinal, 'descricaoProduto' => htmlspecialchars($lead->interesse, ENT_QUOTES, 'UTF-8'),
                'observacoes' => htmlspecialchars($lead->condicoes, ENT_QUOTES, 'UTF-8')]);
            $os = (int) $this->db->insert_id();
            foreach ($itens as $item) {
                $this->db->insert('servicos_os', ['os_id' => $os, 'servicos_id' => $item['servicos_id'], 'servico' => mb_substr($item['nome'], 0, 80),
                    'quantidade' => $item['quantidade'], 'preco' => $item['preco'], 'subTotal' => financeiroDecimal($item['quantidade'] * financeiroCentavos($item['preco']))]);
            }
            $this->db->where('id', $id)->update('leads', ['os_id' => $os, 'etapa' => 'convertido', 'versao' => $versao + 1]);
            if (! $this->db->trans_status()) {
                throw new RuntimeException('Não foi possível converter. Nenhum registro foi alterado.');
            }
            $this->db->trans_commit();
            return $os;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }
}
