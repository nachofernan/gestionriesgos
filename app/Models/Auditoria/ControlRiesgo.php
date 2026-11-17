<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Relación control-riesgo (D-051). El control mitiga lo mismo en cada riesgo, así
 * que la relación sólo tiene la evidencia de cumplimiento (`efectividad`, 0-100):
 * nace en 100 y sólo la cambia el auditor con un testeo. Llega como
 * `$control->pivot` o `$riesgo->pivot` según de qué lado se cargue la relación.
 */
class ControlRiesgo extends Pivot
{
    protected $table = 'control_riesgo';

    protected $casts = [
        'efectividad' => 'integer',
    ];

    /**
     * Lo que descuenta la relación del residual, sin mirar el estado del control
     * (eso lo decide Control::mitiga()): el valor del control ($valorControl, su
     * mitigacion_default) regulado por el tramo de la evidencia
     * (Control::aplicarEvidencia()). La consumen Riesgo::getMitigacionControlesAttribute()
     * y las pantallas que muestran el descuento.
     * Tests: cada_tramo_de_evidencia_mitiga_su_porcentaje_hacia_abajo,
     * dos_relaciones_del_mismo_control_mitigan_segun_su_propia_evidencia.
     */
    public function mitigacionEfectiva(int $valorControl): int
    {
        return Control::aplicarEvidencia($valorControl, $this->efectividad ?? 100);
    }
}
