<?php

class Migration_create_fichas extends CI_Migration
{
    public function up()
    {
        $this->tabela('ficha_modelos', [
            'nome' => ['type' => 'VARCHAR', 'constraint' => 150],
            'campos_json' => ['type' => 'TEXT'], 'termo' => ['type' => 'TEXT'],
            'versao' => ['type' => 'INT', 'default' => 1],
        ]);
        if ($this->db->count_all('ficha_modelos') === 0) {
            foreach (['Amamentação', 'Perfuração de orelha', 'Laserterapia', 'Doula', 'Botox', 'Atendimento geral'] as $nome) {
                $this->db->insert('ficha_modelos', ['nome' => $nome, 'campos_json' => json_encode(['Motivo do atendimento', 'Histórico informado', 'Avaliação da profissional', 'Informações adicionais'], JSON_UNESCAPED_UNICODE), 'termo' => '']);
            }
        }
        $this->tabela('fichas', [
            'os_id' => ['type' => 'INT'], 'clientes_id' => ['type' => 'INT'],
            'dependente_id' => ['type' => 'INT', 'null' => true], 'servicos_id' => ['type' => 'INT'],
            'paciente_nome' => ['type' => 'VARCHAR', 'constraint' => 255],
            'responsavel_nome' => ['type' => 'VARCHAR', 'constraint' => 255],
            'servico_nome' => ['type' => 'VARCHAR', 'constraint' => 150],
            'modelo_nome' => ['type' => 'VARCHAR', 'constraint' => 150],
            'campos_json' => ['type' => 'TEXT'], 'respostas_json' => ['type' => 'MEDIUMTEXT'], 'termo' => ['type' => 'TEXT'],
            'usuarios_id' => ['type' => 'INT'], 'criado_em' => ['type' => 'DATETIME'],
            'chave' => ['type' => 'CHAR', 'constraint' => 32],
        ]);
        $this->tabela('ficha_evolucoes', [
            'ficha_id' => ['type' => 'INT'], 'agenda_id' => ['type' => 'INT', 'null' => true],
            'ocorrido_em' => ['type' => 'DATETIME'], 'avaliacao' => ['type' => 'TEXT'],
            'procedimento' => ['type' => 'TEXT'], 'orientacoes' => ['type' => 'TEXT'], 'observacoes' => ['type' => 'TEXT'],
            'retorno' => ['type' => 'DATE', 'null' => true],
            'retorno_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pendente'],
            'retorno_historico' => ['type' => 'MEDIUMTEXT'],
            'usuarios_id' => ['type' => 'INT'], 'criado_em' => ['type' => 'DATETIME'],
            'chave' => ['type' => 'CHAR', 'constraint' => 32],
        ]);
        $this->tabela('ficha_arquivos', [
            'ficha_id' => ['type' => 'INT'], 'tipo' => ['type' => 'VARCHAR', 'constraint' => 30],
            'descricao' => ['type' => 'VARCHAR', 'constraint' => 255],
            'signatario' => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'assinado_em' => ['type' => 'DATE', 'null' => true],
            'nome' => ['type' => 'VARCHAR', 'constraint' => 255], 'caminho' => ['type' => 'VARCHAR', 'constraint' => 255],
            'usuarios_id' => ['type' => 'INT'], 'criado_em' => ['type' => 'DATETIME'],
        ]);
        $this->db->query('ALTER TABLE fichas ADD UNIQUE KEY uk_ficha_chave (chave)');
        $this->db->query('ALTER TABLE ficha_evolucoes ADD UNIQUE KEY uk_evolucao_chave (chave)');
        $this->db->query('ALTER TABLE fichas ADD INDEX idx_ficha_paciente (clientes_id, dependente_id)');
        $this->db->query('ALTER TABLE ficha_evolucoes ADD INDEX idx_ficha_retorno (retorno_status, retorno)');
        foreach (['fichas' => ['os_id' => 'os(idOs)', 'clientes_id' => 'clientes(idClientes)', 'dependente_id' => 'dependentes(id)', 'servicos_id' => 'servicos(idServicos)'],
            'ficha_evolucoes' => ['ficha_id' => 'fichas(id)'], 'ficha_arquivos' => ['ficha_id' => 'fichas(id)']] as $tabela => $relacoes) {
            foreach ($relacoes as $campo => $destino) {
                $this->db->query('ALTER TABLE ' . $tabela . ' ADD CONSTRAINT fk_' . $tabela . '_' . $campo . ' FOREIGN KEY (' . $campo . ') REFERENCES ' . $destino . ' ON DELETE RESTRICT');
            }
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
        foreach (['ficha_arquivos', 'ficha_evolucoes', 'fichas', 'ficha_modelos'] as $tabela) {
            $this->dbforge->drop_table($tabela, true);
        }
    }
}
