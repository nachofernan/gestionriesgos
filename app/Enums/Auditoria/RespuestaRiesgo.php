<?php

namespace App\Enums\Auditoria;

enum RespuestaRiesgo: string
{
    case Mitigar = 'mitigar';
    case Evitar = 'evitar';
    case Compartir = 'compartir';
    case Aceptar = 'aceptar';

    public function label(): string
    {
        return match ($this) {
            self::Mitigar => 'Reducir / Mitigar',
            self::Evitar => 'Evitar',
            self::Compartir => 'Compartir',
            self::Aceptar => 'Aceptar / Monitorear',
        };
    }

    /**
     * Respuestas que no admite un TipoRiesgo con `restringe_respuesta` (hoy,
     * Corrupción): no se puede transferir a un tercero ni convivir con él.
     * Las consumen RiesgoController::store/update y los selects del wizard.
     *
     * @return array<int, self>
     */
    public static function restringidas(): array
    {
        return [self::Compartir, self::Aceptar];
    }

    public function estaRestringida(): bool
    {
        return in_array($this, self::restringidas(), true);
    }

    /**
     * Respuestas que exigen `fundamento` en el Riesgo: compartir y aceptar.
     * Mitigar queda afuera porque se justifica con su plan de acción, que ya es
     * obligatorio para validar (ver Riesgo::motivosBloqueoValidacion()). Evitar
     * queda afuera por decisión de negocio (ver docs/DECISIONES.md).
     *
     * @return array<int, self>
     */
    public static function exigenFundamento(): array
    {
        return [self::Compartir, self::Aceptar];
    }

    public function exigeFundamento(): bool
    {
        return in_array($this, self::exigenFundamento(), true);
    }
}
