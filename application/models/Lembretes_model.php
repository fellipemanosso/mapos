<?php

/** Fontes e validação tardia: uma mensagem antiga não sobrevive a remarcação, quitação ou cancelamento. */
class Lembretes_model extends CI_Model
{
    public function candidatos(string $agora): array
    {
        $fim = date('Y-m-d H:i:s', strtotime($agora) + 48 * 3600);
        $agenda = $this->db->select('a.id, a.inicio, a.fim, os.clientes_id, os.idOs')->from('agenda a')->join('os', 'os.idOs = a.os_id')
            ->where('a.cancelado', 0)->where('os.status !=', 'Cancelado')->where('a.inicio >', $agora)->where('a.inicio <=', $fim)
            ->order_by('a.inicio')->limit(500)->get()->result();
        $saida = [];
        foreach ($agenda as $a) {
            $horas = (strtotime($a->inicio) - strtotime($agora)) / 3600;
            // Janelas curtas evitam dois lembretes juntos depois de um período sem cron.
            $tipo = $horas > 46 ? 'confirmacao' : ($horas > 22 && $horas <= 24 ? 'lembrete' : null);
            if ($tipo) {
                $saida[] = ['tipo' => $tipo, 'id' => (int) $a->id, 'cliente' => (int) $a->clientes_id,
                    'versao' => $a->inicio . '|' . $a->fim, 'valores' => ['data' => date('d/m/Y', strtotime($a->inicio)), 'hora' => date('H:i', strtotime($a->inicio))]];
            }
        }
        $saldos = $this->db->select('l.idLancamentos, l.valor, l.data_vencimento, os.idOs, os.clientes_id')->from('lancamentos l')
            ->join('os', 'os.idOs = l.atendimento_id')->where('l.recebimento_tipo', 'saldo')->where('l.baixado', 0)
            ->where('os.status !=', 'Cancelado')->where('l.valor >', 0)->where('l.data_vencimento <=', substr($agora, 0, 10))
            ->order_by('l.idLancamentos')->limit(500)->get()->result();
        foreach ($saldos as $s) {
            $saida[] = ['tipo' => 'cobranca', 'id' => (int) $s->idLancamentos, 'cliente' => (int) $s->clientes_id,
                'versao' => $s->data_vencimento . '|' . number_format((float) $s->valor, 2, '.', '') . '|' . date('o-W', strtotime($agora)),
                'valores' => ['atendimento' => $s->idOs, 'saldo' => number_format((float) $s->valor, 2, ',', '.')]];
        }
        $retornos = $this->db->select('e.id, e.retorno, f.clientes_id')->from('ficha_evolucoes e')->join('fichas f', 'f.id = e.ficha_id')
            ->join('os', 'os.idOs = f.os_id')->where('os.status !=', 'Cancelado')->where('e.retorno_status', 'pendente')
            ->where('e.retorno', substr($agora, 0, 10))->order_by('e.id')->limit(500)->get()->result();
        foreach ($retornos as $r) {
            $saida[] = ['tipo' => 'retorno', 'id' => (int) $r->id, 'cliente' => (int) $r->clientes_id, 'versao' => $r->retorno, 'valores' => []];
        }
        return $saida;
    }

    public function valido(object $mensagem, object $conversa, string $agora): bool
    {
        if (! in_array($mensagem->tipo, ['confirmacao', 'lembrete', 'cobranca', 'retorno'], true)) {
            return true;
        }
        if (! $conversa->cliente_id || ! $conversa->lembretes || $conversa->optout) {
            return false;
        }
        // As janelas e os valores são recalculados imediatamente antes do envio.
        foreach ($this->candidatos($agora) as $candidato) {
            if ($candidato['tipo'] === $mensagem->tipo && $candidato['id'] === (int) $mensagem->contexto_id
                && $candidato['cliente'] === (int) $conversa->cliente_id && $candidato['versao'] === $mensagem->contexto_versao) {
                return true;
            }
        }
        return false;
    }
}
