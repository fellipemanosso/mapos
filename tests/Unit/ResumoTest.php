<?php

require_once APPPATH . 'helpers/resumo_helper.php';
require_once APPPATH . 'models/Financeiro_model.php';
require_once APPPATH . 'models/Resumo_model.php';
require_once APPPATH . 'controllers/Resumo.php';

final class ResumoTest extends MaposTestCase
{
    private function modelo(): Resumo_model
    {
        $this->db->query('CREATE TABLE lancamentos (idLancamentos INTEGER PRIMARY KEY AUTOINCREMENT, tipo TEXT, valor NUMERIC, valor_desconto NUMERIC DEFAULT 0, desconto NUMERIC DEFAULT 0, baixado INTEGER, data_pagamento TEXT, data_vencimento TEXT, descricao TEXT, despesa_grupo_id INTEGER)');
        $modelo = $this->makeInstance(Resumo_model::class);
        $this->setPrivateProperty($modelo, 'db', $this->db);
        return $modelo;
    }

    private function linha(string $tipo, string $valor, ?string $pagamento, string $vencimento, array $mais = []): void
    {
        $this->db->insert('lancamentos', $mais + ['tipo' => $tipo, 'valor' => $valor, 'baixado' => $pagamento ? 1 : 0, 'data_pagamento' => $pagamento, 'data_vencimento' => $vencimento, 'descricao' => 'Exemplo']);
    }

    public function testRealizadoUsaPagamentoEPendenciasUsamVencimento(): void
    {
        $modelo = $this->modelo();
        $this->linha('receita', '100', '2026-11-05', '2026-12-01');
        $this->linha('despesa', '30', '2026-11-10', '2026-10-01');
        $this->linha('receita', '500', '2026-12-01', '2026-11-01');
        $this->linha('receita', '200', null, '2026-11-20');
        $this->linha('despesa', '50', null, '2026-11-21');
        $this->linha('despesa', '400', null, '2026-12-21');
        $totais = $modelo->totais('2026-11-01', '2026-12-01');
        $this->assertSame(['recebido' => 100.0, 'pago' => 30.0, 'resultado' => 70.0, 'a_receber' => 200.0, 'a_pagar' => 50.0], $totais);
        $this->assertCount(2, $modelo->pendencias('2026-11-01', '2026-12-01'));
        $this->assertCount(2, $modelo->movimento('2026-11-01', '2026-12-01'));
    }

    public function testDescontoEstornoEFechamentoDeAno(): void
    {
        $modelo = $this->modelo();
        $this->linha('receita', '100', '2026-12-31 23:59:59', '2026-12-31', ['valor_desconto' => '90', 'desconto' => '10']);
        $this->linha('receita', '0', '2026-12-10', '2026-12-10');
        $this->linha('receita', '999', '2027-01-01', '2026-12-31');
        $this->linha('despesa', '50', '2026-12-15', '2026-12-15', ['desconto' => '5']);
        $totais = $modelo->totais('2026-12-01', '2027-01-01');
        $this->assertSame(90.0, $totais['recebido']);
        $this->assertSame(45.0, $totais['pago']);
        $this->assertSame(45.0, $totais['resultado']);
    }

    public function testCategoriasIncluemDespesasAntigasSemCategoria(): void
    {
        $modelo = $this->modelo();
        $this->db->query('CREATE TABLE despesa_grupos (id INTEGER PRIMARY KEY, categoria TEXT)');
        $this->db->query("INSERT INTO despesa_grupos VALUES (1, 'insumos'), (2, 'meta')");
        $this->linha('despesa', '400', '2026-11-11', '2026-12-01', ['despesa_grupo_id' => 1]);
        $this->linha('despesa', '20', '2026-11-12', '2026-11-12', ['despesa_grupo_id' => 2]);
        $this->linha('despesa', '10', '2026-11-12', '2026-11-12');
        $this->linha('despesa', '999', null, '2026-11-12', ['despesa_grupo_id' => 2]);
        $categorias = $modelo->categorias('2026-11-01', '2026-12-01');
        $this->assertSame(['insumos', 'meta', 'sem_categoria'], array_column($categorias, 'categoria'));
        $this->assertSame([400.0, 20.0, 10.0], array_map(static fn ($c) => (float) $c->valor, $categorias));
        $this->assertSame(430.0, $modelo->totais('2026-11-01', '2026-12-01')['pago']);
    }

    public function testMesSemMovimentoEInstalacaoSemGrupos(): void
    {
        $modelo = $this->modelo();
        $this->assertSame(['recebido' => 0.0, 'pago' => 0.0, 'resultado' => 0.0, 'a_receber' => 0.0, 'a_pagar' => 0.0], $modelo->totais('2026-11-01', '2026-12-01'));
        $this->assertSame([], $modelo->categorias('2026-11-01', '2026-12-01'));
        $this->linha('despesa', '20', '2026-11-10', '2026-11-10');
        $this->assertSame('sem_categoria', $modelo->categorias('2026-11-01', '2026-12-01')[0]->categoria);
    }

    public function testMesInvalidoVoltaAoAtual(): void
    {
        foreach ([null, [], '2026-13', '2026-1', '2026-11<script>', '0000-01', '9999-12'] as $mes) {
            $this->assertSame('2026-11-01', resumoMes($mes, '2026-11-11')->format('Y-m-d'));
        }
        $this->assertSame('2024-02-01', resumoMes('2024-02', '2026-11-11')->format('Y-m-d'));
    }

    public function testSemPermissaoNaoConsultaFinanceiro(): void
    {
        $controller = new class extends Resumo {
            public function __construct()
            {
            }
            protected function hasAnyPermission(array $permissoes): bool
            {
                return false;
            }
        };
        $this->expectException(RuntimeException::class);
        $controller->index();
    }
}
