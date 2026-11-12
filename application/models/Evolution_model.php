<?php

class Evolution_model extends CI_Model
{
    public function configuracao(): ?object
    {
        return $this->db->select('id, url, instancia, chave_cifrada')->where('id', 1)->get('evolution_config')->row();
    }

    public function salvar(string $url, string $instancia, string $chaveCifrada): bool
    {
        $dados = ['url' => $url, 'instancia' => $instancia, 'chave_cifrada' => $chaveCifrada];
        return $this->configuracao()
            ? $this->db->where('id', 1)->update('evolution_config', $dados)
            : $this->db->insert('evolution_config', ['id' => 1] + $dados);
    }
}
