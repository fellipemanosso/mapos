<?php

class Automacoes_model extends CI_Model
{
    public function configuracao(): array
    {
        $r = $this->db->where('id', 1)->get('automacoes_config')->row();
        return array_replace(automacoesPadrao(), json_decode($r->dados_json ?? '{}', true) ?: []);
    }

    public function salvar(array $dados, int $versao): void
    {
        $this->db->where('id', 1)->where('versao', $versao)->update('automacoes_config', [
            'dados_json' => json_encode(array_intersect_key($dados, automacoesPadrao()), JSON_UNESCAPED_UNICODE),
            'versao' => $versao + 1, 'atualizado_em' => date('Y-m-d H:i:s')]);
        if ($this->db->affected_rows() !== 1) {
            throw new DomainException('A configuração foi alterada. Reabra a página antes de salvar.');
        }
    }

    public function estado(): object
    {
        return $this->db->where('id', 1)->get('automacoes_config')->row();
    }

    public function conversa(int $id): ?object
    {
        return $this->db->where('id', $id)->get('wa_conversas')->row();
    }

    public function fila(int $conversa, string $texto, string $tipo, string $chave, ?int $contexto = null, ?string $versao = null): int
    {
        $antiga = $this->db->where('chave', $chave)->get('wa_mensagens')->row();
        if ($antiga) {
            if ((int) $antiga->conversa_id !== $conversa) {
                throw new DomainException('Chave de envio não pertence à conversa.');
            }
            return (int) $antiga->id;
        }
        $c = $this->conversa($conversa);
        if (! $c || $c->optout || trim($texto) === '' || mb_strlen($texto) > 4000) {
            throw new DomainException('Conversa indisponível ou mensagem inválida.');
        }
        $this->db->insert('wa_mensagens', ['conversa_id' => $conversa, 'direcao' => 'saida', 'tipo' => $tipo,
            'texto' => $texto, 'estado' => 'pendente', 'chave' => $chave, 'contexto_id' => $contexto,
            'contexto_versao' => $versao, 'criado_em' => date('Y-m-d H:i:s')]);
        return (int) $this->db->insert_id();
    }

    /** A linha singleton serializa eventos concorrentes e a criação do contato. */
    public function receber(array $evento): ?int
    {
        $this->db->trans_begin();
        try {
            $this->db->query('SELECT id FROM automacoes_config WHERE id = 1' . ($this->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : ''))->row();
            $antiga = $this->db->where('chave', $evento['chave'])->get('wa_mensagens')->row();
            if ($antiga) {
                $this->db->trans_commit();
                return (int) $antiga->conversa_id;
            }
            // Eco do nosso envio não deve pausar o assistente nem duplicar a mensagem.
            if ($evento['propria'] && $this->db->where('externa', $evento['externa'])->where('direcao', 'saida')->get('wa_mensagens')->row()) {
                $this->db->trans_commit();
                return null;
            }
            $c = $this->db->where('telefone', $evento['telefone'])->get('wa_conversas')->row();
            if ($c && $evento['propria']) {
                $enviando = $this->db->where('conversa_id', $c->id)->where('estado', 'enviando')->where('texto', $evento['texto'])->get('wa_mensagens')->row();
                if ($enviando) {
                    $this->db->where('id', $enviando->id)->update('wa_mensagens', ['externa' => $evento['externa'], 'estado' => 'enviado']);
                    $this->db->trans_commit();
                    return (int) $c->id;
                }
            }
            $nova = ! $c;
            $config = $this->configuracao();
            if (! $c) {
                $this->db->insert('wa_conversas', ['telefone' => $evento['telefone'], 'nome' => $evento['nome'],
                    'atualizado_em' => date('Y-m-d H:i:s')]);
                $c = $this->conversa((int) $this->db->insert_id());
                if (! $evento['propria']) {
                    $telefone = substr($evento['telefone'], 2);
                    $lead = $this->db->where('telefone', $telefone)->order_by('id', 'desc')->get('leads')->row();
                    if (! $lead) {
                        $this->db->insert('leads', ['nome' => $evento['nome'], 'telefone' => $telefone, 'origem' => 'whatsapp',
                            'interesse' => 'Contato recebido pelo WhatsApp', 'observacoes' => '', 'condicoes' => '', 'itens_json' => '[]',
                            'chave' => bin2hex(random_bytes(16)), 'criado_em' => date('Y-m-d H:i:s')]);
                        $lead = $this->db->where('id', $this->db->insert_id())->get('leads')->row();
                    }
                    $this->db->where('id', $c->id)->update('wa_conversas', ['lead_id' => $lead->id]);
                }
            }
            $contagem = $this->db->where('conversa_id', $c->id)->where('criado_em >=', date('Y-m-d H:i:s', time() - 3600))->count_all_results('wa_mensagens');
            if ($contagem >= 200) {
                throw new DomainException('Limite de mensagens por contato atingido.');
            }
            $this->db->insert('wa_mensagens', ['conversa_id' => $c->id, 'direcao' => $evento['propria'] ? 'saida' : 'entrada',
                'tipo' => $evento['midia'] ? 'midia' : 'texto', 'texto' => $evento['texto'], 'estado' => 'recebido',
                'chave' => $evento['chave'], 'externa' => $evento['externa'], 'criado_em' => date('Y-m-d H:i:s')]);
            $idMensagem = (int) $this->db->insert_id();
            $comando = mb_strtoupper(trim($evento['texto']));
            $alterar = ['atualizado_em' => date('Y-m-d H:i:s')];
            if ($comando === 'SAIR' && ! $evento['propria']) {
                $alterar += ['optout' => 1, 'modo' => 'humano', 'lembretes' => 0, 'consentimento_ia' => 0];
                $this->cancelarPendentes((int) $c->id);
            } elseif ($comando === 'ENTRAR' && ! $evento['propria'] && ! $evento['antiga']) {
                $alterar += ['optout' => 0, 'modo' => 'humano'];
            } elseif ($comando === 'CONFIRMAR' && ! $evento['propria'] && ! $evento['antiga']) {
                // A resposta fica destacada para a profissional; não confirma um horário ambíguo automaticamente.
                $this->db->where('id', $idMensagem)->update('wa_mensagens', ['tipo' => 'confirmacao_cliente']);
                $alterar['modo'] = 'humano';
                $this->cancelarPendentes((int) $c->id, ['ia', 'boas_vindas', 'texto']);
            } elseif ($evento['propria'] || $evento['midia'] || automacoesHumano($evento['texto'])) {
                $alterar['modo'] = 'humano';
                $this->cancelarPendentes((int) $c->id, ['ia', 'boas_vindas', 'texto']);
            } elseif ($comando === 'AUTORIZO IA' && ! $c->optout && ! $evento['antiga']) {
                $alterar += ['consentimento_ia' => 1, 'modo' => 'ia', 'consentimento_em' => date('Y-m-d H:i:s')];
                $this->fila((int) $c->id, 'Autorização registrada. Sou uma assistente virtual com IA. Qual serviço você procura? Não envie dados de saúde por aqui. Escreva HUMANO para falar com a profissional.', 'boas_vindas', hash('sha256', 'consentimento:' . $idMensagem));
            } elseif ($comando === 'AUTORIZO LEMBRETES' && ! $c->optout && ! $evento['antiga']) {
                $alterar += ['lembretes' => 1, 'consentimento_em' => date('Y-m-d H:i:s')];
            } elseif (! $c->optout && ! $evento['antiga'] && $config['automatico']) {
                if ($nova) {
                    $this->fila((int) $c->id, automacoesTexto($config['boas_vindas'], ['profissional' => $config['nome']]), 'boas_vindas', hash('sha256', 'bemvinda:' . $c->id));
                } elseif ($config['ia'] && $c->modo === 'ia' && $c->consentimento_ia) {
                    $this->db->where('id', $idMensagem)->update('wa_mensagens', ['estado' => 'aguarda_ia']);
                }
            }
            $this->db->where('id', $c->id)->update('wa_conversas', $alterar);
            if (! $this->db->trans_status()) {
                throw new RuntimeException('Não foi possível registrar a mensagem.');
            }
            $this->db->trans_commit();
            return (int) $c->id;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    public function cancelarPendentes(int $id, array $tipos = []): void
    {
        if ($tipos !== []) {
            $this->db->where_in('tipo', $tipos);
        }
        $this->db->where('conversa_id', $id)->where_in('estado', ['pendente', 'aguarda_ia'])->update('wa_mensagens', ['estado' => 'cancelado']);
    }

    public function ajustar(int $id, string $modo, ?int $cliente, bool $lembretes): void
    {
        $c = $this->conversa($id);
        if (! $c || ! in_array($modo, ['humano', 'ia'], true) || ($modo === 'ia' && (! $c->consentimento_ia || $c->optout))) {
            throw new DomainException('A cliente precisa autorizar a IA antes de ativá-la.');
        }
        if ($cliente && ! $this->db->where('idClientes', $cliente)->where('fornecedor', 0)->get('clientes')->row()) {
            throw new DomainException('Cliente não encontrada.');
        }
        // Opt-out só é removido pela própria cliente, nunca pelo operador.
        if ($c->optout && $lembretes) {
            throw new DomainException('Esta pessoa solicitou a interrupção das mensagens.');
        }
        $this->db->where('id', $id)->update('wa_conversas', ['modo' => $modo, 'cliente_id' => $cliente, 'lembretes' => (int) $lembretes,
            'consentimento_em' => $lembretes ? date('Y-m-d H:i:s') : $c->consentimento_em]);
        if ($modo === 'humano') {
            $this->cancelarPendentes($id, ['ia', 'boas_vindas', 'texto']);
        }
        if (! $lembretes || (int) $cliente !== (int) $c->cliente_id) {
            $this->cancelarPendentes($id, ['confirmacao', 'lembrete', 'cobranca', 'retorno']);
        }
    }
}
