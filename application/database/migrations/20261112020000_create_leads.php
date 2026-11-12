<?php

class Migration_create_leads extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_field([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'nome' => ['type' => 'VARCHAR', 'constraint' => 150],
            'telefone' => ['type' => 'VARCHAR', 'constraint' => 20],
            'bebe' => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'origem' => ['type' => 'VARCHAR', 'constraint' => 30],
            'etapa' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'novo'],
            'interesse' => ['type' => 'VARCHAR', 'constraint' => 255],
            'observacoes' => ['type' => 'TEXT'],
            'condicoes' => ['type' => 'TEXT'],
            'itens_json' => ['type' => 'TEXT'],
            'total' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'sinal' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'validade' => ['type' => 'DATE', 'null' => true],
            'retorno' => ['type' => 'DATE', 'null' => true],
            'motivo_perda' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'os_id' => ['type' => 'INT', 'null' => true],
            'chave' => ['type' => 'CHAR', 'constraint' => 32],
            'versao' => ['type' => 'INT', 'default' => 1],
            'criado_em' => ['type' => 'DATETIME'],
        ]);
        $this->dbforge->add_key('id', true);
        $this->dbforge->add_key('etapa');
        $this->dbforge->add_key('retorno');
        $this->dbforge->create_table('leads', true);
        $this->db->query('ALTER TABLE leads ADD UNIQUE KEY uk_lead_chave (chave)');
        $this->db->query('ALTER TABLE leads ADD UNIQUE KEY uk_lead_os (os_id)');
        $this->db->query('ALTER TABLE leads ADD CONSTRAINT fk_lead_os FOREIGN KEY (os_id) REFERENCES os(idOs) ON DELETE RESTRICT');
    }

    public function down()
    {
        $this->dbforge->drop_table('leads', true);
    }
}
