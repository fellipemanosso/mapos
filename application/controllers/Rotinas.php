<?php

/** Nunca executa por HTTP. O agendador do servidor chama estes comandos. */
class Rotinas extends CI_Controller
{
    public function processar()
    {
        if (! $this->input->is_cli_request()) {
            $this->output->set_status_header(404)->set_output('');
            return;
        }
        $this->load->library('automacoes_worker');
        try {
            $resultado = $this->automacoes_worker->executar();
            echo json_encode($resultado) . PHP_EOL;
        } catch (Throwable $e) {
            log_message('error', 'A rotina de mensagens não foi concluída.');
            fwrite(STDERR, 'A rotina falhou. Confira o banco, armazenamento e configurações.' . PHP_EOL);
            exit(1);
        }
    }

    public function backup()
    {
        if (! $this->input->is_cli_request()) {
            $this->output->set_status_header(404)->set_output('');
            return;
        }
        $this->load->library('backup_operacao');
        try {
            $arquivo = $this->backup_operacao->executar();
            echo 'Backup concluído: ' . $arquivo . PHP_EOL;
        } catch (Throwable $e) {
            log_message('error', 'A rotina de backup não foi concluída.');
            fwrite(STDERR, 'Backup falhou. Confira BACKUP_PATH, permissões, espaço e extensão zip.' . PHP_EOL);
            exit(1);
        }
    }

    public function diagnostico()
    {
        if (! $this->input->is_cli_request()) {
            $this->output->set_status_header(404)->set_output('');
            return;
        }
        $this->load->helper('automacoes');
        $this->load->model('automacoes_model');
        $estado = $this->automacoes_model->estado();
        $r = ['ambiente_producao' => ($_ENV['APP_ENVIRONMENT'] ?? '') === 'production',
            'url_https' => str_starts_with((string) ($_ENV['APP_BASEURL'] ?? ''), 'https://'),
            'csrf' => (bool) $this->config->item('csrf_protection'),
            'segredo_webhook' => strlen((string) ($_ENV['EVOLUTION_WEBHOOK_SECRET'] ?? '')) >= 32,
            'chave_ia' => ! empty($_ENV['OPENAI_API_KEY']), 'backup_configurado' => ! empty($_ENV['BACKUP_PATH']),
            'storage_gravavel' => is_dir(armazenamentoRaiz()) && is_writable(armazenamentoRaiz()),
            'worker_recente' => $estado->ultimo_worker && strtotime($estado->ultimo_worker) > time() - 300,
            'smtp_configurado' => ! empty($_ENV['EMAIL_SMTP_PASS']) && $_ENV['EMAIL_SMTP_PASS'] !== 'senhadoemail'];
        echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
