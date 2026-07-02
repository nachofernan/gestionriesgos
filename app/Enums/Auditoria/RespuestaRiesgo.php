<?php

namespace App\Enums\Auditoria;

enum RespuestaRiesgo: string
{
    case Mitigar   = 'mitigar';
    case Evitar    = 'evitar';
    case Compartir = 'compartir';
    case Aceptar   = 'aceptar';

    public function label(): string
    {
        return match ($this) {
            self::Mitigar   => 'Reducir / Mitigar',
            self::Evitar    => 'Evitar',
            self::Compartir => 'Compartir',
            self::Aceptar   => 'Aceptar',
        };
    }
}
