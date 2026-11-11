<?php

require_once APPPATH . 'helpers/agenda_helper.php';
require_once APPPATH . 'models/Agenda_model.php';

final class AgendaTest extends MaposTestCase
{
    private function modelo(): Agenda_model
    {
        $this->db->query('CREATE TABLE os (idOs INTEGER PRIMARY KEY, status TEXT)');
        $this->db->query("INSERT INTO os VALUES (1, 'Aberto'), (2, 'Cancelado')");
        $this->db->query('CREATE TABLE agenda (id INTEGER PRIMARY KEY AUTOINCREMENT, usuarios_id INTEGER, os_id INTEGER, inicio TEXT, fim TEXT, ocupado_inicio TEXT, ocupado_fim TEXT, cancelado INTEGER DEFAULT 0)');
        $model = $this->makeInstance(Agenda_model::class);
        $this->setPrivateProperty($model, 'db', $this->db);
        return $model;
    }

    public function testCalculaTerminoEIntervalosAtravessandoMeiaNoite(): void
    {
        $periodo = agendaPeriodo('2026-11-11T23:30', 90, 30, 15);
        $this->assertSame('2026-11-12 01:00:00', $periodo['fim']);
        $this->assertSame('2026-11-11 23:00:00', $periodo['ocupado_inicio']);
        $this->assertSame('2026-11-12 01:15:00', $periodo['ocupado_fim']);
        $this->assertNull(agendaPeriodo('2026-02-30T10:00', 60, 0, 0));
        $this->assertNull(agendaPeriodo('2026-11-11T10:00', 0, 0, 0));
        $this->assertNull(agendaPeriodo('2026-11-11T10:00', 60, -10, 0));
    }

    public function testImpedeSobreposicaoComDeslocamentoEMantemReservasAdjacentes(): void
    {
        $model = $this->modelo();
        $dados = agendaPeriodo('2026-11-11T10:00', 60, 30, 15) + ['usuarios_id' => 1, 'os_id' => 1];
        $id = $model->reservar($dados, null);
        $this->assertNotNull($id);
        $this->assertNotNull($model->conflito(1, '2026-11-11 09:45:00', '2026-11-11 10:00:00'));
        $this->assertNotNull($model->conflito(1, '2026-11-11 11:00:00', '2026-11-11 11:10:00'));
        $this->assertNull($model->conflito(1, '2026-11-11 11:15:00', '2026-11-11 12:00:00'));
        $this->assertNull($model->conflito(2, '2026-11-11 10:00:00', '2026-11-11 11:00:00'));
        $this->assertNull($model->conflito(1, $dados['ocupado_inicio'], $dados['ocupado_fim'], $id));
        $this->expectException(DomainException::class);
        $model->reservar(agendaPeriodo('2026-11-11T10:30', 30, 0, 0) + ['usuarios_id' => 1, 'os_id' => 1], null);
    }

    public function testBloqueioConflitaECancelamentoLiberaHorario(): void
    {
        $model = $this->modelo();
        $periodo = agendaPeriodo('2026-11-11T12:00', 60, 0, 0);
        $id = $model->reservar($periodo + ['usuarios_id' => 1, 'os_id' => null], null);
        $this->assertNotNull($model->conflito(1, $periodo['inicio'], $periodo['fim']));
        $this->db->where('id', $id)->update('agenda', ['cancelado' => 1]);
        $this->assertNull($model->conflito(1, $periodo['inicio'], $periodo['fim']));
        $model->reservar($periodo + ['usuarios_id' => 1, 'os_id' => 2], null);
        $this->assertNull($model->conflito(1, $periodo['inicio'], $periodo['fim']));
    }
}
