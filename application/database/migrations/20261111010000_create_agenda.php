<?php

class Migration_create_agenda extends CI_Migration
{
    public function up()
    {
        if (! $this->db->field_exists('duracao_min', 'servicos')) {
            $this->dbforge->add_column('servicos', ['duracao_min' => ['type' => 'INT', 'default' => 0]]);
        }
        if (! $this->db->table_exists('agenda')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'auto_increment' => true],
                'os_id' => ['type' => 'INT', 'null' => true],
                'usuarios_id' => ['type' => 'INT'],
                'tipo' => ['type' => 'VARCHAR', 'constraint' => 20],
                'titulo' => ['type' => 'VARCHAR', 'constraint' => 255],
                'inicio' => ['type' => 'DATETIME'],
                'fim' => ['type' => 'DATETIME'],
                'ocupado_inicio' => ['type' => 'DATETIME'],
                'ocupado_fim' => ['type' => 'DATETIME'],
                'duracao_min' => ['type' => 'INT'],
                'deslocamento_min' => ['type' => 'INT', 'default' => 0],
                'intervalo_min' => ['type' => 'INT', 'default' => 0],
                'servicos_json' => ['type' => 'TEXT'],
                'cancelado' => ['type' => 'TINYINT', 'default' => 0],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key(['usuarios_id', 'ocupado_inicio']);
            $this->dbforge->create_table('agenda', true);
            $this->db->query('ALTER TABLE agenda ADD CONSTRAINT fk_agenda_os FOREIGN KEY (os_id) REFERENCES os(idOs) ON DELETE CASCADE');
            $this->db->query('ALTER TABLE agenda ADD CONSTRAINT fk_agenda_usuario FOREIGN KEY (usuarios_id) REFERENCES usuarios(idUsuarios)');
        }
    }

    public function down()
    {
        $this->dbforge->drop_table('agenda', true);
        $this->dbforge->drop_column('servicos', 'duracao_min');
    }
}
