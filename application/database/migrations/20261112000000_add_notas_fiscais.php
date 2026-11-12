<?php

class Migration_add_notas_fiscais extends CI_Migration
{
    public function up()
    {
        if (! $this->db->field_exists('nf_solicitada', 'os')) {
            $this->dbforge->add_column('os', ['nf_solicitada' => ['type' => 'TINYINT', 'default' => 0]]);
        }
        if (! $this->db->table_exists('notas_fiscais')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'auto_increment' => true],
                'os_id' => ['type' => 'INT'],
                'numero' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'data_emissao' => ['type' => 'DATE', 'null' => true],
                'nome' => ['type' => 'VARCHAR', 'constraint' => 255],
                'caminho' => ['type' => 'VARCHAR', 'constraint' => 255],
                'ativo' => ['type' => 'TINYINT', 'default' => 1],
                'usuarios_id' => ['type' => 'INT'],
                'criado_em' => ['type' => 'DATETIME'],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('os_id');
            $this->dbforge->create_table('notas_fiscais', true);
            $this->db->query('ALTER TABLE notas_fiscais ADD CONSTRAINT fk_nota_fiscal_os FOREIGN KEY (os_id) REFERENCES os(idOs)');
        }
    }

    public function down()
    {
        $this->dbforge->drop_table('notas_fiscais', true);
        $this->dbforge->drop_column('os', 'nf_solicitada');
    }
}
