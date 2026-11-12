<?php

class Migration_add_custos_atendimento extends CI_Migration
{
    public function up()
    {
        $this->tabela('atendimento_custos', ['os_id' => ['type' => 'INT'], 'tipo' => ['type' => 'VARCHAR', 'constraint' => 20],
            'descricao' => ['type' => 'VARCHAR', 'constraint' => 150], 'valor' => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'usuarios_id' => ['type' => 'INT'], 'criado_em' => ['type' => 'DATETIME'],
            'estornado_em' => ['type' => 'DATETIME', 'null' => true], 'estornado_por' => ['type' => 'INT', 'null' => true],
            'chave' => ['type' => 'CHAR', 'constraint' => 32]]);
        $this->db->query('ALTER TABLE atendimento_custos ADD UNIQUE KEY uk_custo_chave (chave)');
        $this->db->query('ALTER TABLE atendimento_custos ADD INDEX idx_custo_os (os_id)');
        $this->tabela('servico_insumos', ['servico_id' => ['type' => 'INT'], 'insumo_id' => ['type' => 'INT'], 'quantidade' => ['type' => 'DECIMAL', 'constraint' => '12,3']]);
        $this->db->query('ALTER TABLE servico_insumos ADD UNIQUE KEY uk_servico_insumo (servico_id, insumo_id)');
        $this->tabela('atendimento_insumos_padrao', ['os_id' => ['type' => 'INT'], 'item_id' => ['type' => 'INT'], 'criado_em' => ['type' => 'DATETIME']]);
        $this->db->query('ALTER TABLE atendimento_insumos_padrao ADD UNIQUE KEY uk_padrao_item (os_id, item_id)');
    }

    private function tabela(string $nome, array $campos): void
    {
        $this->dbforge->add_field(['id' => ['type' => 'INT', 'auto_increment' => true]] + $campos);
        $this->dbforge->add_key('id', true);
        $this->dbforge->create_table($nome, true);
    }

    public function down()
    {
        foreach (['atendimento_insumos_padrao', 'servico_insumos', 'atendimento_custos'] as $tabela) {
            $this->dbforge->drop_table($tabela, true);
        }
    }
}
