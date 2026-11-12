<?php

require_once APPPATH . 'helpers/notas_fiscais_helper.php';
require_once APPPATH . 'models/Notas_fiscais_model.php';
require_once APPPATH . 'controllers/Notasfiscais.php';

final class NotasFiscaisTest extends MaposTestCase
{
    private function modelo(): Notas_fiscais_model
    {
        $this->db->query('CREATE TABLE os (idOs INTEGER PRIMARY KEY, clientes_id INTEGER, nf_solicitada INTEGER DEFAULT 0, faturado INTEGER, valorTotal NUMERIC, lancamento INTEGER)');
        $this->db->query('INSERT INTO os VALUES (1, 10, 0, 1, 230, 5), (2, 20, 0, 0, 450, NULL)');
        $this->db->query('CREATE TABLE notas_fiscais (id INTEGER PRIMARY KEY AUTOINCREMENT, os_id INTEGER, numero TEXT, data_emissao TEXT, nome TEXT, caminho TEXT, ativo INTEGER DEFAULT 1, usuarios_id INTEGER, criado_em TEXT)');
        $modelo = $this->makeInstance(Notas_fiscais_model::class);
        $this->setPrivateProperty($modelo, 'db', $this->db);
        return $modelo;
    }

    private function dados(int $os = 1): array
    {
        return ['os_id' => $os, 'numero' => 'DEMO-123', 'data_emissao' => '2026-11-12', 'nome' => 'nota-ficticia.pdf', 'caminho' => 'notas-fiscais/1/aleatorio.pdf', 'ativo' => 1, 'usuarios_id' => 1, 'criado_em' => '2026-11-12 10:00:00'];
    }

    public function testSolicitaEAnexaDepoisDaQuitacaoSemAlterarValores(): void
    {
        $modelo = $this->modelo();
        $this->assertTrue($modelo->solicitar(1, true));
        $this->assertTrue($modelo->registrar($this->dados()));
        $os = $this->db->where('idOs', 1)->get('os')->row();
        $this->assertSame(1, (int) $os->nf_solicitada);
        $this->assertSame(1, (int) $os->faturado);
        $this->assertSame(230.0, (float) $os->valorTotal);
        $this->assertSame(5, (int) $os->lancamento);
        $this->assertCount(1, $modelo->listar(1));
        $this->assertSame('NF anexada', notaFiscalStatus(true, $modelo->listar(1))['label']);
    }

    public function testClienteNaoAcessaNotaDeOutraCliente(): void
    {
        $modelo = $this->modelo();
        $modelo->registrar($this->dados());
        $id = (int) $modelo->listar(1)[0]->id;
        $this->assertNotNull($modelo->doCliente($id, 10));
        $this->assertNull($modelo->doCliente($id, 20));
        $this->assertNull($modelo->doCliente(999, 10));
    }

    public function testRetiradaRevogaLinkDoClienteEPodeSerRevertida(): void
    {
        $modelo = $this->modelo();
        $modelo->registrar($this->dados());
        $id = (int) $modelo->listar(1)[0]->id;
        $this->assertTrue($modelo->alterarDisponibilidade(1, $id, false));
        $this->assertSame([], $modelo->listar(1, true));
        $this->assertNull($modelo->doCliente($id, 10));
        $this->assertCount(1, $modelo->listar(1));
        $this->assertNotNull($modelo->arquivo($id));
        $this->assertSame('NF solicitada', notaFiscalStatus(true, $modelo->listar(1))['label']);
        $this->assertTrue($modelo->alterarDisponibilidade(1, $id, true));
        $this->assertNotNull($modelo->doCliente($id, 10));
    }

    public function testNaoAlteraNotaDeOutroAtendimentoNemExpoeCaminhosNaListagem(): void
    {
        $modelo = $this->modelo();
        $modelo->registrar($this->dados());
        $nota = $modelo->listar(1)[0];
        $this->assertObjectNotHasProperty('caminho', $nota);
        $this->assertFalse($modelo->alterarDisponibilidade(2, (int) $nota->id, false));
        $this->assertNotNull($modelo->doCliente((int) $nota->id, 10));
    }

    public function testNumeroEDataOpcionaisEValidacao(): void
    {
        $this->assertSame([['numero' => null, 'data_emissao' => null], []], notaFiscalDados([], '2026-11-12'));
        [, $erros] = notaFiscalDados(['numero' => str_repeat('A', 61), 'data_emissao' => '2026-11-13'], '2026-11-12');
        $this->assertArrayHasKey('numero', $erros);
        $this->assertArrayHasKey('data_emissao', $erros);
        [, $erros] = notaFiscalDados(['numero' => [], 'data_emissao' => '2026-02-30'], '2026-11-12');
        $this->assertArrayHasKey('numero', $erros);
        $this->assertArrayHasKey('data_emissao', $erros);
        $this->assertSame('NF não solicitada', notaFiscalStatus(false, [])['label']);
    }

    public function testRotasAdministrativasNegamSemPermissao(): void
    {
        foreach (['index', 'solicitar', 'anexar', 'disponibilidade', 'arquivo'] as $metodo) {
            $controller = new class extends Notasfiscais {
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
