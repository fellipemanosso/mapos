<?php

class Migration_add_dependentes extends CI_Migration
{
    public function up()
    {
        if (! $this->db->table_exists('dependentes')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'clientes_id' => ['type' => 'INT', 'constraint' => 11],
                'nome' => ['type' => 'VARCHAR', 'constraint' => 255],
                'nascimento' => ['type' => 'DATE', 'null' => true],
                'parentesco' => ['type' => 'VARCHAR', 'constraint' => 45],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('clientes_id');
            $this->dbforge->create_table('dependentes');
            $this->db->query('ALTER TABLE dependentes ADD CONSTRAINT fk_dependente_cliente FOREIGN KEY (clientes_id) REFERENCES clientes(idClientes) ON DELETE CASCADE');
        }
        if (! $this->db->field_exists('dependente_id', 'os')) {
            $this->dbforge->add_column('os', ['dependente_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true]]);
            $this->db->query('ALTER TABLE os ADD CONSTRAINT fk_os_dependente FOREIGN KEY (dependente_id) REFERENCES dependentes(id) ON DELETE RESTRICT');
        }
        if (! $this->db->field_exists('dependente_id', 'os')) {
            throw new RuntimeException('Não foi possível atualizar os atendimentos.');
        }
    }

    public function down()
    {
        $this->db->query('ALTER TABLE os DROP FOREIGN KEY fk_os_dependente');
        $this->dbforge->drop_column('os', 'dependente_id');
        $this->dbforge->drop_table('dependentes');
    }
}
