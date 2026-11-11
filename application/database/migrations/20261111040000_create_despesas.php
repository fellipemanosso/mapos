<?php

class Migration_create_despesas extends CI_Migration
{
    public function up()
    {
        if (! $this->db->table_exists('despesa_grupos')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'auto_increment' => true],
                'chave' => ['type' => 'CHAR', 'constraint' => 32],
                'descricao' => ['type' => 'VARCHAR', 'constraint' => 255],
                'categoria' => ['type' => 'VARCHAR', 'constraint' => 40],
                'modo' => ['type' => 'VARCHAR', 'constraint' => 20],
                'frequencia' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'quantidade' => ['type' => 'INT'],
                'total_original' => ['type' => 'DECIMAL', 'constraint' => '14,2'],
                'criado_em' => ['type' => 'DATETIME'],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->create_table('despesa_grupos', true);
            $this->db->query('ALTER TABLE despesa_grupos ADD UNIQUE KEY uk_despesa_chave (chave)');
        }
        if (! $this->db->field_exists('despesa_grupo_id', 'lancamentos')) {
            $this->dbforge->add_column('lancamentos', ['despesa_grupo_id' => ['type' => 'INT', 'null' => true]]);
            $this->db->query('ALTER TABLE lancamentos ADD CONSTRAINT fk_lancamento_despesa_grupo FOREIGN KEY (despesa_grupo_id) REFERENCES despesa_grupos(id)');
        }
        if (! $this->db->table_exists('despesa_anexos')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'auto_increment' => true],
                'grupo_id' => ['type' => 'INT'],
                'nome' => ['type' => 'VARCHAR', 'constraint' => 255],
                'caminho' => ['type' => 'VARCHAR', 'constraint' => 255],
                'criado_em' => ['type' => 'DATETIME'],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->create_table('despesa_anexos', true);
            $this->db->query('ALTER TABLE despesa_anexos ADD CONSTRAINT fk_despesa_anexo_grupo FOREIGN KEY (grupo_id) REFERENCES despesa_grupos(id)');
        }
    }

    public function down()
    {
        $this->db->query('ALTER TABLE lancamentos DROP FOREIGN KEY fk_lancamento_despesa_grupo');
        $this->dbforge->drop_column('lancamentos', 'despesa_grupo_id');
        $this->dbforge->drop_table('despesa_anexos', true);
        $this->dbforge->drop_table('despesa_grupos', true);
    }
}
