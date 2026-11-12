<?php

class Notas_fiscais_model extends CI_Model
{
    public function listar(int $os, bool $somenteAtivas = false): array
    {
        $this->db->select('id, os_id, numero, data_emissao, nome, ativo, criado_em')->where('os_id', $os);
        if ($somenteAtivas) {
            $this->db->where('ativo', 1);
        }
        return $this->db->order_by('id', 'desc')->get('notas_fiscais')->result();
    }

    public function arquivo(int $id): ?object
    {
        return $this->db->where('id', $id)->get('notas_fiscais')->row();
    }

    public function doCliente(int $id, int $cliente): ?object
    {
        return $this->db->select('nf.*')->from('notas_fiscais nf')->join('os', 'os.idOs = nf.os_id')
            ->where('nf.id', $id)->where('nf.ativo', 1)->where('os.clientes_id', $cliente)->get()->row();
    }

    /** Só o campo fiscal é atualizado; pagamentos e valores do atendimento são preservados. */
    public function solicitar(int $os, bool $solicitada): bool
    {
        return $this->db->where('idOs', $os)->update('os', ['nf_solicitada' => $solicitada ? 1 : 0]);
    }

    public function registrar(array $dados): bool
    {
        return $this->db->insert('notas_fiscais', $dados);
    }

    public function alterarDisponibilidade(int $os, int $id, bool $ativo): bool
    {
        $nota = $this->db->where('id', $id)->where('os_id', $os)->get('notas_fiscais')->row();
        if (! $nota) {
            return false;
        }
        return $this->db->where('id', $id)->where('os_id', $os)->update('notas_fiscais', ['ativo' => $ativo ? 1 : 0]);
    }
}
