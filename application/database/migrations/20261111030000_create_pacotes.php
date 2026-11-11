<?php

class Migration_create_pacotes extends CI_Migration
{
    public function up()
    {
        if (! $this->db->table_exists('pacotes')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'auto_increment' => true],
                'os_id' => ['type' => 'INT'],
                'nome' => ['type' => 'VARCHAR', 'constraint' => 150],
                'chave' => ['type' => 'CHAR', 'constraint' => 32],
                'criado_em' => ['type' => 'DATETIME'],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->create_table('pacotes', true);
            $this->db->query('ALTER TABLE pacotes ADD UNIQUE KEY uk_pacote_os (os_id), ADD UNIQUE KEY uk_pacote_chave (chave), ADD CONSTRAINT fk_pacote_os FOREIGN KEY (os_id) REFERENCES os(idOs)');
        }
        if (! $this->db->table_exists('pacote_sessoes')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'auto_increment' => true],
                'pacote_id' => ['type' => 'INT'],
                'servicos_id' => ['type' => 'INT'],
                'nome_servico' => ['type' => 'VARCHAR', 'constraint' => 255],
                'duracao_min' => ['type' => 'INT'],
                'numero' => ['type' => 'INT'],
                'agenda_id' => ['type' => 'INT', 'null' => true],
                'realizado_em' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('pacote_id');
            $this->dbforge->create_table('pacote_sessoes', true);
            $this->db->query('ALTER TABLE pacote_sessoes ADD CONSTRAINT fk_sessao_pacote FOREIGN KEY (pacote_id) REFERENCES pacotes(id), ADD CONSTRAINT fk_sessao_servico FOREIGN KEY (servicos_id) REFERENCES servicos(idServicos), ADD CONSTRAINT fk_sessao_agenda FOREIGN KEY (agenda_id) REFERENCES agenda(id)');
        }
        if (! $this->db->field_exists('pacote_sessao_id', 'agenda')) {
            $this->dbforge->add_column('agenda', ['pacote_sessao_id' => ['type' => 'INT', 'null' => true]]);
            $this->db->query('ALTER TABLE agenda ADD CONSTRAINT fk_agenda_sessao FOREIGN KEY (pacote_sessao_id) REFERENCES pacote_sessoes(id)');
        }
    }

    public function down()
    {
        $this->db->query('ALTER TABLE agenda DROP FOREIGN KEY fk_agenda_sessao');
        $this->dbforge->drop_column('agenda', 'pacote_sessao_id');
        $this->dbforge->drop_table('pacote_sessoes', true);
        $this->dbforge->drop_table('pacotes', true);
    }
}
