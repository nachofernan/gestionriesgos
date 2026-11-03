<?php

namespace App\Enums\Auditoria;

/** Caracterización de un riesgo ambiental (D-043): no mueve el valor. */
enum Responsabilidad: string
{
    case Directa = 'directa';
    case Indirecta = 'indirecta';

    public function label(): string
    {
        return match ($this) {
            self::Directa => 'Directa',
            self::Indirecta => 'Indirecta',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Directa => 'Cuando el aspecto ambiental está vinculado directamente a las actividades, productos y servicios de la empresa, y que por lo tanto pueden ser controlados. Incluye la actividad de contratistas y proveedores dentro del predio de la empresa.',
            self::Indirecta => 'Cuando el aspecto ambiental deriva de las actividades, productos y servicios de la empresa, pero sobre los cuales no puede ejercerse control directo, aunque puede ejercerse cierta influencia sobre los mismos (ej. disposición final de residuos especiales, transporte realizado por terceros, etc.).',
        };
    }
}
