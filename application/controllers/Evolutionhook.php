<?php

/** Endpoint isolado: sem sessão e sem CSRF, autenticado por segredo exclusivo no header. */
class Evolutionhook extends CI_Controller
{
    public function index()
    {
        $this->output->set_header('Cache-Control: no-store');
        $segredo = $_ENV['EVOLUTION_WEBHOOK_SECRET'] ?? '';
        $recebido = $this->input->get_request_header('X-Mapos-Webhook');
        if (! is_string($segredo) || strlen($segredo) < 32 || ! is_string($recebido) || ! hash_equals($segredo, $recebido)) {
            $this->output->set_status_header(403)->set_output('');
            return;
        }
        if ($this->input->method() !== 'post') {
            $this->output->set_status_header(405)->set_output('');
            return;
        }
        $corpo = file_get_contents('php://input', false, null, 0, 262145);
        if ($corpo === false || strlen($corpo) > 262144 || ! is_array($evento = json_decode($corpo, true, 32))) {
            $this->output->set_status_header(400)->set_output('');
            return;
        }
        $this->load->database();
        $this->load->helper('automacoes');
        $this->load->model(['evolution_model', 'automacoes_model']);
        $config = $this->evolution_model->configuracao();
        $normalizado = $config ? whatsappEvento($evento, $config->instancia) : null;
        if ($normalizado) {
            try {
                $this->automacoes_model->receber($normalizado);
            } catch (DomainException $e) {
                $this->output->set_status_header(429)->set_output('');
                return;
            } catch (Throwable $e) {
                log_message('error', 'Não foi possível persistir um evento WhatsApp.');
                $this->output->set_status_header(503)->set_output('');
                return;
            }
        }
        $this->output->set_status_header(204)->set_output('');
    }
}
