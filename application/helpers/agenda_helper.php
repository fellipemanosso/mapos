<?php

function agendaIntervalo(DateTimeImmutable $dia, string $modo): array
{
    if ($modo === 'calendario') {
        $primeiro = $dia->modify('first day of this month');
        $ultimo = $dia->modify('last day of this month');
        return [$primeiro->modify('monday this week'), $ultimo->modify('sunday this week')->modify('+1 day')];
    }
    $inicio = $modo === 'dia' ? $dia : $dia->modify('monday this week');
    return [$inicio, $inicio->modify($modo === 'dia' ? '+1 day' : '+7 days')];
}

/** Horários locais de São Paulo, em intervalos semiabertos: fim às 10h permite início às 10h. */
function agendaPeriodo(string $inicio, int $duracao, int $deslocamento, int $intervalo): ?array
{
    $data = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $inicio, new DateTimeZone('America/Sao_Paulo'));
    if (! $data || $data->format('Y-m-d\TH:i') !== $inicio || $duracao < 1 || $duracao > 1440 || $deslocamento < 0 || $deslocamento > 720 || $intervalo < 0 || $intervalo > 720) {
        return null;
    }
    $fim = $data->modify('+' . $duracao . ' minutes');

    return [
        'inicio' => $data->format('Y-m-d H:i:s'),
        'fim' => $fim->format('Y-m-d H:i:s'),
        'ocupado_inicio' => $data->modify('-' . $deslocamento . ' minutes')->format('Y-m-d H:i:s'),
        'ocupado_fim' => $fim->modify('+' . $intervalo . ' minutes')->format('Y-m-d H:i:s'),
    ];
}
