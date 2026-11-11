<?php

require_once APPPATH . 'helpers/pacotes_helper.php';
require_once APPPATH . 'helpers/agenda_helper.php';
require_once APPPATH . 'models/Pacotes_model.php';
require_once APPPATH . 'models/Agenda_model.php';

final class PacotesTest extends MaposTestCase
{
    private function banco(): Pacotes_model
    {
        $this->db->query('CREATE TABLE clientes (idClientes INTEGER PRIMARY KEY, nomeCliente TEXT)');
        $this->db->query("INSERT INTO clientes VALUES (1, 'Ana'), (2, 'Maria')");
        $this->db->query('CREATE TABLE dependentes (id INTEGER PRIMARY KEY, clientes_id INTEGER, nome TEXT)');
        $this->db->query("INSERT INTO dependentes VALUES (1, 1, 'Helena'), (2, 2, 'Pedro')");
        $this->db->query('CREATE TABLE usuarios (idUsuarios INTEGER PRIMARY KEY, situacao INTEGER)');
        $this->db->query('INSERT INTO usuarios VALUES (1, 1), (2, 1)');
        $this->db->query('CREATE TABLE os (idOs INTEGER PRIMARY KEY AUTOINCREMENT, clientes_id INTEGER, dependente_id INTEGER, usuarios_id INTEGER, status TEXT, dataInicial TEXT, dataFinal TEXT, faturado INTEGER, descricaoProduto TEXT, valorTotal NUMERIC)');
        $this->db->query('CREATE TABLE pacotes (id INTEGER PRIMARY KEY AUTOINCREMENT, os_id INTEGER UNIQUE, nome TEXT, chave TEXT UNIQUE, criado_em TEXT)');
        $this->db->query('CREATE TABLE servicos_os (idServicos_os INTEGER PRIMARY KEY AUTOINCREMENT, os_id INTEGER, servicos_id INTEGER, servico TEXT, quantidade INTEGER, preco NUMERIC, subTotal NUMERIC)');
        $this->db->query('CREATE TABLE pacote_sessoes (id INTEGER PRIMARY KEY AUTOINCREMENT, pacote_id INTEGER, servicos_id INTEGER, nome_servico TEXT, duracao_min INTEGER, numero INTEGER, agenda_id INTEGER, realizado_em TEXT)');
        $this->db->query('CREATE TABLE agenda (id INTEGER PRIMARY KEY AUTOINCREMENT, os_id INTEGER, usuarios_id INTEGER, pacote_sessao_id INTEGER, tipo TEXT, titulo TEXT, inicio TEXT, fim TEXT, ocupado_inicio TEXT, ocupado_fim TEXT, servicos_json TEXT, cancelado INTEGER DEFAULT 0)');
        $model = $this->makeInstance(Pacotes_model::class);
        $this->setPrivateProperty($model, 'db', $this->db);
        return $model;
    }

    private function composicao(): array
    {
        [$dados, $erros] = pacoteDoFormulario(['nome' => 'Pós-parto', 'servicos' => [3 => ['quantidade' => '3', 'preco' => '150,50'], 4 => ['quantidade' => '1', 'preco' => '80,00']]], [
            (object) ['idServicos' => 3, 'nome' => 'Consultoria', 'duracao_min' => 60],
            (object) ['idServicos' => 4, 'nome' => 'Laser', 'duracao_min' => 30],
        ]);
        $this->assertSame([], $erros);
        return $dados;
    }

    private function agenda(): Agenda_model
    {
        $agenda = $this->makeInstance(Agenda_model::class);
        $this->setPrivateProperty($agenda, 'db', $this->db);
        return $agenda;
    }

    private function reserva(int $sessao, int $os, int $profissional = 1, string $inicio = '2020-01-01T10:00'): array
    {
        return agendaPeriodo($inicio, 60, 0, 0) + ['pacote_sessao_id' => $sessao, 'os_id' => $os, 'usuarios_id' => $profissional,
            'tipo' => 'atendimento', 'titulo' => 'Sessão', 'servicos_json' => json_encode([['id' => 3]])];
    }

    public function testCriaUmContratoESessoesComPrecoNegociadoSemDuplicarReenvio(): void
    {
        $model = $this->banco();
        $id = $model->criar($this->composicao(), 1, 1, 1, str_repeat('a', 32));
        $this->assertSame($id, $model->criar($this->composicao(), 1, 1, 1, str_repeat('a', 32)));
        $this->assertCount(4, $model->sessoes($id));
        $this->assertSame(1, $this->db->count_all_results('os'));
        $this->assertSame(531.5, (float) $this->db->select_sum('subTotal')->get('servicos_os')->row()->subTotal);
        $this->assertSame('Helena', $model->getById($id)->beneficiario);
        $this->assertSame(60, (int) $model->sessoes($id)[0]->duracao_min);
    }

    public function testRecusaBebeDeOutraResponsavelESemRegistrosParciais(): void
    {
        $model = $this->banco();
        try {
            $model->criar($this->composicao(), 1, 2, 1, str_repeat('a', 32));
            $this->fail('Deveria recusar o bebê de outra cliente.');
        } catch (DomainException $e) {
            $this->assertSame(0, $this->db->count_all_results('os'));
        }
    }

    public function testCancelarEReagendarNaoConsomeNemDuplicaSessao(): void
    {
        $model = $this->banco();
        $id = $model->criar($this->composicao(), 1, null, 1, str_repeat('a', 32));
        $os = (int) $model->getById($id)->os_id;
        $sessao = (int) $model->sessoes($id)[0]->id;
        $agenda = $this->agenda();
        $reserva = $agenda->reservar($this->reserva($sessao, $os), null);
        $this->assertSame(1, pacoteProgresso($model->sessoes($id))['agendadas']);
        $agenda->cancelar($reserva);
        $this->assertSame(0, pacoteProgresso($model->sessoes($id))['agendadas']);
        $nova = $agenda->reservar($this->reserva($sessao, $os, 2), null);
        $this->assertNotSame($reserva, $nova);
        $this->assertSame(4, pacoteProgresso($model->sessoes($id))['restantes']);
        $this->assertSame($nova, (int) $model->sessoes($id)[0]->agenda_id);
    }

    public function testNaoAgendaMesmaSessaoDuasVezesMesmoComOutraProfissional(): void
    {
        $model = $this->banco();
        $id = $model->criar($this->composicao(), 1, null, 1, str_repeat('a', 32));
        $os = (int) $model->getById($id)->os_id;
        $sessao = (int) $model->sessoes($id)[0]->id;
        $agenda = $this->agenda();
        $agenda->reservar($this->reserva($sessao, $os), null);
        $this->expectException(DomainException::class);
        $agenda->reservar($this->reserva($sessao, $os, 2), null);
    }

    public function testSessaoRealizadaConsomeUmaVezEPodeSerReaberta(): void
    {
        $model = $this->banco();
        $id = $model->criar($this->composicao(), 1, null, 1, str_repeat('a', 32));
        $sessao = (int) $model->sessoes($id)[0]->id;
        $agenda = $this->agenda();
        $reserva = $agenda->reservar($this->reserva($sessao, (int) $model->getById($id)->os_id), null);
        $model->concluirSessao($id, $sessao, true);
        $model->concluirSessao($id, $sessao, true);
        $this->assertSame(1, pacoteProgresso($model->sessoes($id))['realizadas']);
        $this->assertSame(3, pacoteProgresso($model->sessoes($id))['restantes']);
        try {
            $agenda->cancelar($reserva);
            $this->fail('Sessão realizada precisa ser reaberta antes do cancelamento.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Reabra', $e->getMessage());
        }
        $model->concluirSessao($id, $sessao, false);
        $this->assertSame(4, pacoteProgresso($model->sessoes($id))['restantes']);
        $this->assertSame(531.5, (float) $this->db->select_sum('subTotal')->get('servicos_os')->row()->subTotal);
    }

    public function testNaoRealizaSessaoDeOutroPacote(): void
    {
        $model = $this->banco();
        $id = $model->criar($this->composicao(), 1, null, 1, str_repeat('a', 32));
        $outro = $model->criar($this->composicao(), 1, null, 1, str_repeat('b', 32));
        $this->expectException(DomainException::class);
        $model->concluirSessao($outro, (int) $model->sessoes($id)[0]->id, true);
    }

    public function testNaoAgendaSessaoEmOutroAtendimento(): void
    {
        $model = $this->banco();
        $id = $model->criar($this->composicao(), 1, null, 1, str_repeat('a', 32));
        $this->expectException(DomainException::class);
        $this->agenda()->reservar($this->reserva((int) $model->sessoes($id)[0]->id, 999), null);
    }

    public function testNaoRealizaSessaoSemReservaOuAntesDoFim(): void
    {
        $model = $this->banco();
        $id = $model->criar($this->composicao(), 1, null, 1, str_repeat('a', 32));
        $sessao = (int) $model->sessoes($id)[0]->id;
        try {
            $model->concluirSessao($id, $sessao, true);
            $this->fail('Deve exigir reserva.');
        } catch (DomainException $e) {
            $this->assertSame(0, pacoteProgresso($model->sessoes($id))['realizadas']);
        }
        $this->agenda()->reservar($this->reserva($sessao, (int) $model->getById($id)->os_id, 1, '2099-01-01T10:00'), null);
        $this->expectException(DomainException::class);
        $model->concluirSessao($id, $sessao, true);
    }

    public function testValidaQuantidadesPrecoEDuracaoNoServidor(): void
    {
        foreach ([['quantidade' => '-1', 'preco' => '50'], ['quantidade' => '1.5', 'preco' => '50'], ['quantidade' => '101', 'preco' => '50'], ['quantidade' => '1', 'preco' => '0']] as $entrada) {
            [, $erros] = pacoteDoFormulario(['nome' => 'Pacote', 'servicos' => [3 => $entrada]], [(object) ['idServicos' => 3, 'nome' => 'Laser', 'duracao_min' => 30]]);
            $this->assertNotEmpty($erros);
        }
        [, $erros] = pacoteDoFormulario(['nome' => '', 'servicos' => [3 => ['quantidade' => '1', 'preco' => '50']]], [(object) ['idServicos' => 3, 'nome' => 'Taxa', 'duracao_min' => 0]]);
        $this->assertArrayHasKey('nome', $erros);
        $this->assertArrayHasKey('_geral', $erros);
    }
}
