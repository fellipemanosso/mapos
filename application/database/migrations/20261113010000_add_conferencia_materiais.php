<?php

class Migration_add_conferencia_materiais extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_field(['os_id' => ['type' => 'INT'], 'assinatura' => ['type' => 'CHAR', 'constraint' => 64],
            'usuarios_id' => ['type' => 'INT'], 'criado_em' => ['type' => 'DATETIME']]);
        $this->dbforge->add_key('os_id', true);
        $this->dbforge->create_table('atendimento_conferencia', true);
    }

    public function down()
    {
        $this->dbforge->drop_table('atendimento_conferencia', true);
    }
}
