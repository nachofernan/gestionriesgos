<?php

namespace App\Enums\Auditoria;

/** Caracterización de un riesgo ambiental (D-043): no mueve el valor. */
enum Temporalidad: string
{
    case Pasado = 'pasado';
    case Actual = 'actual';
    case Futuro = 'futuro';

    public function label(): string
    {
        return match ($this) {
            self::Pasado => 'Pasado',
            self::Actual => 'Actual',
            self::Futuro => 'Futuro',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Pasado => 'Actividades, productos, servicios remanentes de una actividad pasada que no continúa en la actualidad.',
            self::Actual => 'Actividades, productos, servicios que se llevan a cabo en el presente.',
            self::Futuro => 'Actividades, productos, servicios planificados (ej. nuevos equipos, ampliaciones, nuevos proyectos, etc.).',
        };
    }
}
