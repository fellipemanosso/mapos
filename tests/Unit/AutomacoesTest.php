<?php

require_once APPPATH . 'helpers/automacoes_helper.php';
require_once APPPATH . 'helpers/evolution_helper.php';
require_once APPPATH . 'helpers/armazenamento_helper.php';
require_once APPPATH . 'models/Automacoes_model.php';
require_once APPPATH . 'models/Lembretes_model.php';
require_once APPPATH . 'libraries/Automacoes_http.php';
require_once APPPATH . 'libraries/Automacoes_worker.php';
require_once APPPATH . 'libraries/Backup_operacao.php';
require_once APPPATH . 'core/MY_Controller.php';
require_once APPPATH . 'controllers/Conversas.php';
require_once APPPATH . 'controllers/Rotinas.php';

final class AutomacoesTest extends MaposTestCase
{
    private function modelo(): Automacoes_model
    {
        $this->db->query('CREATE TABLE automacoes_config (id INTEGER PRIMARY KEY, dados_json TEXT, versao INTEGER DEFAULT 1, atualizado_em TEXT, ultimo_worker TEXT)');
        $this->db->insert('automacoes_config', ['id' => 1, 'dados_json' => json_encode(automacoesPadrao())]);
        $this->db->query("CREATE TABLE wa_conversas (id INTEGER PRIMARY KEY AUTOINCREMENT, telefone TEXT UNIQUE, nome TEXT, lead_id INTEGER, cliente_id INTEGER, modo TEXT DEFAULT 'humano', consentimento_ia INTEGER DEFAULT 0, lembretes INTEGER DEFAULT 0, optout INTEGER DEFAULT 0, atualizado_em TEXT, consentimento_em TEXT)");
        $this->db->query("CREATE TABLE wa_mensagens (id INTEGER PRIMARY KEY AUTOINCREMENT, conversa_id INTEGER, direcao TEXT, tipo TEXT, texto TEXT, estado TEXT, chave TEXT UNIQUE, externa TEXT, contexto_id INTEGER, contexto_versao TEXT, criado_em TEXT, processado_em TEXT, erro TEXT DEFAULT '')");
        $this->db->query('CREATE TABLE leads (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, telefone TEXT, origem TEXT, interesse TEXT, observacoes TEXT, condicoes TEXT, itens_json TEXT, chave TEXT, criado_em TEXT)');
        $this->db->query('CREATE TABLE clientes (idClientes INTEGER PRIMARY KEY, fornecedor INTEGER)');
        $this->db->insert('clientes', ['idClientes' => 1, 'fornecedor' => 0]);
        $m = $this->makeInstance(Automacoes_model::class);
        $m->db = $this->db;
        return $m;
    }

    private function evento(string $texto = 'Olá', string $id = 'MSG1', bool $propria = false): array
    {
        return ['instance' => 'teste', 'event' => 'messages.upsert', 'data' => ['key' => ['remoteJid' => '5541999991234@s.whatsapp.net', 'id' => $id, 'fromMe' => $propria],
            'pushName' => 'Contato fictício', 'messageTimestamp' => time(), 'message' => ['conversation' => $texto]]];
    }

    public function testNormalizaBrasilRecusaGruposLidOutraInstancia(): void
    {
        $this->assertSame('5541999991234', whatsappTelefone('(41) 99999-1234'));
        $this->assertNull(whatsappTelefone('+1 2345678901'));
        $this->assertNull(whatsappTelefone([]));
        $this->assertNull(whatsappEvento($this->evento(), 'outra'));
        $this->assertNull(whatsappEvento(['instance' => 'teste', 'event' => []], 'teste'));
        $invalido = $this->evento();
        $invalido['data']['message'] = 'inválida';
        $this->assertNull(whatsappEvento($invalido, 'teste'));
        foreach (['123@g.us', '123@lid', 'status@broadcast', 'x@s.whatsapp.net'] as $jid) {
            $evento = $this->evento();
            $evento['data']['key']['remoteJid'] = $jid;
            $this->assertNull(whatsappEvento($evento, 'teste'));
        }
        $this->assertFalse(whatsappEvento($this->evento(), 'teste')['antiga']);
    }

    public function testEventoDuplicadoCriaUmContatoUmLeadEUmaMensagem(): void
    {
        $m = $this->modelo();
        $e = whatsappEvento($this->evento(), 'teste');
        $id = $m->receber($e);
        $this->assertSame($id, $m->receber($e));
        $this->assertSame(1, $this->db->count_all('leads'));
        $this->assertSame(1, $this->db->count_all('wa_conversas'));
        $this->assertSame(1, $this->db->count_all('wa_mensagens'));
        $this->assertSame('41999991234', $this->db->get('leads')->row()->telefone);
    }

    public function testAutorizacaoIAHumanoOptoutEReentrada(): void
    {
        $m = $this->modelo();
        $id = $m->receber(whatsappEvento($this->evento(), 'teste'));
        try {
            $m->ajustar($id, 'ia', null, false);
            $this->fail('Não pode ativar IA sem consentimento.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('autorizar', $e->getMessage());
        }
        $m->receber(whatsappEvento($this->evento('AUTORIZO IA', 'M2'), 'teste'));
        $this->assertSame('ia', $m->conversa($id)->modo);
        $this->assertSame(1, (int) $m->conversa($id)->consentimento_ia);
        $m->fila($id, 'Resposta preparada', 'ia', hash('sha256', 'resposta'));
        $m->receber(whatsappEvento($this->evento('Quero falar com humano', 'M3'), 'teste'));
        $this->assertSame('humano', $m->conversa($id)->modo);
        $this->assertSame(0, $this->db->where('estado', 'pendente')->count_all_results('wa_mensagens'));
        $m->receber(whatsappEvento($this->evento('SAIR', 'M4'), 'teste'));
        $this->assertSame(1, (int) $m->conversa($id)->optout);
        try {
            $m->fila($id, 'Não enviar', 'manual', hash('sha256', 'bloqueada'));
            $this->fail('Optout deve bloquear todos os envios.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('indisponível', $e->getMessage());
        }
        $m->receber(whatsappEvento($this->evento('ENTRAR', 'M5'), 'teste'));
        $this->assertSame(0, (int) $m->conversa($id)->optout);
        $this->assertSame(0, (int) $m->conversa($id)->consentimento_ia);
    }

    public function testMensagemPropriaPausaMasEcoDoSistemaNaoPausa(): void
    {
        $m = $this->modelo();
        $id = $m->receber(whatsappEvento($this->evento('AUTORIZO IA'), 'teste'));
        $fila = $m->fila($id, 'Resposta comercial', 'ia', hash('sha256', 'bot'));
        $this->db->where('id', $fila)->update('wa_mensagens', ['estado' => 'enviando']);
        $m->receber(whatsappEvento($this->evento('Resposta comercial', 'ECO', true), 'teste'));
        $this->assertSame('ia', $m->conversa($id)->modo);
        $this->assertSame('enviado', $this->db->where('id', $fila)->get('wa_mensagens')->row()->estado);
        $m->receber(whatsappEvento($this->evento('Bom dia, sou a profissional', 'PROF', true), 'teste'));
        $this->assertSame('humano', $m->conversa($id)->modo);
    }

    public function testFilaIdempotenteEConsentimentoDeLembretesExigeClienteValida(): void
    {
        $m = $this->modelo();
        $id = $m->receber(whatsappEvento($this->evento(), 'teste'));
        $chave = hash('sha256', 'manual');
        $this->assertSame($m->fila($id, 'Olá', 'manual', $chave), $m->fila($id, 'Olá', 'manual', $chave));
        $m->ajustar($id, 'humano', 1, true);
        $this->assertSame(1, (int) $m->conversa($id)->cliente_id);
        $this->assertNotEmpty($m->conversa($id)->consentimento_em);
        $this->expectException(DomainException::class);
        $m->ajustar($id, 'humano', 999, true);
    }

    public function testConfiguracaoValidaHorariosLimitesSemAtivarPorPadrao(): void
    {
        $m = $this->modelo();
        $this->assertFalse($m->configuracao()['automatico']);
        $m->salvar(automacoesPadrao() + ['desconhecido' => 'não guardar'], 1);
        $this->assertArrayNotHasKey('desconhecido', $m->configuracao());
        [, $erros] = automacoesDados(['inicio_envio' => '19:00', 'fim_envio' => '08:00', 'limite_diario' => '999', 'ia' => '1']);
        $this->assertArrayHasKey('fim_envio', $erros);
        $this->assertArrayHasKey('limite_diario', $erros);
        $this->assertArrayHasKey('modelo_ia', $erros);
        $this->expectException(DomainException::class);
        $m->salvar(automacoesPadrao(), 1);
    }

    public function testPrivacidadeTransporteTextoEMetadados(): void
    {
        $this->assertTrue(automacoesHumano('Meu bebê está com febre'));
        $this->assertFalse(automacoesHumano('Quais são os serviços?'));
        $this->assertSame('Contato [email] ou [número]', automacoesAnonimizar('Contato teste@example.com ou (41) 99999-1234'));
        $http = new class extends Automacoes_http {
            public array $pedido = [];
            public function json(string $url, array $headers, array $dados): array
            {
                $this->pedido = compact('url', 'dados');
                return ['key' => ['id' => 'ID-DEMO']];
            }
        };
        $id = $http->enviar((object) ['url' => 'https://evolution.example.com', 'instancia' => 'demo'], 'ficticia', '5541999991234', 'Olá');
        $this->assertSame('ID-DEMO', $id);
        $this->assertSame('https://evolution.example.com/message/sendText/demo', $http->pedido['url']);
        $this->assertSame(['number' => '5541999991234', 'text' => 'Olá', 'linkPreview' => false], $http->pedido['dados']);
        $this->expectException(RuntimeException::class);
        $http->enviar((object) ['url' => 'http://example.com', 'instancia' => 'demo'], 'x', '5541999991234', 'Olá');
    }

    public function testConsultaNaoPodeResponderNemAlterarConversa(): void
    {
        $controller = new class extends Conversas {
            public function __construct()
            {
            }
            protected function hasAnyPermission(array $p): bool
            {
                return in_array('vWhatsapp', $p, true);
            }
        };
        foreach (['responder', 'ajustar', 'cancelar'] as $metodo) {
            try {
                $controller->{$metodo}(1);
                $this->fail('Consulta não pode alterar.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Sem permissão', $e->getMessage());
            }
        }
    }

    public function testBackupRecusaDestinoPublicoERotinaHTTP(): void
    {
        $backup = new Backup_operacao();
        try {
            $backup->destino(FCPATH, FCPATH);
            $this->fail('Backup público deve ser recusado.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('raiz web', $e->getMessage());
        }
        $controller = new class extends Rotinas {
            public $input;
            public $output;
            public function __construct()
            {
            }
        };
        $controller->input = new class {
            public function is_cli_request(): bool
            {
                return false;
            }
        };
        $controller->output = new class {
            public int $status = 200;
            public function set_status_header($status)
            {
                $this->status = $status;
                return $this;
            }
            public function set_output($texto)
            {
                return $this;
            }
        };
        $controller->processar();
        $this->assertSame(404, $controller->output->status);
    }

    public function testWorkerEnviaUmaVezEEntregaIncertaNaoReenvia(): void
    {
        $m = $this->modelo();
        $this->db->query('CREATE TABLE os (idOs INTEGER PRIMARY KEY, clientes_id INTEGER, status TEXT)');
        $this->db->query('CREATE TABLE agenda (id INTEGER PRIMARY KEY, os_id INTEGER, inicio TEXT, fim TEXT, cancelado INTEGER)');
        $this->db->query('CREATE TABLE lancamentos (idLancamentos INTEGER PRIMARY KEY, atendimento_id INTEGER, valor NUMERIC, data_vencimento TEXT, recebimento_tipo TEXT, baixado INTEGER)');
        $this->db->query('CREATE TABLE fichas (id INTEGER PRIMARY KEY, os_id INTEGER, clientes_id INTEGER)');
        $this->db->query('CREATE TABLE ficha_evolucoes (id INTEGER PRIMARY KEY, ficha_id INTEGER, retorno TEXT, retorno_status TEXT)');
        $id = $m->receber(whatsappEvento($this->evento(), 'teste'));
        $config = automacoesPadrao();
        $config['inicio_envio'] = '00:00';
        $config['fim_envio'] = '23:59';
        $m->salvar($config, 1);
        $m->fila($id, 'Primeira', 'manual', hash('sha256', 'primeira'));
        $m->fila($id, 'Segunda', 'manual', hash('sha256', 'segunda'));
        $lembretes = $this->makeInstance(Lembretes_model::class);
        $lembretes->db = $this->db;
        $http = new class extends Automacoes_http {
            public int $chamadas = 0;
            public function enviar(object $c, string $chave, string $numero, string $texto): string
            {
                $this->chamadas++;
                if ($texto === 'Segunda') {
                    throw new RuntimeException('Timeout simulado');
                }
                return 'ENVIO1';
            }
        };
        $worker = new Automacoes_worker();
        $worker->ci = (object) ['db' => $this->db, 'automacoes_model' => $m, 'lembretes_model' => $lembretes, 'automacoes_http' => $http,
            'load' => new class {
                public function helper($h)
                {
                } public function model($m)
                {
                } public function library($l)
                {
                }
            },
            'encryption' => new class {
                public function decrypt($v)
                {
                    return 'ficticia';
                }
            },
            'evolution_model' => new class {
                public function configuracao()
                {
                    return (object) ['chave_cifrada' => 'ficticia'];
                }
            }];
        $anterior = $_ENV['STORAGE_PATH'] ?? null;
        $pasta = sys_get_temp_dir() . '/mapos-worker-' . bin2hex(random_bytes(6));
        $_ENV['STORAGE_PATH'] = $pasta;
        try {
            $this->assertSame(['enviados' => 1, 'incertos' => 1, 'cancelados' => 0], $worker->executar());
            $worker->executar();
            $this->assertSame(2, $http->chamadas);
            $this->assertSame(1, $this->db->where('estado', 'incerto')->count_all_results('wa_mensagens'));
            $this->assertNotEmpty($m->estado()->ultimo_worker);
        } finally {
            if ($anterior === null) {
                unset($_ENV['STORAGE_PATH']);
            } else {
                $_ENV['STORAGE_PATH'] = $anterior;
            }
            foreach (glob($pasta . '/automacoes/*') ?: [] as $arquivo) {
                unlink($arquivo);
            }
            rmdir($pasta . '/automacoes');
            unlink($pasta . '/.htaccess');
            if (is_file($pasta . '/index.html')) {
                unlink($pasta . '/index.html');
            }
            if (is_file($pasta . '/web.config')) {
                unlink($pasta . '/web.config');
            }
            rmdir($pasta);
        }
    }

    public function testLembretesCancelamAposRemarcacaoQuitacaoTrocaDeCliente(): void
    {
        $this->db->query('CREATE TABLE os (idOs INTEGER PRIMARY KEY, clientes_id INTEGER, status TEXT)');
        $this->db->query('CREATE TABLE agenda (id INTEGER PRIMARY KEY, os_id INTEGER, inicio TEXT, fim TEXT, cancelado INTEGER)');
        $this->db->query('CREATE TABLE lancamentos (idLancamentos INTEGER PRIMARY KEY, atendimento_id INTEGER, valor NUMERIC, data_vencimento TEXT, recebimento_tipo TEXT, baixado INTEGER)');
        $this->db->query('CREATE TABLE fichas (id INTEGER PRIMARY KEY, os_id INTEGER, clientes_id INTEGER)');
        $this->db->query('CREATE TABLE ficha_evolucoes (id INTEGER PRIMARY KEY, ficha_id INTEGER, retorno TEXT, retorno_status TEXT)');
        $this->db->insert('os', ['idOs' => 1, 'clientes_id' => 1, 'status' => 'Aprovado']);
        $this->db->insert('agenda', ['id' => 1, 'os_id' => 1, 'inicio' => '2030-01-02 09:00:00', 'fim' => '2030-01-02 10:00:00', 'cancelado' => 0]);
        $this->db->insert('lancamentos', ['idLancamentos' => 1, 'atendimento_id' => 1, 'valor' => 100, 'data_vencimento' => '2030-01-01', 'recebimento_tipo' => 'saldo', 'baixado' => 0]);
        $m = $this->makeInstance(Lembretes_model::class);
        $m->db = $this->db;
        $candidatos = $m->candidatos('2030-01-01 10:00:00');
        $this->assertCount(2, $candidatos);
        $conversa = (object) ['cliente_id' => 1, 'lembretes' => 1, 'optout' => 0];
        $mensagem = (object) ['tipo' => 'lembrete', 'contexto_id' => 1, 'contexto_versao' => $candidatos[0]['versao']];
        $this->assertTrue($m->valido($mensagem, $conversa, '2030-01-01 10:00:00'));
        $conversa->cliente_id = 2;
        $this->assertFalse($m->valido($mensagem, $conversa, '2030-01-01 10:00:00'));
        $conversa->cliente_id = 1;
        $this->db->where('id', 1)->update('agenda', ['inicio' => '2030-01-02 08:00:00']);
        $this->assertFalse($m->valido($mensagem, $conversa, '2030-01-01 10:00:00'));
        $mensagem = (object) ['tipo' => 'cobranca', 'contexto_id' => 1, 'contexto_versao' => $candidatos[1]['versao']];
        $this->assertTrue($m->valido($mensagem, $conversa, '2030-01-01 10:00:00'));
        $this->db->where('idLancamentos', 1)->update('lancamentos', ['baixado' => 1]);
        $this->assertFalse($m->valido($mensagem, $conversa, '2030-01-01 10:00:00'));
    }
}
