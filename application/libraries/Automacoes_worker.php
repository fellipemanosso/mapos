<?php

class Automacoes_worker
{
    public ?object $ci = null;

    public function executar(int $limite = 20): array
    {
        $ci = $this->ci;
        if ($ci === null) {
            $ci = &get_instance();
        }
        $ci->load->helper(['automacoes', 'evolution']);
        $ci->load->model(['automacoes_model', 'lembretes_model', 'evolution_model']);
        $ci->load->library(['encryption', 'automacoes_http']);
        $pasta = armazenamentoPasta('automacoes');
        if (! $pasta || ! ($lock = fopen($pasta . '/worker.lock', 'c'))) {
            throw new RuntimeException('Não foi possível abrir a trava do processamento.');
        }
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return ['ocupado' => 1, 'enviados' => 0];
        }
        $resultado = ['enviados' => 0, 'incertos' => 0, 'cancelados' => 0];
        try {
            $agora = date('Y-m-d H:i:s');
            $config = $ci->automacoes_model->configuracao();
            $ci->db->where('id', 1)->update('automacoes_config', ['ultimo_worker' => $agora]);
            // Um processo interrompido não pode reenviar algo possivelmente entregue.
            $ci->db->where('estado', 'enviando')->update('wa_mensagens', ['estado' => 'incerto', 'erro' => 'Processamento interrompido. Confira o WhatsApp antes de reenviar.']);
            if (date('H:i') < $config['inicio_envio'] || date('H:i') >= $config['fim_envio']) {
                return $resultado;
            }
            $evolution = $ci->evolution_model->configuracao();
            $chave = $evolution ? $ci->encryption->decrypt($evolution->chave_cifrada) : false;
            if (! is_string($chave) || $chave === '') {
                return $resultado + ['nao_configurado' => 1];
            }
            foreach ($ci->lembretes_model->candidatos($agora) as $candidato) {
                $flag = match ($candidato['tipo']) {
                    'confirmacao', 'lembrete' => 'lembretes', 'cobranca' => 'cobrancas', default => 'retornos'
                };
                if (! $config[$flag]) {
                    continue;
                }
                foreach ($ci->db->where('cliente_id', $candidato['cliente'])->where('lembretes', 1)->where('optout', 0)->get('wa_conversas')->result() as $conversa) {
                    $ci->automacoes_model->fila(
                        (int) $conversa->id,
                        automacoesTexto($config[$candidato['tipo']], ['profissional' => $config['nome']] + $candidato['valores']),
                        $candidato['tipo'],
                        hash('sha256', $candidato['tipo'] . ':' . $candidato['id'] . ':' . $candidato['versao'] . ':' . $conversa->id),
                        $candidato['id'],
                        $candidato['versao']
                    );
                }
            }
            $ids = $ci->db->select('id')->where_in('estado', ['pendente', 'aguarda_ia'])->order_by('id')->limit(max(1, min(50, $limite)))->get('wa_mensagens')->result();
            foreach ($ids as $linha) {
                $config = $ci->automacoes_model->configuracao();
                $enviados = $ci->db->where('direcao', 'saida')->where_in('estado', ['enviado', 'enviando', 'incerto'])->where('processado_em >=', date('Y-m-d') . ' 00:00:00')->count_all_results('wa_mensagens');
                if ($enviados >= $config['limite_diario']) {
                    break;
                }
                $ci->db->trans_begin();
                try {
                    $ci->db->query('SELECT id FROM automacoes_config WHERE id = 1' . ($ci->db->dbdriver === 'mysqli' ? ' FOR UPDATE' : ''))->row();
                    $m = $ci->db->where('id', $linha->id)->get('wa_mensagens')->row();
                    $c = $ci->automacoes_model->conversa((int) $m->conversa_id);
                    $flag = match ($m->tipo) {
                        'confirmacao', 'lembrete' => 'lembretes', 'cobranca' => 'cobrancas', 'retorno' => 'retornos', default => null
                    };
                    $permitido = in_array($m->estado, ['pendente', 'aguarda_ia'], true) && ! $c->optout
                        && strtotime($m->criado_em) > time() - 86400 && (! $flag || $config[$flag])
                        && $ci->lembretes_model->valido($m, $c, date('Y-m-d H:i:s'));
                    if ($m->estado === 'aguarda_ia' || $m->tipo === 'ia') {
                        $permitido = $permitido && $config['automatico'] && $config['ia'] && $c->modo === 'ia' && $c->consentimento_ia;
                    }
                    if ($m->tipo === 'boas_vindas') {
                        $permitido = $permitido && $config['automatico'];
                    }
                    if (! $permitido) {
                        $ci->db->where('id', $m->id)->update('wa_mensagens', ['estado' => 'cancelado']);
                        $ci->db->trans_commit();
                        $resultado['cancelados']++;
                        continue;
                    }
                    if ($m->estado === 'aguarda_ia') {
                        try {
                            $servicos = $ci->db->select('nome, duracao_min')->order_by('nome')->limit(100)->get('servicos')->result_array();
                            $texto = $ci->automacoes_http->responder($config, $m->texto, $servicos);
                            if (str_contains($texto, '[HUMANO]')) {
                                throw new RuntimeException('Solicitação encaminhada à profissional.');
                            }
                            $novo = $ci->automacoes_model->fila((int) $c->id, $texto, 'ia', hash('sha256', 'ia:' . $m->id));
                            $ci->db->where('id', $m->id)->update('wa_mensagens', ['estado' => 'respondido']);
                            $m = $ci->db->where('id', $novo)->get('wa_mensagens')->row();
                        } catch (RuntimeException $e) {
                            $ci->db->where('id', $c->id)->update('wa_conversas', ['modo' => 'humano']);
                            $ci->db->where('id', $m->id)->update('wa_mensagens', ['estado' => 'humano', 'erro' => 'A IA não respondeu. A profissional deve assumir.']);
                            $ci->db->trans_commit();
                            continue;
                        }
                    }
                    // Persiste ANTES da requisição: se morrer, fica incerto e não é reenviado.
                    $ci->db->where('id', $m->id)->update('wa_mensagens', ['estado' => 'enviando', 'processado_em' => date('Y-m-d H:i:s')]);
                    $ci->db->trans_commit();
                    try {
                        $externa = $ci->automacoes_http->enviar($evolution, $chave, $c->telefone, $m->texto);
                        $ci->db->where('id', $m->id)->update('wa_mensagens', ['estado' => 'enviado', 'externa' => $externa, 'erro' => '']);
                        $resultado['enviados']++;
                    } catch (RuntimeException $e) {
                        $ci->db->where('id', $m->id)->update('wa_mensagens', ['estado' => 'incerto', 'erro' => 'Entrega não confirmada. Confira no WhatsApp antes de reenviar.']);
                        $resultado['incertos']++;
                    }
                } catch (Throwable $e) {
                    $ci->db->trans_rollback();
                    throw $e;
                }
            }
            return $resultado;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
