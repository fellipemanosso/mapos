<?php

class Deslocamentos extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'deslocamentos']);
        $this->load->model('os_model');
    }

    public function formulario($id = null)
    {
        if (! $this->hasAnyPermission(['eOs'])) {
            show_error('Sem permissão para configurar o deslocamento.', 403);
            return;
        }
        $os = is_scalar($id) && ctype_digit((string) $id) ? $this->os_model->getById((int) $id) : null;
        if (! $os) {
            show_404();
            return;
        }
        $valores = (array) $os;
        $valores['distancia_km'] = number_format((float) ($os->distancia_km ?? 0), 2, ',', '');
        $erros = [];
        if ($this->input->method() === 'post') {
            $valores = $this->input->post();
            [$dados, $erros] = deslocamentoDados($valores);
            if ($erros === [] && $this->os_model->edit('os', $dados, 'idOs', (int) $os->idOs)) {
                log_info('Atualizou logística do atendimento #' . $os->idOs);
                $this->session->set_flashdata('success', 'Logística salva. O tempo será sugerido nos novos agendamentos; horários existentes e preços foram preservados.');
                return redirect('os/visualizar/' . $os->idOs);
            }
            $this->output->set_status_header(422);
        }
        $this->data += ['os' => $os, 'valores' => $valores, 'erros' => $erros];
        $this->data['view'] = 'deslocamentos/formulario';
        return $this->layout();
    }
}
