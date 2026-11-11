<?php

require_once APPPATH . 'helpers/recebimentos_helper.php';
require_once APPPATH . 'models/Recebimentos_model.php';
require_once APPPATH . 'models/Financeiro_model.php';
require_once APPPATH . 'models/Os_model.php';
require_once APPPATH . 'controllers/Recebimentos.php';

final class RecebimentosTest extends MaposTestCase
{
    private function modelo(): Recebimentos_model
    {
        $this->db->query('CREATE TABLE os (idOs INTEGER PRIMARY KEY, clientes_id INTEGER, status TEXT, faturado INTEGER DEFAULT 0, financeiro_confirmado INTEGER DEFAULT 0, lancamento INTEGER, sinal_previsto NUMERIC DEFAULT 0, saldo_vencimento TEXT)');
        $this->db->query("INSERT INTO os (idOs, clientes_id, status, saldo_vencimento) VALUES (1, 1, 'Aberto', '2026-11-20'), (2, 1, 'Cancelado', '2026-11-20'), (3, 1, 'Aberto', '2026-11-20')");
        $this->db->query('CREATE TABLE clientes (idClientes INTEGER PRIMARY KEY, nomeCliente TEXT)');
        $this->db->query("INSERT INTO clientes VALUES (1, 'Cliente fictícia')");
        $this->db->query('CREATE TABLE lancamentos (idLancamentos INTEGER PRIMARY KEY AUTOINCREMENT, atendimento_id INTEGER, recebimento_tipo TEXT, recebimento_valor NUMERIC, chave_operacao TEXT UNIQUE, estornado_em TEXT, descricao TEXT, valor NUMERIC, valor_desconto NUMERIC, desconto NUMERIC, tipo_desconto TEXT, tipo TEXT, clientes_id INTEGER, cliente_fornecedor TEXT, usuarios_id INTEGER, data_vencimento TEXT, data_pagamento TEXT, baixado INTEGER, forma_pgto TEXT, observacoes TEXT)');
        $model = $this->makeInstance(Recebimentos_model::class);
        $this->setPrivateProperty($model, 'db', $this->db);
        $model->osModel = new class {
            public array $historico = [];

            public function registrarStatus($id, $antes, $depois, $usuario): void
            {
                $this->historico[] = [$id, $antes, $depois, $usuario];
            }

            public function totais(object $os): array
            {
                // Orçamento com serviço e deslocamento, já líquido de desconto.
                return ['total' => 230.01];
            }
        };
        return $model;
    }

    private function dados(int $centavos, string $tipo, string $chave = 'a'): array
    {
        return ['centavos' => $centavos, 'tipo' => $tipo, 'chave' => str_repeat($chave, 32), 'data' => '2026-11-11', 'forma' => 'Pix', 'observacoes' => ''];
    }

    public function testSinalEQuitacaoAtualizamSaldoSemDuplicarReceita(): void
    {
        $model = $this->modelo();
        $model->orcamento(1, '50.00', '2026-11-20');
        $model->receber(1, $this->dados(5000, 'sinal'), 1);
        $this->assertSame(5000, $model->recebido(1));
        $saldo = $this->db->where('recebimento_tipo', 'saldo')->get('lancamentos')->row();
        $this->assertSame(180.01, (float) $saldo->valor);
        $this->assertSame(0, (int) $saldo->baixado);
        $model->receber(1, $this->dados(18001, 'quitacao', 'b'), 1);
        $this->assertSame(23001, $model->recebido(1));
        $this->assertSame(0, $this->db->where('recebimento_tipo', 'saldo')->count_all_results('lancamentos'));
        $this->assertSame(230.01, (float) $this->db->select_sum('valor')->get('lancamentos')->row()->valor);
    }

    public function testIntegralDesdeInicioEReenvioIdempotente(): void
    {
        $model = $this->modelo();
        $model->receber(1, $this->dados(23001, 'integral'), 1);
        $model->receber(1, $this->dados(23001, 'integral'), 1);
        $this->assertSame(1, $this->db->count_all_results('lancamentos'));
        $this->assertSame(23001, $model->recebido(1));
    }

    public function testSinalAprovaOrcamentoERegistraMudancaDeStatus(): void
    {
        $model = $this->modelo();
        $this->db->where('idOs', 1)->update('os', ['status' => 'Orçamento']);
        $model->receber(1, $this->dados(5000, 'sinal'), 1);
        $this->assertSame('Aprovado', $this->db->where('idOs', 1)->get('os')->row()->status);
        $this->assertSame([[1, 'Orçamento', 'Aprovado', 1]], $model->osModel->historico);
    }

    public function testPixUsaSomenteSaldoENaoGeraCobrancaDepoisDaQuitacao(): void
    {
        $model = $this->modelo();
        $os = new class extends Os_model {
            public function __construct()
            {
            }
            public function valorTotalOS($id = null)
            {
                return ['valor_desconto' => 0, 'totalServico' => 230.01, 'totalProdutos' => 0];
            }
        };
        $this->setPrivateProperty($os, 'db', $this->db);
        $emitente = (object) ['nome' => 'Demo', 'cidade' => 'Sao Paulo'];
        $model->receber(1, $this->dados(5000, 'sinal'), 1);
        $this->assertStringContainsString('5406180.01', $os->getPixPayload(1, '11999998888', $emitente));
        $model->receber(1, $this->dados(18001, 'quitacao', 'b'), 1);
        $this->assertNull($os->getPixPayload(1, '11999998888', $emitente));
    }

    public function testPagamentoParcialAdicionalESaldoDeUmCentavo(): void
    {
        $model = $this->modelo();
        $model->receber(1, $this->dados(10000, 'sinal'), 1);
        $model->receber(1, $this->dados(13000, 'parcial', 'b'), 1);
        $saldo = $this->db->where('recebimento_tipo', 'saldo')->get('lancamentos')->row();
        $this->assertSame(0.01, (float) $saldo->valor);
        $model->receber(1, $this->dados(1, 'quitacao', 'c'), 1);
        $this->assertSame(23001, $model->recebido(1));
    }

    public function testRecusaValorAcimaDoSaldoSemGravacaoParcial(): void
    {
        $model = $this->modelo();
        $model->receber(1, $this->dados(5000, 'sinal'), 1);
        try {
            $model->receber(1, $this->dados(18002, 'parcial', 'b'), 1);
            $this->fail('Deveria recusar valor acima do saldo.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('superar o saldo', $e->getMessage());
        }
        $this->assertSame(5000, $model->recebido(1));
        $this->assertCount(1, $model->historico(1));
    }

    public function testEstornoPreservaHistoricoEReabreSaldo(): void
    {
        $model = $this->modelo();
        $model->receber(1, $this->dados(5000, 'sinal'), 1);
        $model->receber(1, $this->dados(18001, 'quitacao', 'b'), 1);
        $quitacao = $model->historico(1)[0];
        $model->estornar(1, (int) $quitacao->idLancamentos, 1);
        $this->assertSame(5000, $model->recebido(1));
        $this->assertCount(2, $model->historico(1));
        $this->assertNotNull($model->historico(1)[0]->estornado_em);
        $this->assertSame(230.01, (float) $this->db->select_sum('valor')->get('lancamentos')->row()->valor);
        $sinal = $model->historico(1)[1];
        $model->estornar(1, (int) $sinal->idLancamentos, 1);
        $model->estornar(1, (int) $sinal->idLancamentos, 1);
        $os = $this->db->where('idOs', 1)->get('os')->row();
        $this->assertSame(0, (int) $os->financeiro_confirmado);
        $this->assertSame(0, (int) $os->faturado);
        $this->assertNull($os->lancamento);
        $this->assertSame(0, $model->recebido(1));
        $model->orcamento(1, '60.00', '2026-11-21');
    }

    public function testNaoEstornaRecebimentoDeOutroAtendimento(): void
    {
        $model = $this->modelo();
        $model->receber(1, $this->dados(5000, 'sinal'), 1);
        $pagamento = $model->historico(1)[0];
        $this->expectException(DomainException::class);
        $model->estornar(3, (int) $pagamento->idLancamentos, 1);
    }

    public function testNaoReutilizaChaveDeOutroAtendimento(): void
    {
        $model = $this->modelo();
        $model->receber(1, $this->dados(5000, 'sinal'), 1);
        $this->expectException(DomainException::class);
        $model->receber(3, $this->dados(5000, 'sinal'), 1);
    }

    public function testRecusaAtendimentoCancelado(): void
    {
        $model = $this->modelo();
        $this->expectException(DomainException::class);
        $model->receber(2, $this->dados(5000, 'sinal'), 1);
    }

    public function testNaoDuplicaFaturaLegada(): void
    {
        $model = $this->modelo();
        $this->db->where('idOs', 1)->update('os', ['faturado' => 1]);
        $this->expectException(DomainException::class);
        $model->receber(1, $this->dados(5000, 'sinal'), 1);
    }

    public function testOrcamentoConfirmadoNaoPodeSerAlterado(): void
    {
        $model = $this->modelo();
        $model->receber(1, $this->dados(5000, 'sinal'), 1);
        $this->expectException(DomainException::class);
        $model->orcamento(1, '100.00', '2026-11-20');
    }

    public function testFinanceiroNaoEditaNemExcluiLancamentoVinculado(): void
    {
        $model = $this->modelo();
        $model->receber(1, $this->dados(5000, 'sinal'), 1);
        $financeiro = $this->makeInstance(Financeiro_model::class);
        $this->setPrivateProperty($financeiro, 'db', $this->db);
        $id = (int) $model->historico(1)[0]->idLancamentos;
        $this->assertFalse($financeiro->atualizar($id, ['valor' => 999]));
        $this->assertFalse($financeiro->excluir($id, 1));
        $this->assertSame(5000, $model->recebido(1));
    }

    public function testValidaValorDataFormaETipoNoServidor(): void
    {
        [$dados, $erros] = recebimentoDoFormulario(['valor' => '1.234,56', 'data' => '2026-11-11', 'forma' => 'Pix', 'tipo' => 'integral', 'chave' => str_repeat('a', 32)], '2026-11-11');
        $this->assertSame([], $erros);
        $this->assertSame(123456, $dados['centavos']);
        foreach (['0', '-1', '1.001', [], 'abc'] as $valor) {
            [, $erros] = recebimentoDoFormulario(['valor' => $valor, 'data' => '2026-02-30', 'forma' => 'Outro', 'tipo' => 'invalido', 'chave' => ''], '2026-11-11');
            foreach (['valor', 'data', 'forma', 'tipo', '_geral'] as $campo) {
                $this->assertArrayHasKey($campo, $erros);
            }
        }
    }

    public function testExigePermissoesFinanceirasAlemDeEditarAtendimento(): void
    {
        foreach (['receber', 'estornar'] as $metodo) {
            $controller = new class extends Recebimentos {
                public $permission;
                public $session;
                public function __construct()
                {
                }
            };
            $controller->session = new class {
                public function userdata($nome)
                {
                    return 1;
                }
            };
            $controller->permission = new class {
                public function checkPermission($id, $permissao)
                {
                    return $permissao === 'eOs';
                }
            };
            try {
                $controller->{$metodo}(1);
                $this->fail('Deveria negar sem a permissão financeira.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('É preciso', $e->getMessage());
            }
        }
    }

    public function testRecusaIntegralComValorParcialESinalDepoisDeOutroPagamento(): void
    {
        $model = $this->modelo();
        foreach ([$this->dados(5000, 'integral'), $this->dados(5000, 'quitacao')] as $dados) {
            try {
                $model->receber(1, $dados, 1);
                $this->fail('Integral e quitação precisam cobrir o saldo.');
            } catch (DomainException $e) {
                $this->assertSame(0, $model->recebido(1));
            }
        }
        $model->receber(1, $this->dados(5000, 'sinal'), 1);
        $this->expectException(DomainException::class);
        $model->receber(1, $this->dados(5000, 'sinal', 'b'), 1);
    }
}
