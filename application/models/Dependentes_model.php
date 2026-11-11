<?php

class Dependentes_model extends CI_Model
{
    public function doCliente(int $clienteId): array
    {
        return $this->db->where('clientes_id', $clienteId)->order_by('nome', 'asc')->get('dependentes')->result();
    }

    public function doResponsavel(int $id, int $clienteId): ?object
    {
        return $this->db->where('id', $id)->where('clientes_id', $clienteId)->get('dependentes')->row();
    }

    public function salvar(int $clienteId, ?int $id, array $dados): bool
    {
        if ($id !== null) {
            return $this->db->where('id', $id)->where('clientes_id', $clienteId)->update('dependentes', $dados);
        }

        return $this->db->insert('dependentes', $dados + ['clientes_id' => $clienteId]);
    }
}
