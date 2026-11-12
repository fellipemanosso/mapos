<?php

class Exportacoes extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'exportacoes', 'fichas']);
        $this->output->set_header('Cache-Control: no-store, private');
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['rCliente', 'rFinanceiro', 'rFicha'])) {
            show_error('Sem permissão para exportar dados.', 403);
            return;
        }
        $this->data += ['pode_clientes' => $this->permite('rCliente'), 'pode_financeiro' => $this->permite('rFinanceiro')];
        $this->data['view'] = 'exportacoes/index';
        return $this->layout();
    }

    private function post(): bool
    {
        if ($this->input->method() !== 'post') {
            show_error('Use o formulário para exportar.', 405);
            return false;
        } return true;
    }

    private function csv(string $nome)
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nome . '.csv"');
        header('Cache-Control: no-store, private');
        $stream = fopen('php://output', 'wb');
        fwrite($stream, "\xEF\xBB\xBF");
        return $stream;
    }

    public function clientes()
    {
        if (! $this->hasAnyPermission(['rCliente'])) {
            show_error('Sem permissão para exportar clientes.', 403);
            return;
        }
        if (! $this->post()) {
            return;
        }
        log_info('Exportou cadastro de clientes.');
        $stream = $this->csv('clientes-' . date('Y-m-d'));
        exportacaoLinha($stream, ['Código', 'Nome', 'Telefone', 'Celular', 'E-mail', 'Cidade', 'Estado']);
        $ultimo = 0;
        do {
            $linhas = $this->db->select('idClientes, nomeCliente, telefone, celular, email, cidade, estado')->where('fornecedor', 0)->where('idClientes >', $ultimo)->order_by('idClientes')->limit(500)->get('clientes')->result_array();
            foreach ($linhas as $linha) {
                $ultimo = (int) $linha['idClientes'];
                exportacaoLinha($stream, array_values($linha));
            }
        } while (count($linhas) === 500);
        fclose($stream);
    }

    public function financeiro()
    {
        if (! $this->hasAnyPermission(['rFinanceiro'])) {
            show_error('Sem permissão para exportar financeiro.', 403);
            return;
        }
        if (! $this->post()) {
            return;
        }
        $inicio = fichaData($this->input->post('inicio'));
        $fim = fichaData($this->input->post('fim'));
        if (! $inicio || ! $fim || $inicio > $fim) {
            show_error('Informe um período válido por vencimento.', 422);
            return;
        }
        log_info('Exportou lançamentos financeiros.');
        $stream = $this->csv('financeiro-' . $inicio . '-' . $fim);
        exportacaoLinha($stream, ['Código', 'Descrição', 'Tipo', 'Valor bruto', 'Desconto', 'Valor líquido registrado', 'Vencimento', 'Pagamento', 'Baixado', 'Forma de pagamento']);
        $ultimo = 0;
        do {
            $linhas = $this->db->select('idLancamentos, descricao, tipo, valor, desconto, valor_desconto, data_vencimento, data_pagamento, baixado, forma_pgto')->where('data_vencimento >=', $inicio)->where('data_vencimento <=', $fim)->where('idLancamentos >', $ultimo)->order_by('idLancamentos')->limit(500)->get('lancamentos')->result_array();
            foreach ($linhas as $linha) {
                $ultimo = (int) $linha['idLancamentos'];
                foreach (['valor', 'desconto', 'valor_desconto'] as $campo) {
                    if (is_numeric($linha[$campo])) {
                        $linha[$campo] = number_format((float) $linha[$campo], 2, ',', '');
                    }
                }
                exportacaoLinha($stream, array_values($linha));
            }
        } while (count($linhas) === 500);
        fclose($stream);
    }

    public function fichas($id = null)
    {
        if (! $this->hasAnyPermission(['rFicha'])) {
            show_error('Sem permissão para exportar histórico clínico.', 403);
            return;
        }
        if (! $this->post()) {
            return;
        }
        $this->load->model('fichas_model');
        $ficha = is_scalar($id) && ctype_digit((string) $id) ? $this->fichas_model->ficha((int) $id) : null;
        if (! $ficha) {
            show_404();
            return;
        }
        header('Content-Type: application/x-ndjson; charset=UTF-8');
        header('Content-Disposition: attachment; filename="historico-ficha-' . (int) $ficha->id . '.ndjson"');
        header('Cache-Control: no-store, private');
        log_info('Exportou histórico da pessoa atendida na ficha #' . $ficha->id);
        foreach ($this->fichas_model->historico((int) $ficha->clientes_id, $ficha->dependente_id ? (int) $ficha->dependente_id : null) as $item) {
            $registro = (array) $this->fichas_model->ficha((int) $item->id);
            unset($registro['chave']);
            $evolucoes = array_map(static function ($e) {
                $linha = (array) $e;
                unset($linha['chave']);
                return $linha;
            }, $this->fichas_model->evolucoes((int) $item->id));
            echo json_encode(['ficha' => $registro, 'evolucoes' => $evolucoes, 'documentos' => $this->fichas_model->arquivos((int) $item->id)], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
        }
    }
}
