<?php

class Migration_add_recebimentos_atendimentos extends CI_Migration
{
    public function up()
    {
        if (! $this->db->field_exists('sinal_previsto', 'os')) {
            $this->dbforge->add_column('os', [
                'sinal_previsto' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
                'saldo_vencimento' => ['type' => 'DATE', 'null' => true],
                'financeiro_confirmado' => ['type' => 'TINYINT', 'default' => 0],
            ]);
        }
        if (! $this->db->field_exists('atendimento_id', 'lancamentos')) {
            $this->dbforge->add_column('lancamentos', [
                'atendimento_id' => ['type' => 'INT', 'null' => true],
                'recebimento_tipo' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'recebimento_valor' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'chave_operacao' => ['type' => 'CHAR', 'constraint' => 32, 'null' => true],
                'estornado_em' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->db->query('ALTER TABLE lancamentos ADD UNIQUE KEY uk_recebimento_operacao (chave_operacao)');
            $this->db->query('ALTER TABLE lancamentos ADD CONSTRAINT fk_recebimento_atendimento FOREIGN KEY (atendimento_id) REFERENCES os(idOs)');
        }
    }

    public function down()
    {
        $this->db->query('ALTER TABLE lancamentos DROP FOREIGN KEY fk_recebimento_atendimento');
        $this->db->query('ALTER TABLE lancamentos DROP INDEX uk_recebimento_operacao');
        $this->dbforge->drop_column('lancamentos', ['atendimento_id', 'recebimento_tipo', 'recebimento_valor', 'chave_operacao', 'estornado_em']);
        $this->dbforge->drop_column('os', ['sinal_previsto', 'saldo_vencimento', 'financeiro_confirmado']);
    }
}
