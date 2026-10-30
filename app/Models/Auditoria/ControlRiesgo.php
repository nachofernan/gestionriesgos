<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Relación control-riesgo (D-040). Tiene dos valores: `mitigacion`, el número
 * que carga quien gestiona el riesgo (null = hereda el `mitigacion_default` del
 * control), y `efectividad` (0-100), que nace en 100 y sólo cambia el auditor con
 * un testeo. Llega como `$control->pivot` o `$riesgo->pivot` según de qué lado se
 * cargue la relación.
 */
class ControlRiesgo extends Pivot
{
    protected $table = 'control_riesgo';

    protected $casts = [
        'checks' => 'array',
        'mitigacion' => 'integer',
        'efectividad' => 'integer',
    ];

    /**
     * Lo que descuenta la relación del residual, sin mirar el estado del control
     * (eso lo decide Control::mitiga()). $default es el mitigacion_default del
     * control, que vale cuando la relación no tiene número propio. La consumen
     * Riesgo::getMitigacionControlesAttribute() y las pantallas que muestran el
     * descuento.
     * Tests: la_relacion_al_50_por_ciento_mitiga_la_mitad,
     * dos_relaciones_del_mismo_control_mitigan_segun_su_propia_efectividad.
     */
    public function mitigacionEfectiva(int $default): int
    {
        return Control::aplicarEfectividad($this->mitigacion ?? $default, $this->efectividad ?? 100);
    }
}
