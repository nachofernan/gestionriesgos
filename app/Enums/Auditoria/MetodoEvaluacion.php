<?php

namespace App\Enums\Auditoria;

/**
 * Cómo se calculan impacto y probabilidad de un riesgo a partir del wizard
 * (ver App\Services\Auditoria\EvaluacionRiesgo).
 */
enum MetodoEvaluacion: string
{
    case Clasica = 'clasica';

    public function label(): string
    {
        return match ($this) {
            self::Clasica => 'Clásica',
        };
    }
}
