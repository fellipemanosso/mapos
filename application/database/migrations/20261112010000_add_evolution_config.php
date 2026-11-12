<?php

class Migration_add_evolution_config extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_field([
            'id' => ['type' => 'INT'],
            'url' => ['type' => 'VARCHAR', 'constraint' => 500],
            'instancia' => ['type' => 'VARCHAR', 'constraint' => 100],
            'chave_cifrada' => ['type' => 'TEXT'],
        ]);
        $this->dbforge->add_key('id', true);
        $this->dbforge->create_table('evolution_config', true);
    }

    public function down()
    {
        $this->dbforge->drop_table('evolution_config', true);
    }
}
