<?php

require_once APPPATH . 'helpers/fichas_helper.php';
require_once APPPATH . 'models/Fichas_model.php';
require_once APPPATH . 'models/Os_model.php';
require_once APPPATH . 'core/MY_Controller.php';
require_once APPPATH . 'controllers/Fichas.php';

final class FichasTest extends MaposTestCase
{
    private function modelo(): Fichas_model
    {
        $this->db->query('CREATE TABLE ficha_modelos (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, campos_json TEXT, termo TEXT, versao INTEGER DEFAULT 1)');
        $this->db->query('CREATE TABLE fichas (id INTEGER PRIMARY KEY AUTOINCREMENT, os_id INTEGER, clientes_id INTEGER, dependente_id INTEGER, servicos_id INTEGER, paciente_nome TEXT, responsavel_nome TEXT, servico_nome TEXT, modelo_nome TEXT, campos_json TEXT, respostas_json TEXT, termo TEXT, usuarios_id INTEGER, criado_em TEXT, chave TEXT UNIQUE)');
        $this->db->query('CREATE TABLE ficha_evolucoes (id INTEGER PRIMARY KEY AUTOINCREMENT, ficha_id INTEGER, agenda_id INTEGER, ocorrido_em TEXT, avaliacao TEXT, procedimento TEXT, orientacoes TEXT, observacoes TEXT, retorno TEXT, retorno_status TEXT, retorno_historico TEXT, usuarios_id INTEGER, criado_em TEXT, chave TEXT UNIQUE)');
        $this->db->query('CREATE TABLE ficha_arquivos (id INTEGER PRIMARY KEY AUTOINCREMENT, ficha_id INTEGER, tipo TEXT, descricao TEXT, signatario TEXT, assinado_em TEXT, nome TEXT, caminho TEXT, usuarios_id INTEGER, criado_em TEXT)');
        $this->db->query('CREATE TABLE os (idOs INTEGER PRIMARY KEY, clientes_id INTEGER, dependente_id INTEGER, status TEXT, faturado INTEGER)');
        $this->db->query("INSERT INTO os VALUES (1, 1, 1, 'Finalizado', 1), (2, 1, NULL, 'Aberto', 0), (3, 1, 2, 'Aberto', 0), (4, 2, NULL, 'Aberto', 0)");
        $this->db->query('CREATE TABLE clientes (idClientes INTEGER PRIMARY KEY, nomeCliente TEXT)');
        $this->db->query("INSERT INTO clientes VALUES (1, 'Ana'), (2, 'Maria')");
        $this->db->query('CREATE TABLE dependentes (id INTEGER PRIMARY KEY, clientes_id INTEGER, nome TEXT)');
        $this->db->query("INSERT INTO dependentes VALUES (1, 1, 'Helena'), (2, 1, 'Pedro')");
        $this->db->query('CREATE TABLE usuarios (idUsuarios INTEGER PRIMARY KEY, nome TEXT)');
        $this->db->query("INSERT INTO usuarios VALUES (1, 'Profissional')");
        $this->db->query('CREATE TABLE servicos_os (os_id INTEGER, servicos_id INTEGER, servico TEXT)');
        $this->db->query("INSERT INTO servicos_os VALUES (1, 5, 'Consultoria'), (2, 5, 'Consultoria'), (3, 5, 'Consultoria'), (4, 5, 'Consultoria')");
        $this->db->query('CREATE TABLE agenda (id INTEGER PRIMARY KEY, os_id INTEGER, inicio TEXT, fim TEXT, cancelado INTEGER)');
        $this->db->query("INSERT INTO agenda VALUES (1, 1, '2020-01-01 10:00:00', '2020-01-01 11:00:00', 0), (2, 2, '2020-01-01 10:00:00', '2020-01-01 11:00:00', 0), (3, 1, '2099-01-01 10:00:00', '2099-01-01 11:00:00', 0), (4, 1, '2020-01-01 10:00:00', '2020-01-01 11:00:00', 1)");
        $this->db->insert('ficha_modelos', ['id' => 1, 'nome' => 'Amamentação', 'campos_json' => '["Motivo","Histórico"]', 'termo' => 'Texto aprovado pela profissional.']);
        $modelo = $this->makeInstance(Fichas_model::class);
        $this->setPrivateProperty($modelo, 'db', $this->db);
        return $modelo;
    }

    private function criar(Fichas_model $modelo, int $os = 1, string $chave = 'a'): int
    {
        $registro = $this->db->where('idOs', $os)->get('os')->row();
        return $modelo->criar($os, 5, 1, 1, ['Resposta inicial', 'Histórico informado'], 1, str_repeat($chave, 32), (int) $registro->clientes_id, $registro->dependente_id ? (int) $registro->dependente_id : null);
    }

    private function evolucao(array $extras = []): array
    {
        [$dados, $erros] = fichaEvolucaoDados(array_replace(['ocorrido_em' => '2020-01-01T10:00', 'avaliacao' => 'Registro fictício', 'retorno' => date('Y-m-d'), 'agenda_id' => '1'], $extras), date('Y-m-d H:i:s'));
        $this->assertSame([], $erros);
        return $dados;
    }

    public function testFichaEmAtendimentoPagoPreservaModeloRespostasEReenvio(): void
    {
        $modelo = $this->modelo();
        $id = $this->criar($modelo);
        $this->assertSame($id, $this->criar($modelo));
        $this->assertSame(1, $this->db->count_all('fichas'));
        $this->assertSame('Helena', $modelo->ficha($id)->paciente_nome);
        $this->assertSame('Ana', $modelo->ficha($id)->responsavel_nome);
        $modelo->salvarModelo(1, ['nome' => 'Modelo alterado', 'campos_json' => '["Outra pergunta"]', 'termo' => 'Novo texto'], 1);
        $this->assertSame('Amamentação', $modelo->ficha($id)->modelo_nome);
        $this->assertSame('Texto aprovado pela profissional.', $modelo->ficha($id)->termo);
        $this->assertSame(['Resposta inicial', 'Histórico informado'], json_decode($modelo->ficha($id)->respostas_json, true));
        $this->assertSame(1, (int) $this->db->where('idOs', 1)->get('os')->row()->faturado);
        $this->expectException(DomainException::class);
        $this->criar($modelo, 2, 'b');
    }

    public function testHistoricoNaoMisturaResponsavelIrmaosOuOutraCliente(): void
    {
        $modelo = $this->modelo();
        foreach ([1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd'] as $os => $chave) {
            $this->criar($modelo, $os, $chave);
        }
        $this->assertCount(1, $modelo->historico(1, 1));
        $this->assertSame('Helena', $modelo->historico(1, 1)[0]->paciente_nome);
        $this->assertSame('Ana', $modelo->historico(1, null)[0]->paciente_nome);
        $this->assertSame('Pedro', $modelo->historico(1, 2)[0]->paciente_nome);
        $this->assertSame([], $modelo->historico(2, 1));
        $this->assertCount(1, $modelo->doAtendimento(1));
    }

    public function testServicoPrecisaPertencerAoAtendimento(): void
    {
        $modelo = $this->modelo();
        try {
            $modelo->criar(1, 999, 1, 1, ['Resposta'], 1, str_repeat('a', 32), 1, 1);
            $this->fail('Serviço de outro atendimento.');
        } catch (DomainException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertSame(0, $this->db->count_all('fichas'));
    }

    public function testRespostasDeFormularioAntigoNaoVaoParaOutraPessoa(): void
    {
        $modelo = $this->modelo();
        $this->db->where('idOs', 1)->update('os', ['dependente_id' => 2]);
        try {
            $modelo->criar(1, 5, 1, 1, ['Resposta para Helena'], 1, str_repeat('a', 32), 1, 1);
            $this->fail('Pessoa mudou durante o preenchimento.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('pessoa atendida mudou', $e->getMessage());
        }
        $this->assertSame(0, $this->db->count_all('fichas'));
    }

    public function testEvolucaoVinculaSessaoCorretaEReenvioNaoDuplica(): void
    {
        $modelo = $this->modelo();
        $ficha = $this->criar($modelo);
        $id = $modelo->evoluir($ficha, $this->evolucao(), 1, str_repeat('b', 32));
        $this->assertSame($id, $modelo->evoluir($ficha, $this->evolucao(), 1, str_repeat('b', 32)));
        $this->assertCount(1, $modelo->evolucoes($ficha));
        $this->assertSame('Profissional', $modelo->evolucoes($ficha)[0]->autor);
        foreach (['2', '3', '4'] as $agenda) {
            try {
                $modelo->evoluir($ficha, $this->evolucao(['agenda_id' => $agenda]), 1, str_repeat('c', 32));
                $this->fail('Sessão inválida.');
            } catch (DomainException $e) {
                $this->assertStringContainsString('sessão', $e->getMessage());
            }
        }
        $this->assertCount(1, $modelo->evolucoes($ficha));
    }

    public function testRetornoPodeSerConcluidoEReabertoComHistorico(): void
    {
        $modelo = $this->modelo();
        $ficha = $this->criar($modelo);
        $id = $modelo->evoluir($ficha, $this->evolucao(), 1, str_repeat('b', 32));
        $this->assertSame(1, $modelo->contarRetornos('pendente', date('Y-m-d')));
        $modelo->retorno($ficha, $id, 'concluido', 'Acompanhamento registrado.', 1);
        $modelo->retorno($ficha, $id, 'concluido', 'Reenvio.', 1);
        $this->assertSame(0, $modelo->contarRetornos('pendente', date('Y-m-d')));
        $this->assertCount(1, $modelo->retornos('concluido', date('Y-m-d'), 0, 20));
        $modelo->retorno($ficha, $id, 'pendente', 'Reaberto para novo contato.', 1);
        $registro = $modelo->evolucoes($ficha)[0];
        $this->assertCount(2, json_decode($registro->retorno_historico, true));
        $this->assertSame('Registro fictício', $registro->avaliacao);
        $this->expectException(DomainException::class);
        $modelo->retorno(999, $id, 'concluido', 'Tentativa em outra ficha.', 1);
    }

    public function testArquivoPrecisaPertencerAFichaECaminhoNaoSaiNaListagem(): void
    {
        $modelo = $this->modelo();
        $ficha = $this->criar($modelo);
        $this->assertTrue($modelo->anexar($ficha, ['tipo' => 'documento', 'descricao' => 'Documento fictício', 'nome' => 'arquivo.pdf', 'caminho' => 'fichas/1/privado.pdf', 'usuarios_id' => 1, 'criado_em' => date('Y-m-d H:i:s')]));
        $id = (int) $modelo->arquivos($ficha)[0]->id;
        $this->assertObjectNotHasProperty('caminho', $modelo->arquivos($ficha)[0]);
        $this->assertNotNull($modelo->arquivo($ficha, $id));
        $this->assertNull($modelo->arquivo(999, $id));
        $this->assertFalse($modelo->anexar(999, []));
    }

    public function testNaoTrocaPacienteDepoisDaFichaMasPermiteAtualizarStatus(): void
    {
        $modelo = $this->modelo();
        $this->criar($modelo);
        $os = $this->makeInstance(Os_model::class);
        $this->setPrivateProperty($os, 'db', $this->db);
        $this->assertFalse($os->edit('os', ['clientes_id' => 2], 'idOs', 1));
        $this->assertFalse($os->edit('os', ['clientes_id' => null], 'idOs', 1));
        $this->assertFalse($os->edit('os', ['dependente_id' => null], 'idOs', 1));
        $this->assertFalse($os->edit('os', ['dependente_id' => 2], 'idOs', 1));
        $this->assertTrue($os->edit('os', ['status' => 'Finalizado'], 'idOs', 1));
        $this->assertTrue($os->pessoaPermitida(1, ['clientes_id' => 1, 'dependente_id' => 1]));
        $this->assertTrue($os->pessoaPermitida(2, ['clientes_id' => 2]));
        $this->assertSame(1, (int) $this->db->where('idOs', 1)->get('os')->row()->clientes_id);
    }

    public function testValidacoesDeModelosRespostasDatasETermos(): void
    {
        [, $erros] = fichaModeloDados(['nome' => 'Modelo', 'campos' => "Pergunta\nPergunta", 'termo' => []]);
        $this->assertArrayHasKey('campos', $erros);
        $this->assertArrayHasKey('termo', $erros);
        [, $erros] = fichaRespostas(['respostas' => [str_repeat('a', 2001)]], ['Pergunta']);
        $this->assertNotEmpty($erros);
        [, $erros] = fichaEvolucaoDados(['ocorrido_em' => '2099-01-01T10:00', 'retorno' => '2026-02-30', 'agenda_id' => []], '2026-11-12 10:00:00');
        foreach (['_geral', 'ocorrido_em', 'retorno', 'agenda_id'] as $campo) {
            $this->assertArrayHasKey($campo, $erros);
        }
        $this->assertNull(fichaData("2026-01-01\0"));
        [, $erros] = fichaArquivoDados(['tipo' => 'termo_assinado', 'descricao' => 'Termo', 'signatario' => '', 'assinado_em' => '2099-01-01'], '2026-11-12');
        $this->assertArrayHasKey('signatario', $erros);
        $this->assertArrayHasKey('assinado_em', $erros);
        [$dados, $erros] = fichaArquivoDados(['tipo' => 'termo_assinado', 'descricao' => 'Termo', 'signatario' => 'Ana', 'assinado_em' => '2026-11-12'], '2026-11-12');
        $this->assertSame([], $erros);
        $this->assertSame('Ana', $dados['signatario']);
    }

    public function testRotasNegamSemPermissaoAdministrativaMesmoComIdConhecido(): void
    {
        foreach (['index', 'atendimento', 'modelo', 'criar', 'visualizar', 'evolucao', 'retorno', 'anexar', 'arquivo', 'termo'] as $metodo) {
            $controller = new class extends Fichas {
                public function __construct()
                {
                }
                protected function hasAnyPermission(array $permissoes): bool
                {
                    return false;
                }
            };
            try {
                $controller->{$metodo}();
                $this->fail('Deveria negar o acesso.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Sem permissão', $e->getMessage());
            }
        }
    }

    public function testMutacoesRecusamGetAntesDeConsultarDados(): void
    {
        foreach (['evolucao', 'retorno', 'anexar'] as $metodo) {
            $controller = new class extends Fichas {
                public $input;
                public function __construct()
                {
                    $this->input = new class {
                        public function method()
                        {
                            return 'get';
                        }
                    };
                }
                protected function hasAnyPermission(array $permissoes): bool
                {
                    return true;
                }
            };
            try {
                $controller->{$metodo}(1);
                $this->fail('GET deveria ser recusado.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Use o formulário', $e->getMessage());
            }
        }
    }
}
