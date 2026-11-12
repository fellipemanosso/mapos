<?php

class Migration_add_automacoes extends CI_Migration
{
    public function up()
    {
        $this->tabela('automacoes_config', ['dados_json' => ['type' => 'TEXT'], 'versao' => ['type' => 'INT', 'default' => 1],
            'atualizado_em' => ['type' => 'DATETIME'], 'ultimo_worker' => ['type' => 'DATETIME', 'null' => true]]);
        $this->db->insert('automacoes_config', ['id' => 1, 'dados_json' => '{}', 'atualizado_em' => date('Y-m-d H:i:s')]);
        $this->tabela('wa_conversas', ['telefone' => ['type' => 'VARCHAR', 'constraint' => 20], 'nome' => ['type' => 'VARCHAR', 'constraint' => 150],
            'lead_id' => ['type' => 'INT', 'null' => true], 'cliente_id' => ['type' => 'INT', 'null' => true],
            'modo' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'humano'], 'consentimento_ia' => ['type' => 'TINYINT', 'default' => 0],
            'lembretes' => ['type' => 'TINYINT', 'default' => 0], 'optout' => ['type' => 'TINYINT', 'default' => 0],
            'atualizado_em' => ['type' => 'DATETIME'], 'consentimento_em' => ['type' => 'DATETIME', 'null' => true]]);
        $this->db->query('ALTER TABLE wa_conversas ADD UNIQUE KEY uk_wa_telefone (telefone)');
        $this->tabela('wa_mensagens', ['conversa_id' => ['type' => 'INT'], 'direcao' => ['type' => 'VARCHAR', 'constraint' => 10],
            'tipo' => ['type' => 'VARCHAR', 'constraint' => 25], 'texto' => ['type' => 'TEXT'], 'estado' => ['type' => 'VARCHAR', 'constraint' => 20],
            'chave' => ['type' => 'CHAR', 'constraint' => 64], 'externa' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'contexto_id' => ['type' => 'INT', 'null' => true], 'contexto_versao' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'criado_em' => ['type' => 'DATETIME'], 'processado_em' => ['type' => 'DATETIME', 'null' => true],
            'erro' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => '']]);
        $this->db->query('ALTER TABLE wa_mensagens ADD UNIQUE KEY uk_wa_chave (chave)');
        $this->db->query('ALTER TABLE wa_mensagens ADD INDEX idx_wa_fila (estado, criado_em)');
        $this->db->query('ALTER TABLE wa_mensagens ADD CONSTRAINT fk_wa_conversa FOREIGN KEY (conversa_id) REFERENCES wa_conversas(id) ON DELETE RESTRICT');
        $grupo = $this->db->where('idPermissao', 1)->get('permissoes')->row();
        if ($grupo && is_array($permissoes = json_decode_legacy($grupo->permissoes))) {
            $this->db->where('idPermissao', 1)->update('permissoes', ['permissoes' => json_encode($permissoes + ['vWhatsapp' => 1, 'eWhatsapp' => 1])]);
        }
    }

    private function tabela(string $nome, array $campos): void
    {
        $this->dbforge->add_field(['id' => ['type' => 'INT', 'auto_increment' => true]] + $campos);
        $this->dbforge->add_key('id', true);
        $this->dbforge->create_table($nome, true);
    }

    public function down()
    {
        foreach (['wa_mensagens', 'wa_conversas', 'automacoes_config'] as $tabela) {
            $this->dbforge->drop_table($tabela, true);
        }
    }
}
