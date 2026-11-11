<?php

require_once APPPATH . 'helpers/despesas_helper.php';
require_once APPPATH . 'models/Despesas_model.php';
require_once APPPATH . 'models/Financeiro_model.php';
require_once APPPATH . 'controllers/Despesas.php';

final class DespesasTest extends MaposTestCase
{
    private function post(array $mais = []): array
    {
        return $mais + ['descricao' => 'Compra de insumos', 'categoria' => 'insumos', 'modo' => 'parcelada', 'cliente_fornecedor' => 'Loja demo',
            'valor' => '1.200,00', 'desconto' => '', 'data_vencimento' => '2026-01-31', 'forma_pgto' => 'Cartão de Crédito',
            'parcelas' => '3', 'entrada' => '', 'chave' => str_repeat('a', 32)];
    }

    public function testCompraParceladaDivideOTotalSemMultiplicarDespesa(): void
    {
        [$grupo, $linhas, $erros] = despesaDoFormulario($this->post(), '2026-01-20');
        $this->assertSame([], $erros);
        $this->assertSame('1200.00', $grupo['total_original']);
        $this->assertSame(['400.00', '400.00', '400.00'], array_column($linhas, 'valor_desconto'));
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], array_column($linhas, 'data_vencimento'));
        $this->assertSame([0, 0, 0], array_column($linhas, 'baixado'));
    }

    public function testEntradaECentavosConservamTotalExato(): void
    {
        [$grupo, $linhas, $erros] = despesaDoFormulario($this->post(['valor' => '100,00', 'entrada' => '10,00', 'data_entrada' => '2026-01-20']), '2026-01-20');
        $this->assertSame([], $erros);
        $this->assertSame(['10.00', '30.00', '30.00', '30.00'], array_column($linhas, 'valor_desconto'));
        $this->assertSame(1, $linhas[0]['baixado']);
        $this->assertSame('100.00', $grupo['total_original']);
        [, $linhas] = despesaDoFormulario($this->post(['valor' => '100,00']), '2026-01-20');
        $this->assertSame(['33.34', '33.33', '33.33'], array_column($linhas, 'valor_desconto'));
    }

    public function testMetaDiariaRepeteValorPorOcorrencia(): void
    {
        [$grupo, $linhas, $erros] = despesaDoFormulario($this->post(['categoria' => 'meta', 'modo' => 'recorrente', 'frequencia' => 'dia', 'ocorrencias' => '30', 'valor' => '20,00']), '2026-01-20');
        $this->assertSame([], $erros);
        $this->assertCount(30, $linhas);
        $this->assertSame('600.00', $grupo['total_original']);
        $this->assertSame('20.00', $linhas[29]['valor_desconto']);
        $this->assertSame('2026-03-01', $linhas[29]['data_vencimento']);
        $this->assertSame(0, $linhas[0]['baixado']);
    }

    public function testRecorrenciaSemanalEMensalRespeitaDatas(): void
    {
        [, $semanal, $erros] = despesaDoFormulario($this->post(['modo' => 'recorrente', 'frequencia' => 'semana', 'ocorrencias' => '3']), '2026-01-20');
        $this->assertSame([], $erros);
        $this->assertSame(['2026-01-31', '2026-02-07', '2026-02-14'], array_column($semanal, 'data_vencimento'));
        [, $mensal] = despesaDoFormulario($this->post(['modo' => 'recorrente', 'frequencia' => 'mes', 'ocorrencias' => '3']), '2026-01-20');
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], array_column($mensal, 'data_vencimento'));
    }

    public function testAVistaPagaEFalsificacaoDeReceitaNaoMudaTipo(): void
    {
        [, $linhas, $erros] = despesaDoFormulario($this->post(['modo' => 'avista', 'tipo' => 'receita', 'baixado' => '1', 'data_pagamento' => '2026-01-20', 'forma_pgto' => 'Pix']), '2026-01-20');
        $this->assertSame([], $erros);
        $this->assertSame('despesa', $linhas[0]['tipo']);
        $this->assertSame(1, $linhas[0]['baixado']);
        $this->assertCount(1, $linhas);
    }

    public function testRecusaValoresParcelasEFrequenciaInvalidos(): void
    {
        foreach ([['parcelas' => '0'], ['parcelas' => '61'], ['parcelas' => '1.5'], ['valor' => '0.01', 'parcelas' => '3'], ['entrada' => '1200'], ['entrada' => '-1'], ['baixado' => '1', 'data_pagamento' => '2026-01-20'], ['categoria' => 'invalida'], ['modo' => 'recorrente', 'frequencia' => 'anual', 'ocorrencias' => '367']] as $valores) {
            [$grupo, $linhas, $erros] = despesaDoFormulario($this->post($valores), '2026-01-20');
            $this->assertNotEmpty($erros);
            $this->assertSame([], $grupo);
            $this->assertSame([], $linhas);
        }
    }

    public function testNaoPermitePagamentoOuEntradaNoFuturo(): void
    {
        [, , $erros] = despesaDoFormulario($this->post(['modo' => 'avista', 'baixado' => '1', 'data_pagamento' => '2026-01-21']), '2026-01-20');
        $this->assertArrayHasKey('data_pagamento', $erros);
        [, , $erros] = despesaDoFormulario($this->post(['entrada' => '10', 'data_entrada' => '2026-01-21']), '2026-01-20');
        $this->assertArrayHasKey('data_entrada', $erros);
    }

    public function testRespeitaControleDeBaixaEDatasLimite(): void
    {
        [, , $erros] = despesaDoFormulario($this->post(['entrada' => '100', 'data_entrada' => '2026-01-19']), '2026-01-20', true);
        $this->assertArrayHasKey('data_entrada', $erros);
        [, , $erros] = despesaDoFormulario($this->post(['data_vencimento' => '9999-12-31']), '2026-01-20');
        $this->assertArrayHasKey('data_vencimento', $erros);
    }

    private function modelo(): Despesas_model
    {
        $this->db->query('CREATE TABLE usuarios (idUsuarios INTEGER PRIMARY KEY)');
        $this->db->query('INSERT INTO usuarios VALUES (1)');
        $this->db->query('CREATE TABLE despesa_grupos (id INTEGER PRIMARY KEY AUTOINCREMENT, chave TEXT UNIQUE, descricao TEXT, categoria TEXT, modo TEXT, frequencia TEXT, quantidade INTEGER, total_original NUMERIC, criado_em TEXT)');
        $this->db->query('CREATE TABLE lancamentos (idLancamentos INTEGER PRIMARY KEY AUTOINCREMENT, despesa_grupo_id INTEGER, descricao TEXT, tipo TEXT, cliente_fornecedor TEXT, clientes_id INTEGER, forma_pgto TEXT, observacoes TEXT, tipo_desconto TEXT, usuarios_id INTEGER, valor NUMERIC, desconto NUMERIC, valor_desconto NUMERIC, data_vencimento TEXT, data_pagamento TEXT, baixado INTEGER)');
        $modelo = $this->makeInstance(Despesas_model::class);
        $this->setPrivateProperty($modelo, 'db', $this->db);
        return $modelo;
    }

    public function testParcelasGravamUmaVezEBaixaIndividualAtualizaResumo(): void
    {
        $modelo = $this->modelo();
        [$grupo, $linhas] = despesaDoFormulario($this->post(), '2026-01-20');
        $id = $modelo->criar($grupo, $linhas, 1);
        $this->assertSame($id, $modelo->criar($grupo, $linhas, 1));
        $this->assertCount(3, $modelo->linhas($id));
        $this->assertSame(120000, $modelo->resumo($id)['pendente']);
        $financeiro = $this->makeInstance(Financeiro_model::class);
        $this->setPrivateProperty($financeiro, 'db', $this->db);
        $parcela = (int) $modelo->linhas($id)[0]->idLancamentos;
        $this->assertTrue($financeiro->atualizar($parcela, ['baixado' => 1, 'data_pagamento' => '2026-01-31', 'forma_pgto' => 'Cartão de Crédito']));
        $this->assertSame(40000, $modelo->resumo($id)['pago']);
        $this->assertSame(80000, $modelo->resumo($id)['pendente']);
        $this->assertFalse($financeiro->atualizar($parcela, ['tipo' => 'receita']));
    }

    public function testSemPermissaoNaoAbreNemEnviaComprovante(): void
    {
        foreach (['comprovante', 'anexar', 'formulario'] as $metodo) {
            $controller = new class extends Despesas {
                public function __construct()
                {
                }
                protected function hasAnyPermission(array $permissoes): bool
                {
                    return false;
                }
            };
            try {
                $controller->{$metodo}(1);
                $this->fail('Deveria negar sem permissão.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Sem permissão', $e->getMessage());
            }
        }
    }
}
