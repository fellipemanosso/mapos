<?php

require_once APPPATH . 'helpers/dependentes_helper.php';
require_once APPPATH . 'models/Dependentes_model.php';

final class DependentesTest extends MaposTestCase
{
    public function testValidaNomeNascimentoEParentesco(): void
    {
        [$dados, $erros] = dependenteDados(['nome' => ' Helena ', 'nascimento' => '2026-06-10', 'parentesco' => 'filha'], '2026-11-11');
        $this->assertSame([], $erros);
        $this->assertSame('Helena', $dados['nome']);
        [, $erros] = dependenteDados(['nome' => '', 'nascimento' => '2026-02-30', 'parentesco' => ''], '2026-11-11');
        $this->assertCount(3, $erros);
        [, $erros] = dependenteDados(['nome' => 'Helena', 'nascimento' => '2027-01-01', 'parentesco' => 'filha'], '2026-11-11');
        $this->assertArrayHasKey('nascimento', $erros);
    }

    public function testDependenteNaoPodeSerLidoOuEditadoPorOutraResponsavel(): void
    {
        $this->db->query('CREATE TABLE dependentes (id INTEGER PRIMARY KEY AUTOINCREMENT, clientes_id INTEGER, nome TEXT, nascimento TEXT, parentesco TEXT)');
        $model = $this->makeInstance(Dependentes_model::class);
        $this->setPrivateProperty($model, 'db', $this->db);
        $this->assertTrue($model->salvar(1, null, ['nome' => 'Helena', 'nascimento' => null, 'parentesco' => 'filha']));
        $id = (int) $this->db->insert_id();
        $this->assertNull($model->doResponsavel($id, 2));
        $this->assertSame([], $model->doCliente(2));
        $model->salvar(2, $id, ['nome' => 'Alterado indevidamente']);
        $this->assertSame('Helena', $model->doResponsavel($id, 1)->nome);
        $model->salvar(1, $id, ['nome' => 'Helena Souza']);
        $this->assertSame('Helena Souza', $model->doResponsavel($id, 1)->nome);
    }
}
