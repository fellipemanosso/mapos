<?php

function resumoMes($mes, string $hoje): DateTimeImmutable
{
    $data = is_string($mes) ? DateTimeImmutable::createFromFormat('!Y-m', $mes) : false;
    if (! $data || $data->format('Y-m') !== $mes || (int) $data->format('Y') < 1000 || (int) $data->format('Y') > 9998) {
        $data = new DateTimeImmutable(substr($hoje, 0, 7) . '-01');
    }
    return $data;
}
