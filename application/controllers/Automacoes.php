<?php

class Automacoes extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'automacoes']);
        $this->load->model('automacoes_model');
        $this->output->set_header('Cache-Control: no-store, private');
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para configurar automações.', 403);
            return;
        }
        return $this->tela();
    }

    private function tela(array $extras = [])
    {
        $this->data = array_merge($this->data, ['valores' => $this->automacoes_model->configuracao(),
            'estado' => $this->automacoes_model->estado(), 'erros' => [],
            'webhook_url' => site_url('evolutionhook'),
            'tem_webhook' => strlen((string) ($_ENV['EVOLUTION_WEBHOOK_SECRET'] ?? '')) >= 32,
            'tem_ia' => ! empty($_ENV['OPENAI_API_KEY']), 'pode_emitente' => $this->permite('cEmitente'),
            'tem_smtp' => ! empty($_ENV['EMAIL_SMTP_USER']) && $_ENV['EMAIL_SMTP_USER'] !== 'seuemail@gmail.com' && ! empty($_ENV['EMAIL_SMTP_PASS']),
            'view' => 'automacoes/index'], $extras);
        return $this->layout();
    }

    public function salvar()
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            show_error('Sem permissão para configurar automações.', 403);
            return;
        }
        if ($this->input->method() !== 'post') {
            show_error('Use o formulário para salvar.', 405);
            return;
        }
        [$dados, $erros] = automacoesDados($this->input->post() ?: []);
        $versao = $this->input->post('versao');
        if (! is_string($versao) || ! ctype_digit($versao)) {
            $erros['_geral'] = 'Reabra a configuração antes de salvar.';
        }
        if ($dados['ia'] && empty($_ENV['OPENAI_API_KEY'])) {
            $erros['modelo_ia'] = 'Configure OPENAI_API_KEY no servidor antes de ativar a IA.';
        }
        if ($dados['automatico'] && strlen((string) ($_ENV['EVOLUTION_WEBHOOK_SECRET'] ?? '')) < 32) {
            $erros['_geral'] = 'Configure o segredo do webhook no servidor antes de ativar o atendimento automático.';
        }
        if ($erros === []) {
            try {
                $this->automacoes_model->salvar($dados, (int) $versao);
                log_info('Atualizou perfil comercial, modelos e automações.');
                $this->session->set_flashdata('success', 'Configurações salvas. Os envios dependem da conexão e da rotina de processamento.');
                return redirect('automacoes');
            } catch (DomainException $e) {
                $erros['_geral'] = $e->getMessage();
            }
        }
        $this->output->set_status_header(422);
        return $this->tela(['valores' => $dados, 'erros' => $erros]);
    }
}
