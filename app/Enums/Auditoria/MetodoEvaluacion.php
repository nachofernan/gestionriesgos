<?php

namespace App\Enums\Auditoria;

/**
 * Cómo se calculan impacto y probabilidad de un riesgo a partir del wizard
 * (ver App\Services\Auditoria\EvaluacionRiesgo). Lo define el tipo de riesgo.
 */
enum MetodoEvaluacion: string
{
    case Clasica = 'clasica';
    case Ambiental = 'ambiental';

    public function label(): string
    {
        return match ($this) {
            self::Clasica => 'Clásica',
            self::Ambiental => 'Ambiental',
        };
    }
}
