<?php

require_once APPPATH . 'helpers/leads_helper.php';
require_once APPPATH . 'helpers/financeiro_helper.php';
require_once APPPATH . 'helpers/status_helper.php';
require_once APPPATH . 'models/Leads_model.php';
require_once APPPATH . 'core/MY_Controller.php';
require_once APPPATH . 'controllers/Leads.php';

final class LeadsTest extends MaposTestCase
{
    private function modelo(): Leads_model
    {
        $this->db->query('CREATE TABLE leads (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, telefone TEXT, bebe TEXT, origem TEXT, etapa TEXT, interesse TEXT, observacoes TEXT, condicoes TEXT, itens_json TEXT, total NUMERIC, sinal NUMERIC, validade TEXT, retorno TEXT, motivo_perda TEXT DEFAULT "", os_id INTEGER UNIQUE, chave TEXT UNIQUE, versao INTEGER DEFAULT 1, criado_em TEXT)');
        $this->db->query('CREATE TABLE clientes (idClientes INTEGER PRIMARY KEY AUTOINCREMENT, nomeCliente TEXT, telefone TEXT, celular TEXT, documento TEXT, email TEXT, senha TEXT, dataCadastro TEXT, fornecedor INTEGER DEFAULT 0)');
        $this->db->query('CREATE TABLE dependentes (id INTEGER PRIMARY KEY AUTOINCREMENT, clientes_id INTEGER, nome TEXT, parentesco TEXT)');
        $this->db->query('CREATE TABLE usuarios (idUsuarios INTEGER PRIMARY KEY, situacao INTEGER)');
        $this->db->query('INSERT INTO usuarios VALUES (1, 1), (2, 0)');
        $this->db->query('CREATE TABLE servicos (idServicos INTEGER PRIMARY KEY, nome TEXT, preco NUMERIC)');
        $this->db->query("INSERT INTO servicos VALUES (1, 'Perfuração', 200), (2, 'Taxa de deslocamento', 30)");
        $this->db->query('CREATE TABLE os (idOs INTEGER PRIMARY KEY AUTOINCREMENT, clientes_id INTEGER, dependente_id INTEGER, usuarios_id INTEGER, status TEXT, dataInicial TEXT, faturado INTEGER, valorTotal NUMERIC, sinal_previsto NUMERIC, descricaoProduto TEXT, observacoes TEXT)');
        $this->db->query('CREATE TABLE servicos_os (idServicos_os INTEGER PRIMARY KEY AUTOINCREMENT, os_id INTEGER, servicos_id INTEGER, servico TEXT, quantidade INTEGER, preco NUMERIC, subTotal NUMERIC)');
        $modelo = $this->makeInstance(Leads_model::class);
        $this->setPrivateProperty($modelo, 'db', $this->db);
        return $modelo;
    }

    private function dados(array $extras = []): array
    {
        [$dados, $erros] = leadFormulario(array_replace([
            'nome' => 'Ana', 'telefone' => '(11) 99999-0000', 'bebe' => 'Helena', 'interesse' => 'Perfuração de orelha', 'origem' => 'whatsapp',
            'sinal' => '50,00', 'servicos' => [1 => ['quantidade' => '1', 'preco' => '200,00'], 2 => ['quantidade' => '1', 'preco' => '30,00']],
        ], $extras), [(object) ['idServicos' => 1, 'nome' => 'Perfuração'], (object) ['idServicos' => 2, 'nome' => 'Taxa de deslocamento']]);
        $this->assertSame([], $erros);
        return $dados;
    }

    public function testCalculaOrcamentoComDeslocamentoESinalEmCentavos(): void
    {
        $dados = $this->dados(['total' => '0.01']);
        $this->assertSame('230.00', $dados['total']);
        $this->assertSame('50.00', $dados['sinal']);
        $this->assertSame('5511999990000', $dados['telefone']);
        $this->assertSame(2, count(json_decode($dados['itens_json'], true)));
        [, $erros] = leadFormulario(['nome' => [], 'telefone' => '123', 'origem' => [], 'interesse' => '', 'sinal' => '999', 'validade' => '2026-02-30', 'servicos' => [999 => ['quantidade' => '1', 'preco' => '10']]], []);
        foreach (['nome', 'telefone', 'origem', 'interesse', 'sinal', 'validade', '_geral'] as $campo) {
            $this->assertArrayHasKey($campo, $erros);
        }
    }

    public function testEdicaoAntigaNaoSobrescreveOrcamentoAtual(): void
    {
        $modelo = $this->modelo();
        $dados = $this->dados();
        $id = $modelo->salvar(null, $dados, str_repeat('a', 32), 0);
        $atualizado = $this->dados(['nome' => 'Ana revisada']);
        $modelo->salvar($id, $atualizado, str_repeat('a', 32), 1);
        try {
            $modelo->salvar($id, $dados, str_repeat('a', 32), 1);
            $this->fail('Edição antiga deveria ser recusada.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('alterado', $e->getMessage());
        }
        $this->assertSame('Ana revisada', $modelo->getById($id)->nome);
        $this->assertSame(2, (int) $modelo->getById($id)->versao);
    }

    public function testConverteUmaVezMantemBebeEServicosESemInventarPagamentoOuAgenda(): void
    {
        $modelo = $this->modelo();
        $dados = $this->dados();
        $id = $modelo->salvar(null, $dados, str_repeat('a', 32), 0);
        $this->assertSame($id, $modelo->salvar(null, $dados, str_repeat('a', 32), 0));
        $os = $modelo->converter($id, 0, 1, 1);
        $this->assertSame($os, $modelo->converter($id, 0, 1, 1));
        $this->assertSame(1, $this->db->count_all('os'));
        $this->assertSame(1, $this->db->count_all('clientes'));
        $this->assertSame('convertido', $modelo->getById($id)->etapa);
        $registro = $this->db->get('os')->row();
        $this->assertSame(0, (int) $registro->faturado);
        $this->assertSame(230.0, (float) $registro->valorTotal);
        $this->assertSame(50.0, (float) $registro->sinal_previsto);
        $this->assertSame(230.0, (float) $this->db->select_sum('subTotal')->get('servicos_os')->row()->subTotal);
        $bebe = $this->db->get('dependentes')->row();
        $this->assertSame('Helena', $bebe->nome);
        $this->assertSame((int) $registro->clientes_id, (int) $bebe->clientes_id);
        $this->expectException(DomainException::class);
        $modelo->salvar($id, $dados, str_repeat('a', 32), 2);
    }

    public function testEvitaDuplicarClienteEReaproveitaBebeDoResponsavelEscolhido(): void
    {
        $modelo = $this->modelo();
        $this->db->insert('clientes', ['idClientes' => 10, 'nomeCliente' => 'Ana existente', 'telefone' => '(11) 99999-0000']);
        $this->db->insert('dependentes', ['id' => 20, 'clientes_id' => 10, 'nome' => 'Helena']);
        $id = $modelo->salvar(null, $this->dados(), str_repeat('a', 32), 0);
        $this->assertCount(1, $modelo->candidatas('5511999990000'));
        try {
            $modelo->converter($id, 0, 1, 1);
            $this->fail('Deveria exigir seleção do cadastro.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('telefone', $e->getMessage());
        }
        $this->assertSame(0, $this->db->count_all('os'));
        $modelo->converter($id, 10, 1, 1);
        $this->assertSame(1, $this->db->count_all('dependentes'));
        $this->assertSame(20, (int) $this->db->get('os')->row()->dependente_id);
    }

    public function testImpedeConversaoComVersaoAntigaProfissionalInativaOuClienteInvalida(): void
    {
        $modelo = $this->modelo();
        $id = $modelo->salvar(null, $this->dados(), str_repeat('a', 32), 0);
        foreach ([[0, 1, 0], [0, 2, 1], [999, 1, 1]] as [$cliente, $profissional, $versao]) {
            try {
                $modelo->converter($id, $cliente, $profissional, $versao);
                $this->fail('Conversão inválida deveria ser recusada.');
            } catch (DomainException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertSame(0, $this->db->count_all('os'));
        $this->assertSame(0, $this->db->count_all('clientes'));
    }

    public function testRecusaPerdidoEVencidoEMantemDadosAoFalhar(): void
    {
        $modelo = $this->modelo();
        $id = $modelo->salvar(null, $this->dados(['validade' => '2020-01-01']), str_repeat('a', 32), 0);
        try {
            $modelo->converter($id, 0, 1, 1);
            $this->fail('Orçamento vencido.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('venceu', $e->getMessage());
        }
        $modelo->etapa($id, 'perdido', 'Sem disponibilidade', 1);
        $this->assertSame('Sem disponibilidade', $modelo->getById($id)->motivo_perda);
        $this->expectException(DomainException::class);
        $modelo->converter($id, 0, 1, 2);
    }

    public function testRollbackSeServicoForRemovidoDepoisDoOrcamento(): void
    {
        $modelo = $this->modelo();
        $id = $modelo->salvar(null, $this->dados(), str_repeat('a', 32), 0);
        $this->db->where('idServicos', 1)->delete('servicos');
        try {
            $modelo->converter($id, 0, 1, 1);
            $this->fail('Serviço removido deveria impedir conversão.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('serviço', $e->getMessage());
        }
        $this->assertSame(0, $this->db->count_all('clientes'));
        $this->assertSame(0, $this->db->count_all('dependentes'));
        $this->assertNull($modelo->getById($id)->os_id);
    }

    public function testFiltroRetornosExcluiPerdidosEConvertidos(): void
    {
        $modelo = $this->modelo();
        $id = $modelo->salvar(null, $this->dados(['retorno' => date('Y-m-d')]), str_repeat('a', 32), 0);
        $this->assertSame(1, $modelo->contar(['retornos' => 'hoje']));
        $this->assertCount(1, $modelo->listar(['pesquisa' => 'Helena'], 0, 20));
        $this->assertSame(0, $modelo->contar(['pesquisa' => "' OR 1=1 --"]));
        $modelo->etapa($id, 'perdido', 'Outro motivo', 1);
        $this->assertSame(0, $modelo->contar(['retornos' => 'hoje']));
        $this->assertSame(1, $modelo->contar(['etapa' => 'perdido']));
    }

    public function testContatoSemOrcamentoNaoPodeSerMarcadoEnviadoNemConvertido(): void
    {
        $modelo = $this->modelo();
        $id = $modelo->salvar(null, $this->dados(['servicos' => [], 'sinal' => '0']), str_repeat('a', 32), 0);
        foreach (['enviado', 'sinal', 'convertido', 'invalido'] as $etapa) {
            try {
                $modelo->etapa($id, $etapa, '', 1);
                $this->fail('Etapa inválida deveria ser negada.');
            } catch (DomainException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->expectException(DomainException::class);
        $modelo->converter($id, 0, 1, 1);
    }

    public function testRotasNegamSemPermissao(): void
    {
        foreach (['index', 'visualizar', 'imprimir', 'etapa', 'formulario', 'converter'] as $metodo) {
            $controller = new class extends Leads {
                public $session;
                public $permission;
                public function __construct()
                {
                    $this->session = new class {
                        public function userdata($campo)
                        {
                            return 1;
                        }
                    };
                    $this->permission = new class {
                        public function checkPermission($p, $c)
                        {
                            return false;
                        }
                    };
                }
                protected function hasAnyPermission(array $permissoes): bool
                {
                    return false;
                }
            };
            try {
                $controller->{$metodo}();
                $this->fail('Deveria negar sem permissão.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Sem permissão', $e->getMessage());
            }
        }
    }
}
