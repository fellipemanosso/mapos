<?php

require_once APPPATH . 'helpers/insumos_helper.php';
require_once APPPATH . 'helpers/financeiro_helper.php';
require_once APPPATH . 'helpers/status_helper.php';
require_once APPPATH . 'helpers/fichas_helper.php';
require_once APPPATH . 'helpers/usuarios_helper.php';
require_once APPPATH . 'helpers/deslocamentos_helper.php';
require_once APPPATH . 'helpers/exportacoes_helper.php';
require_once APPPATH . 'models/Insumos_model.php';
require_once APPPATH . 'models/Pacotes_model.php';
require_once APPPATH . 'models/Indicadores_model.php';
require_once APPPATH . 'core/MY_Controller.php';
require_once APPPATH . 'controllers/Fichas.php';
require_once APPPATH . 'controllers/Exportacoes.php';

final class OperacaoTest extends MaposTestCase
{
    private function estoque(): Insumos_model
    {
        $this->db->query('CREATE TABLE insumos (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, unidade TEXT, minimo NUMERIC)');
        $this->db->query('CREATE TABLE insumo_lotes (id INTEGER PRIMARY KEY AUTOINCREMENT, insumo_id INTEGER, lote TEXT, validade TEXT, saldo NUMERIC, custo_unitario NUMERIC, criado_em TEXT)');
        $this->db->query('CREATE TABLE insumo_movimentos (id INTEGER PRIMARY KEY AUTOINCREMENT, lote_id INTEGER, os_id INTEGER, tipo TEXT, quantidade NUMERIC, custo NUMERIC, nota TEXT, usuarios_id INTEGER, criado_em TEXT, estornado_em TEXT, estornado_por INTEGER, chave TEXT UNIQUE)');
        $this->db->query('CREATE TABLE os (idOs INTEGER PRIMARY KEY, status TEXT)');
        $this->db->query("INSERT INTO os VALUES (1, 'Aprovado'), (2, 'Cancelado')");
        $modelo = $this->makeInstance(Insumos_model::class);
        $this->setPrivateProperty($modelo, 'db', $this->db);
        $modelo->cadastrar(['nome' => 'Luvas', 'unidade' => 'un', 'minimo' => '5.000']);
        $modelo->cadastrar(['nome' => 'Gaze', 'unidade' => 'un', 'minimo' => '2.000']);
        return $modelo;
    }

    private function entrada(Insumos_model $m, string $validade = '2099-01-01'): int
    {
        return $m->movimentar(1, 'entrada', 10000, ['nota' => 'Compra fictícia', 'lote' => 'L-001', 'validade' => $validade, 'custo' => '20,00'], 1, str_repeat('a', 32));
    }

    public function testQuantidadesFracionadasSemArredondamentoSilencioso(): void
    {
        $this->assertSame(1250, insumoQuantidade('1,250'));
        $this->assertSame('1.250', insumoDecimal(1250));
        foreach (['-1', '1,0000', '1.000,00', [], '1000000', '1e3'] as $invalido) {
            $this->assertNull(insumoQuantidade($invalido));
        }
        [, $erros] = insumoDados(['nome' => '', 'unidade' => 'litro', 'minimo' => '-1']);
        $this->assertCount(3, $erros);
    }

    public function testCompraConsumoEstornoComHistoricoSemDuplicarReenvio(): void
    {
        $m = $this->estoque();
        $entrada = $this->entrada($m);
        $this->assertSame($entrada, $this->entrada($m));
        $saida = $m->movimentar(1, 'consumo', 2500, ['nota' => 'Atendimento', 'lote_id' => '1', 'os_id' => '1'], 1, str_repeat('b', 32));
        $this->assertSame($saida, $m->movimentar(1, 'consumo', 2500, ['nota' => 'Reenvio', 'lote_id' => '1', 'os_id' => '1'], 1, str_repeat('b', 32)));
        $this->assertSame(7.5, (float) $m->lotes(1)[0]->saldo);
        $this->assertSame(5.0, (float) $m->movimentos(1, 0, 30)[0]->custo);
        try {
            $m->estornar(1, $entrada, 1);
            $this->fail('Entrada com saída ativa não pode ser estornada.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('saídas', $e->getMessage());
        }
        $m->estornar(1, $saida, 1);
        $m->estornar(1, $saida, 1);
        $this->assertSame(10.0, (float) $m->lotes(1)[0]->saldo);
        $m->estornar(1, $entrada, 1);
        $this->assertSame(0.0, (float) $m->lotes(1)[0]->saldo);
        $this->assertSame(2, $m->contarMovimentos(1));
        $this->assertTrue($m->editar(1, ['nome' => 'Luvas revisadas', 'minimo' => '20.000', 'unidade' => 'ml']));
        $this->assertSame('un', $m->getById(1)->unidade);
        $this->assertSame(20.0, (float) $m->getById(1)->minimo);
    }

    public function testSaidaRecusaSaldoNegativoOutroInsumoEAtendimentoCancelado(): void
    {
        $m = $this->estoque();
        $this->entrada($m);
        foreach ([[1, 11000, '1'], [2, 1000, '1'], [1, 1000, '2']] as [$insumo, $qtd, $os]) {
            try {
                $m->movimentar($insumo, 'consumo', $qtd, ['nota' => 'Teste', 'lote_id' => '1', 'os_id' => $os], 1, str_repeat('b', 32));
                $this->fail('Saída inválida.');
            } catch (DomainException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertSame(10.0, (float) $m->lotes(1)[0]->saldo);
        $this->assertSame(1, $m->contarMovimentos(1));
    }

    public function testLoteVencidoNaoEntraNoDisponivelNemNoConsumoMasPermiteDescarte(): void
    {
        $m = $this->estoque();
        $this->entrada($m, '2020-01-01');
        $this->assertSame(0.0, (float) $m->listar('Luvas', 0, 20)[0]->disponivel);
        try {
            $m->movimentar(1, 'consumo', 1000, ['nota' => 'Teste', 'lote_id' => '1', 'os_id' => '1'], 1, str_repeat('b', 32));
            $this->fail('Não pode consumir vencido.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('vencido', $e->getMessage());
        }
        $m->movimentar(1, 'descarte', 10000, ['nota' => 'Descarte por validade', 'lote_id' => '1'], 1, str_repeat('c', 32));
        $this->assertSame(0.0, (float) $m->lotes(1)[0]->saldo);
    }

    public function testEstornoNaoAceitaMovimentoDeOutroInsumo(): void
    {
        $m = $this->estoque();
        $id = $this->entrada($m);
        $this->expectException(DomainException::class);
        $m->estornar(2, $id, 1);
    }

    public function testPermissoesClinicasFinanceirasEAdministrativasSaoSeparadas(): void
    {
        $leitura = permissoesDoFormulario(['vFicha']);
        $this->assertSame(0, $leitura['eFicha']);
        $this->assertSame(0, $leitura['rFicha']);
        $this->assertSame(0, $leitura['cSistema']);
        $escrita = permissoesDoFormulario(['eFicha']);
        $this->assertSame(1, $escrita['vFicha']);
        $this->assertSame(0, $escrita['cModeloFicha']);
        $financeiro = permissoesDoFormulario(['rFinanceiro', 'vLancamento']);
        $this->assertSame(0, $financeiro['vFicha']);
        $admin = new class extends Fichas {
            public function __construct()
            {
            } protected function hasAnyPermission(array $p): bool
            {
                return in_array('cSistema', $p, true);
            }
        };
        try {
            $admin->visualizar(1);
            $this->fail('cSistema não libera clínica.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Sem permissão', $e->getMessage());
        }
        $consulta = new class extends Fichas {
            public function __construct()
            {
            } protected function hasAnyPermission(array $p): bool
            {
                return in_array('vFicha', $p, true);
            }
        };
        foreach (['evolucao', 'anexar', 'modelo'] as $metodo) {
            try {
                $consulta->{$metodo}(1);
                $this->fail('Leitura não permite escrita.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Sem permissão', $e->getMessage());
            }
        }
        $export = new class extends Exportacoes {
            public function __construct()
            {
            } protected function hasAnyPermission(array $p): bool
            {
                return in_array('vFicha', $p, true);
            }
        };
        $this->expectException(RuntimeException::class);
        $export->fichas(1);
    }

    public function testCsvNeutralizaFormulasEPreservaVirgulasAspasEAcentos(): void
    {
        foreach (['=HYPERLINK("x")', '+1+1', '-1+1', '@SUM(A1)', "\t=1", '  =1'] as $ataque) {
            $this->assertStringStartsWith("'", exportacaoCelula($ataque));
        }
        $s = fopen('php://temp', 'w+');
        exportacaoLinha($s, ['Ana; "teste"', 'São Paulo', '=1']);
        rewind($s);
        $this->assertSame(['Ana; "teste"', 'São Paulo', "'=1"], fgetcsv($s, null, ';', '"', ''));
        fclose($s);
    }

    public function testLogisticaValidaLimitesSemMudarValoresDoOrcamento(): void
    {
        [$dados, $erros] = deslocamentoDados(['endereco_atendimento' => 'Rua de exemplo', 'regra_deslocamento' => 'Taxa combinada', 'distancia_km' => '12,50', 'deslocamento_min' => '30', 'valorTotal' => '0']);
        $this->assertSame([], $erros);
        $this->assertSame('12.50', $dados['distancia_km']);
        $this->assertArrayNotHasKey('valorTotal', $dados);
        [, $erros] = deslocamentoDados(['endereco_atendimento' => [], 'regra_deslocamento' => '', 'distancia_km' => '-1', 'deslocamento_min' => '721']);
        $this->assertCount(3, $erros);
    }

    public function testPacoteReaproveitaOsEValoresESemGerarSessoesParaTaxa(): void
    {
        $this->db->query('CREATE TABLE os (idOs INTEGER PRIMARY KEY, status TEXT, descricaoProduto TEXT, valorTotal NUMERIC, faturado INTEGER)');
        $this->db->query("INSERT INTO os VALUES (1, 'Aprovado', 'Consultorias', 630, 1)");
        $this->db->query('CREATE TABLE servicos (idServicos INTEGER PRIMARY KEY, nome TEXT, duracao_min INTEGER)');
        $this->db->query("INSERT INTO servicos VALUES (1, 'Consultoria', 60), (2, 'Deslocamento', 0)");
        $this->db->query('CREATE TABLE servicos_os (os_id INTEGER, servicos_id INTEGER, quantidade NUMERIC)');
        $this->db->query('INSERT INTO servicos_os VALUES (1, 1, 3), (1, 2, 1)');
        $this->db->query('CREATE TABLE pacotes (id INTEGER PRIMARY KEY AUTOINCREMENT, os_id INTEGER UNIQUE, nome TEXT, chave TEXT UNIQUE, criado_em TEXT)');
        $this->db->query('CREATE TABLE pacote_sessoes (id INTEGER PRIMARY KEY AUTOINCREMENT, pacote_id INTEGER, servicos_id INTEGER, nome_servico TEXT, duracao_min INTEGER, numero INTEGER)');
        $m = $this->makeInstance(Pacotes_model::class);
        $this->setPrivateProperty($m, 'db', $this->db);
        try {
            $m->vincular(1, ['2'], str_repeat('a', 32));
            $this->fail('Taxa não gera sessão.');
        } catch (DomainException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $pacote = $m->vincular(1, ['1'], str_repeat('b', 32));
        $this->assertSame($pacote, $m->vincular(1, ['1'], str_repeat('b', 32)));
        $this->assertSame(3, $this->db->count_all('pacote_sessoes'));
        $this->assertSame(1, $this->db->count_all('os'));
        $os = $this->db->get('os')->row();
        $this->assertSame(630.0, (float) $os->valorTotal);
        $this->assertSame(1, (int) $os->faturado);
    }

    public function testIndicadoresAgrupamOrigemEServicosSemIncluirCancelados(): void
    {
        $this->db->query('CREATE TABLE leads (origem TEXT, criado_em TEXT, os_id INTEGER)');
        $this->db->query("INSERT INTO leads VALUES ('whatsapp', '2026-11-01', 1), ('whatsapp', '2026-11-02', NULL), ('instagram', '2026-10-01', 2)");
        $this->db->query('CREATE TABLE os (idOs INTEGER PRIMARY KEY, status TEXT, dataInicial TEXT, faturado INTEGER DEFAULT 0, financeiro_confirmado INTEGER DEFAULT 0)');
        $this->db->query("INSERT INTO os (idOs, status, dataInicial) VALUES (1, 'Aprovado', '2026-11-01'), (2, 'Cancelado', '2026-11-02'), (3, 'Orçamento', '2026-11-03')");
        $this->db->query('CREATE TABLE servicos (idServicos INTEGER PRIMARY KEY, nome TEXT, preco NUMERIC)');
        $this->db->query("INSERT INTO servicos VALUES (1, 'Consultoria', 200)");
        $this->db->query('CREATE TABLE servicos_os (os_id INTEGER, servicos_id INTEGER, quantidade NUMERIC, preco NUMERIC)');
        $this->db->query('INSERT INTO servicos_os VALUES (1, 1, 3, 150), (2, 1, 1, 200), (3, 1, 1, 200)');
        $m = $this->makeInstance(Indicadores_model::class);
        $this->setPrivateProperty($m, 'db', $this->db);
        $origens = $m->origens('2026-11-01', '2026-12-01');
        $this->assertCount(1, $origens);
        $this->assertSame(2, (int) $origens[0]->contatos);
        $this->assertSame(1, (int) $origens[0]->convertidos);
        $this->assertSame(450.0, (float) $m->servicos('2026-11-01', '2026-12-01')[0]->contratado);
    }
}
