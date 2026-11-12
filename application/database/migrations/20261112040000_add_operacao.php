<?php

class Migration_add_operacao extends CI_Migration
{
    public function up()
    {
        $grupo = $this->db->where('idPermissao', 1)->get('permissoes')->row();
        if ($grupo) {
            $permissoes = json_decode_legacy($grupo->permissoes);
            if (is_array($permissoes)) {
                foreach (['vFicha', 'eFicha', 'cModeloFicha', 'rFicha', 'vInsumo', 'eInsumo'] as $codigo) {
                    $permissoes[$codigo] = 1;
                }
                $this->db->where('idPermissao', 1)->update('permissoes', ['permissoes' => json_encode($permissoes)]);
            }
        }
        $this->tabela('insumos', ['nome' => ['type' => 'VARCHAR', 'constraint' => 150], 'unidade' => ['type' => 'VARCHAR', 'constraint' => 10],
            'minimo' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0]]);
        $this->tabela('insumo_lotes', ['insumo_id' => ['type' => 'INT'], 'lote' => ['type' => 'VARCHAR', 'constraint' => 100],
            'validade' => ['type' => 'DATE', 'null' => true], 'saldo' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'custo_unitario' => ['type' => 'DECIMAL', 'constraint' => '14,6'], 'criado_em' => ['type' => 'DATETIME']]);
        $this->tabela('insumo_movimentos', ['lote_id' => ['type' => 'INT'], 'os_id' => ['type' => 'INT', 'null' => true],
            'tipo' => ['type' => 'VARCHAR', 'constraint' => 20], 'quantidade' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'custo' => ['type' => 'DECIMAL', 'constraint' => '12,2'], 'nota' => ['type' => 'VARCHAR', 'constraint' => 255],
            'usuarios_id' => ['type' => 'INT'], 'criado_em' => ['type' => 'DATETIME'], 'estornado_em' => ['type' => 'DATETIME', 'null' => true],
            'estornado_por' => ['type' => 'INT', 'null' => true], 'chave' => ['type' => 'CHAR', 'constraint' => 32]]);
        $this->db->query('ALTER TABLE insumo_movimentos ADD UNIQUE KEY uk_insumo_movimento (chave)');
        foreach (['insumo_lotes' => ['insumo_id' => 'insumos(id)'], 'insumo_movimentos' => ['lote_id' => 'insumo_lotes(id)', 'os_id' => 'os(idOs)']] as $tabela => $relacoes) {
            foreach ($relacoes as $campo => $destino) {
                $this->db->query('ALTER TABLE ' . $tabela . ' ADD CONSTRAINT fk_' . $tabela . '_' . $campo . ' FOREIGN KEY (' . $campo . ') REFERENCES ' . $destino . ' ON DELETE RESTRICT');
            }
        }
        $this->dbforge->add_column('os', ['endereco_atendimento' => ['type' => 'VARCHAR', 'constraint' => 500, 'default' => ''],
            'distancia_km' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => 0],
            'deslocamento_min' => ['type' => 'INT', 'default' => 0], 'regra_deslocamento' => ['type' => 'VARCHAR', 'constraint' => 500, 'default' => '']]);
    }

    private function tabela(string $nome, array $campos): void
    {
        $this->dbforge->add_field(['id' => ['type' => 'INT', 'auto_increment' => true]] + $campos);
        $this->dbforge->add_key('id', true);
        $this->dbforge->create_table($nome, true);
    }

    public function down()
    {
        foreach (['insumo_movimentos', 'insumo_lotes', 'insumos'] as $tabela) {
            $this->dbforge->drop_table($tabela, true);
        }
        $this->dbforge->drop_column('os', ['endereco_atendimento', 'distancia_km', 'deslocamento_min', 'regra_deslocamento']);
    }
}
