<?php

namespace App\Enums\Auditoria;

/** Caracterización de un riesgo ambiental (D-043): no mueve el valor. */
enum Condicion: string
{
    case Normal = 'normal';
    case Contingencia = 'contingencia';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Contingencia => 'Contingencia',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Normal => 'Actividades, productos, servicios que se llevan a cabo en condiciones operativas normales.',
            self::Contingencia => 'Incidentes, accidentes y situaciones de emergencia.',
        };
    }
}
