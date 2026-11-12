<?php

class Whatsapp extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'evolution']);
        $this->load->model('evolution_model');
        $this->load->library(['encryption', 'evolution_api']);
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para configurar o WhatsApp.', 403);
            return;
        }
        return $this->tela();
    }

    private function tela(array $extras = [])
    {
        $config = $this->evolution_model->configuracao();
        $this->data = array_merge($this->data, [
            'configuracao' => ['url' => $config->url ?? '', 'instancia' => $config->instancia ?? ''],
            'tem_chave' => ! empty($config->chave_cifrada), 'erros' => [], 'erro' => null,
            'estado' => ['label' => $config ? 'Aguardando consulta' : 'Não configurado', 'variant' => 'neutral'],
            'qr' => null, 'view' => 'whatsapp/index',
        ], $extras);
        $this->output->set_header('Cache-Control: no-store, private');
        return $this->layout();
    }

    private function somentePost(): bool
    {
        if ($this->input->method() !== 'post') {
            show_error('Use o formulário para esta operação.', 405);
            return false;
        }
        return true;
    }

    public function salvar()
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para configurar o WhatsApp.', 403);
            return;
        }
        if (! $this->somentePost()) {
            return;
        }
        [$dados, $erros] = evolutionConfiguracao($this->input->post());
        $anterior = $this->evolution_model->configuracao();
        if ($dados['chave'] === '' && (! $anterior || $anterior->url !== $dados['url'])) {
            $erros['chave'] = 'Informe a chave ao configurar ou alterar o servidor.';
        }
        if ($erros !== []) {
            $this->output->set_status_header(422);
            return $this->tela(['configuracao' => array_intersect_key($dados, array_flip(['url', 'instancia'])), 'erros' => $erros]);
        }
        $cifrada = $dados['chave'] !== '' ? $this->encryption->encrypt($dados['chave']) : $anterior->chave_cifrada;
        $ok = is_string($cifrada) && $cifrada !== '' && $this->evolution_model->salvar($dados['url'], $dados['instancia'], $cifrada);
        if ($ok) {
            log_info('Atualizou configuração da integração WhatsApp.');
        }
        $this->session->set_flashdata($ok ? 'success' : 'error', $ok ? 'Configuração salva. Consulte o status para testar a conexão.' : 'Não foi possível salvar a configuração.');
        return redirect('whatsapp');
    }

    public function executar()
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para configurar o WhatsApp.', 403);
            return;
        }
        if (! $this->somentePost()) {
            return;
        }
        $acao = $this->input->post('acao');
        if (! is_string($acao) || ! in_array($acao, ['status', 'conectar', 'reiniciar', 'desconectar'], true)) {
            show_error('Operação inválida.', 422);
            return;
        }
        $config = $this->evolution_model->configuracao();
        $chave = $config ? $this->encryption->decrypt($config->chave_cifrada) : false;
        if (! is_string($chave) || $chave === '') {
            return $this->tela(['erro' => 'Salve uma configuração válida antes de continuar.']);
        }
        try {
            $resposta = $this->evolution_api->executar($config->url, $config->instancia, $chave, $acao);
            if ($acao !== 'status') {
                log_info('Executou operação WhatsApp: ' . $acao);
            }
            $estado = $acao === 'desconectar' ? evolutionEstado(['instance' => ['state' => 'close']]) : evolutionEstado($resposta);
            if ($acao === 'reiniciar') {
                $estado = ['label' => 'Reinício solicitado — consulte o status', 'variant' => 'progress'];
            }
            $qr = $acao === 'conectar' ? evolutionQr($resposta) : null;
            if ($qr !== null) {
                $estado = ['label' => 'Aguardando leitura do QR Code', 'variant' => 'progress'];
            }
            return $this->tela(['estado' => $estado, 'qr' => $qr]);
        } catch (RuntimeException $e) {
            return $this->tela(['erro' => $e->getMessage()]);
        }
    }
}
