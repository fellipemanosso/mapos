<?php

require_once APPPATH . 'helpers/custos_helper.php';
require_once APPPATH . 'helpers/financeiro_helper.php';
require_once APPPATH . 'helpers/insumos_helper.php';
require_once APPPATH . 'helpers/fichas_helper.php';
require_once APPPATH . 'models/Custos_model.php';
require_once APPPATH . 'models/Insumos_model.php';
require_once APPPATH . 'core/MY_Controller.php';
require_once APPPATH . 'controllers/Custos.php';

final class CustosTest extends MaposTestCase
{
    public function testEntradaExigeCustoExplicitoMasPermiteZeroEConsumoSemCampoCusto(): void
    {
        $m = $this->modelo();
        $dados = ['lote' => 'Gratuito', 'nota' => 'Amostra', 'validade' => '2099-01-01'];
        foreach ([null, '', ' ', 'desconhecido', '-1', []] as $invalido) {
            try {
                $m->estoque->movimentar(2, 'entrada', 1000, $dados + ['custo' => $invalido], 1, str_repeat('d', 32));
                $this->fail('Custo não informado ou inválido não pode virar zero.');
            } catch (DomainException $e) {
                $this->assertStringContainsString('custo total', $e->getMessage());
                $this->assertCount(0, $m->estoque->lotes(2));
                $this->assertSame(0, $m->estoque->contarMovimentos(2));
            }
        }
        $m->estoque->movimentar(2, 'entrada', 1000, $dados + ['custo' => '0,00'], 1, str_repeat('d', 32));
        $lote = $m->estoque->lotes(2)[0];
        $this->assertSame(0.0, (float) $lote->custo_unitario);
        $m->estoque->movimentar(2, 'consumo', 500, ['lote_id' => (string) $lote->id, 'os_id' => '1', 'nota' => 'Uso da amostra'], 1, str_repeat('e', 32));
        $this->assertSame(0.5, (float) $m->estoque->lotes(2)[0]->saldo);
        $this->assertSame(0.0, (float) $m->estoque->movimentos(2, 0, 10)[0]->custo);
        $m->estoque->movimentar(2, 'descarte', 500, ['lote_id' => (string) $lote->id, 'nota' => 'Perda'], 1, str_repeat('f', 32));
        $this->assertSame(0.0, (float) $m->estoque->lotes(2)[0]->saldo);
    }

    private function modelo(): Custos_model
    {
        foreach ([
            'os (idOs INTEGER PRIMARY KEY, status TEXT)',
            'atendimento_conferencia (os_id INTEGER PRIMARY KEY, assinatura TEXT, usuarios_id INTEGER, criado_em TEXT)',
            'atendimento_custos (id INTEGER PRIMARY KEY AUTOINCREMENT, os_id INTEGER, tipo TEXT, descricao TEXT, valor NUMERIC, usuarios_id INTEGER, criado_em TEXT, estornado_em TEXT, estornado_por INTEGER, chave TEXT UNIQUE)',
            'insumos (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, unidade TEXT, minimo NUMERIC)',
            'insumo_lotes (id INTEGER PRIMARY KEY AUTOINCREMENT, insumo_id INTEGER, lote TEXT, validade TEXT, saldo NUMERIC, custo_unitario NUMERIC, criado_em TEXT)',
            'insumo_movimentos (id INTEGER PRIMARY KEY AUTOINCREMENT, lote_id INTEGER, os_id INTEGER, tipo TEXT, quantidade NUMERIC, custo NUMERIC, nota TEXT, usuarios_id INTEGER, criado_em TEXT, estornado_em TEXT, estornado_por INTEGER, chave TEXT UNIQUE)',
            'servicos (idServicos INTEGER PRIMARY KEY, nome TEXT)',
            'servicos_os (idServicos_os INTEGER PRIMARY KEY, os_id INTEGER, servicos_id INTEGER, quantidade NUMERIC)',
            'servico_insumos (id INTEGER PRIMARY KEY AUTOINCREMENT, servico_id INTEGER, insumo_id INTEGER, quantidade NUMERIC, UNIQUE(servico_id, insumo_id))',
            'atendimento_insumos_padrao (id INTEGER PRIMARY KEY AUTOINCREMENT, os_id INTEGER, item_id INTEGER, criado_em TEXT, UNIQUE(os_id, item_id))',
        ] as $sql) {
            $this->db->query('CREATE TABLE ' . $sql);
        }
        $this->db->query("INSERT INTO os VALUES (1, 'Aprovado'), (2, 'Cancelado'), (3, 'Faturado')");
        $this->db->query("INSERT INTO servicos VALUES (1, 'Perfuração')");
        $this->db->query('INSERT INTO servicos_os VALUES (1, 1, 1, 2)');
        $estoque = $this->makeInstance(Insumos_model::class);
        $this->setPrivateProperty($estoque, 'db', $this->db);
        $estoque->cadastrar(['nome' => 'Gaze', 'unidade' => 'un', 'minimo' => 0]);
        $estoque->cadastrar(['nome' => 'Luva', 'unidade' => 'un', 'minimo' => 0]);
        $estoque->movimentar(1, 'entrada', 10000, ['lote' => 'G-1', 'nota' => 'Compra', 'validade' => '2099-01-01', 'custo' => '20,00'], 1, str_repeat('a', 32));
        $m = $this->makeInstance(Custos_model::class);
        $this->setPrivateProperty($m, 'db', $this->db);
        $m->estoque = $estoque;
        return $m;
    }

    public function testResultadoDistingueCobradoRecebidoCustosENegativo(): void
    {
        $r = custosResultado(17000, 5000, 400, 1500);
        $this->assertSame(1900, $r['custo']);
        $this->assertSame(15100, $r['resultado']);
        $this->assertSame(3100, $r['recebido_menos_custos']);
        $this->assertSame(12000, $r['saldo']);
        $this->assertSame(-1000, custosResultado(1000, 0, 1500, 500)['resultado']);
    }

    public function testCustoDepoisDeFaturarIdempotenteEEstornoAuditavel(): void
    {
        $m = $this->modelo();
        $dados = ['tipo' => 'taxa', 'descricao' => 'Cartão', 'valor' => '5,00'];
        $m->adicionar(3, $dados, 1, str_repeat('b', 32));
        $m->adicionar(3, $dados, 1, str_repeat('b', 32));
        $this->assertCount(1, $m->custos(3));
        $this->assertSame(5.0, (float) $m->custos(3)[0]->valor);
        $m->estornar(3, 1, 7);
        $m->estornar(3, 1, 8);
        $this->assertSame(7, (int) $m->custos(3)[0]->estornado_por);
        $this->assertSame('Faturado', $this->db->where('idOs', 3)->get('os')->row()->status);
    }

    public function testCustoRecusaOutroAtendimentoCanceladoEValorInvalido(): void
    {
        $m = $this->modelo();
        $dados = ['tipo' => 'outro', 'descricao' => 'Teste', 'valor' => '3,00'];
        $m->adicionar(1, $dados, 1, str_repeat('b', 32));
        foreach ([fn () => $m->estornar(3, 1, 1), fn () => $m->adicionar(3, $dados, 1, str_repeat('b', 32)),
            fn () => $m->adicionar(2, $dados, 1, str_repeat('c', 32)), fn () => $m->adicionar(1, array_replace($dados, ['valor' => '-5']), 1, str_repeat('d', 32))] as $acao) {
            try {
                $acao();
                $this->fail('Operação inválida deveria ser recusada.');
            } catch (DomainException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertCount(1, $m->custos(1));
        $this->assertNull($m->custos(1)[0]->estornado_em);
    }

    public function testConsumoUsaCustoDoLoteReenvioNaoDuplicaEEstornoDevolveEstoque(): void
    {
        $m = $this->modelo();
        $m->consumir(1, 1, 2000, 1, str_repeat('b', 32));
        $m->consumir(1, 1, 2000, 1, str_repeat('b', 32));
        $this->assertCount(1, $m->consumos(1));
        $this->assertSame(4.0, (float) $m->consumos(1)[0]->custo);
        $this->assertSame(8.0, (float) $m->estoque->lotes(1)[0]->saldo);
        try {
            $m->estornarConsumo(3, 2, 1);
            $this->fail('Consumo de outro atendimento.');
        } catch (DomainException) {
            $this->assertSame(8.0, (float) $m->estoque->lotes(1)[0]->saldo);
        }
        $m->estornarConsumo(1, 2, 1);
        $this->assertSame(10.0, (float) $m->estoque->lotes(1)[0]->saldo);
    }

    public function testPadraoMultiplicaQuantidadeERepeticaoNaoBaixaDuasVezes(): void
    {
        $m = $this->modelo();
        $m->salvarPadrao(1, 1, 2000);
        $m->aplicarPadroes(1, 1);
        $m->aplicarPadroes(1, 1);
        $this->assertSame(6.0, (float) $m->estoque->lotes(1)[0]->saldo);
        $this->assertSame(8.0, (float) $m->consumos(1)[0]->custo);
        $this->assertCount(1, $m->consumos(1));
        $m->salvarPadrao(1, 1, 3000);
        $m->aplicarPadroes(1, 1);
        $this->assertSame(6.0, (float) $m->estoque->lotes(1)[0]->saldo);
        $m->salvarPadrao(1, 1, 0);
        $this->assertCount(0, $m->padrao(1));
    }

    public function testFaltaDeUmInsumoReverteTodosOsConsumosDoPadrao(): void
    {
        $m = $this->modelo();
        $m->salvarPadrao(1, 1, 2000);
        $m->salvarPadrao(1, 2, 1000);
        try {
            $m->aplicarPadroes(1, 1);
            $this->fail('Não há luvas no estoque.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Estoque insuficiente', $e->getMessage());
        }
        $this->assertSame(10.0, (float) $m->estoque->lotes(1)[0]->saldo);
        $this->assertCount(0, $m->consumos(1));
        $this->assertSame(0, $this->db->count_all('atendimento_insumos_padrao'));
    }

    public function testPadraoPriorizaValidadeERecusaLoteVencido(): void
    {
        $m = $this->modelo();
        $m->estoque->movimentar(1, 'entrada', 1000, ['lote' => 'G-2', 'nota' => 'Compra', 'validade' => '2098-01-01', 'custo' => '3,00'], 1, str_repeat('b', 32));
        $m->salvarPadrao(1, 1, 1000);
        $m->aplicarPadroes(1, 1);
        $this->assertSame(0.0, (float) $m->estoque->lotes(1)[0]->saldo);
        $this->assertSame(9.0, (float) $m->estoque->lotes(1)[1]->saldo);
        $this->db->where('id', 1)->update('insumo_lotes', ['validade' => '2020-01-01']);
        $this->expectException(DomainException::class);
        $m->consumir(3, 1, 1000, 1, str_repeat('c', 32));
    }

    public function testConferenciaDescontaManualPermiteAjusteERecusaReenvioAntigo(): void
    {
        $m = $this->modelo();
        $m->salvarPadrao(1, 1, 2000);
        $m->consumir(1, 1, 1000, 1, str_repeat('b', 32));
        $previa = $m->previa(1);
        $this->assertSame(4000, $previa['materiais'][1]['previsto']);
        $this->assertSame(1000, $previa['materiais'][1]['registrado']);
        $this->assertFalse((bool) $previa['conferido']);
        $m->confirmarMateriais(1, [1 => '3,000'], $previa['assinatura'], 1);
        $this->assertSame(7.0, (float) $m->estoque->lotes(1)[0]->saldo);
        $this->assertTrue((bool) $m->previa(1)['conferido']);
        try {
            $m->confirmarMateriais(1, [1 => '3,000'], $previa['assinatura'], 1);
            $this->fail('Prévia antiga deve ser recusada.');
        } catch (DomainException) {
            $this->assertCount(2, $m->consumos(1));
        }
        $atual = $m->previa(1);
        $m->confirmarMateriais(1, [1 => '3,000'], $atual['assinatura'], 1);
        $this->assertCount(2, $m->consumos(1));
        $m->salvarPadrao(1, 1, 3000);
        $this->assertFalse((bool) $m->previa(1)['conferido']);
    }

    public function testConferenciaAtomicaFaltaDeEstoqueEConsumoAlterado(): void
    {
        $m = $this->modelo();
        $m->salvarPadrao(1, 1, 2000);
        $m->salvarPadrao(1, 2, 1000);
        $previa = $m->previa(1);
        try {
            $m->confirmarMateriais(1, [1 => '4', 2 => '2'], $previa['assinatura'], 1);
            $this->fail('Luvas sem estoque.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Estoque insuficiente', $e->getMessage());
        }
        $this->assertCount(0, $m->consumos(1));
        $this->assertSame(10.0, (float) $m->estoque->lotes(1)[0]->saldo);
        $this->assertFalse((bool) $m->previa(1)['conferido']);
        $m->consumir(1, 1, 1000, 1, str_repeat('b', 32));
        $this->expectException(DomainException::class);
        $m->confirmarMateriais(1, [1 => '4', 2 => '0'], $previa['assinatura'], 1);
    }

    public function testPermissaoFinanceiraExigidaMesmoConhecendoAUrl(): void
    {
        $controller = new class extends Custos {
            public function __construct()
            {
            }
            protected function hasAnyPermission(array $permissoes): bool
            {
                return $permissoes === ['vOs'];
            }
        };
        foreach (['index', 'salvar', 'padrao'] as $metodo) {
            try {
                $controller->{$metodo}(1);
                $this->fail('Rota interna não pode ser aberta só com vOs.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Sem permissão', $e->getMessage());
            }
        }
    }

    public function testComposicaoSalvaVariasQuantidadesERemoveEmUmEnvio(): void
    {
        $m = $this->modelo();
        $m->salvarPadrao(1, 1, 2000);
        $m->salvarPadrao(1, 2, 1000);
        $m->salvarComposicao(1, [1 => 3500, 2 => 2500]);
        $this->assertSame([3.5, 2.5], array_map(static fn ($i) => (float) $i->quantidade, $m->padrao(1)));
        $m->salvarComposicao(1, [1 => 4000, 2 => 0]);
        $this->assertCount(1, $m->padrao(1));
        $this->assertSame(4.0, (float) $m->padrao(1)[0]->quantidade);
        $this->assertCount(0, $m->consumos(1));
    }

    public function testComposicaoRecusaDadosInvalidosOuItensAlteradosSemSalvarParte(): void
    {
        $m = $this->modelo();
        $m->salvarPadrao(1, 1, 2000);
        $m->salvarPadrao(1, 2, 1000);
        foreach ([[1 => 4000, 2 => -1], [1 => 4000, 2 => 1000000000], [1 => 4000], [1 => 4000, 3 => 1000]] as $quantidades) {
            try {
                $m->salvarComposicao(1, $quantidades);
                $this->fail('Composição inválida não deve ser salva.');
            } catch (DomainException) {
                $this->assertSame([2.0, 1.0], array_map(static fn ($i) => (float) $i->quantidade, $m->padrao(1)));
            }
        }
    }
}
