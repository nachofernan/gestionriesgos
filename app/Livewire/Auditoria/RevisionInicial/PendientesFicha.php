<?php

namespace App\Livewire\Auditoria\RevisionInicial;

use App\Models\Auditoria\Estado;
use App\Models\Auditoria\RevisionInicial;
use App\Models\Auditoria\Riesgo;
use App\Services\Auditoria\RevisionInicial\DetectorInconsistencias;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Card "Para la revisión inicial" de la ficha de un elemento (D-048): sus
 * inconsistencias según DetectorInconsistencias, cada una con su acción
 * (Recalcular, o un ancla al bloque donde se arregla), y un <style> que marca
 * en rojo o ámbar esos bloques. Firmado, se reduce a una línea. Es de sólo
 * lectura: revisar y firmar siguen en RevisionInicial\Index. La ve todo el
 * que ve el elemento; el botón Recalcular, sólo quien puede recalcular.
 * Se refresca con el evento "{tipo}-actualizado" que emiten los bloques.
 * Tests: RevisionInicialFichaTest.
 */
class PendientesFicha extends Component
{
    /** Bloque (id de ancla en la ficha) donde se arregla cada inconsistencia. */
    private const ANCLAS = [
        'riesgo' => [
            'sin_fundamento' => ['ficha', 'Ver ficha'],
            'sin_objetivo' => ['objetivos', 'Ver objetivos'],
            'mitigar_sin_plan' => ['planes', 'Ver planes'],
            'sin_controles' => ['controles', 'Ver controles'],
            'sin_evaluacion' => ['valor', 'Ver valor'],
            'valor_en_cero' => ['valor', 'Ver valor'],
        ],
        'control' => [
            'mitigacion_en_cero' => ['mitigacion', 'Ver mitigación'],
            'asociacion_en_cero' => ['riesgos', 'Ver riesgos'],
            'sin_riesgos' => ['riesgos', 'Ver riesgos'],
        ],
        'objetivo' => [
            'peis_sin_items' => ['ficha', 'Ver ficha'],
            'sin_riesgos' => ['riesgos', 'Ver riesgos'],
        ],
        'plan' => [
            'sin_riesgos' => ['riesgos', 'Ver riesgos'],
            'sin_tareas' => ['tareas', 'Ver tareas'],
        ],
        'tarea' => [
            'sin_fecha' => ['ficha', 'Ver ficha'],
            'sin_plan' => ['planes', 'Ver planes'],
            'vencida' => ['avance', 'Ver avance'],
        ],
    ];

    public string $tipo;

    public int $modeloId;

    public function mount(string $tipo, int $modeloId): void
    {
        $this->tipo = $tipo;
        $this->modeloId = $modeloId;
    }

    protected function getListeners(): array
    {
        return ["{$this->tipo}-actualizado" => '$refresh'];
    }

    public function render()
    {
        $elemento = config('auditoria.revision_inicial.activa')
            ? DetectorInconsistencias::consulta(Index::TIPOS[$this->tipo]['modelo'])
                ->with(['area', 'user', 'revisionesIniciales.revisadoPor', 'revisionesIniciales.firmadoPor'])
                ->where('estado_id', '!=', Estado::borrado()?->id)
                ->find($this->modeloId)
            : null;

        if (! $elemento) {
            return view('livewire.auditoria.revision-inicial.pendientes-ficha', ['visible' => false]);
        }

        $gerencia = RevisionInicial::gerenciasDe($elemento)->first();
        $revision = $gerencia ? $elemento->revisionesIniciales->firstWhere('gerencia_id', $gerencia->id) : null;

        if ($revision?->firmado_en) {
            return view('livewire.auditoria.revision-inicial.pendientes-ficha', ['visible' => true, 'firma' => $revision]);
        }

        $hallazgos = collect((new DetectorInconsistencias)->detectar($elemento))->map(fn ($h) => [
            ...$h,
            'ancla' => self::ANCLAS[$this->tipo][$h['codigo']] ?? ['ficha', 'Ver ficha'],
        ]);

        // Marca de cada bloque: error si alguno de sus hallazgos lo es, si no advertencia.
        $marcas = $hallazgos->where('severidad', '!=', DetectorInconsistencias::EN_CURSO)
            ->groupBy(fn ($h) => $h['ancla'][0])
            ->map(fn ($hs) => $hs->contains('severidad', DetectorInconsistencias::ERROR) ? 'error' : 'advertencia');

        $user = Auth::user();

        return view('livewire.auditoria.revision-inicial.pendientes-ficha', [
            'visible' => true,
            'firma' => null,
            'revision' => $revision,
            'hallazgos' => $hallazgos,
            'errores' => $hallazgos->where('severidad', DetectorInconsistencias::ERROR)->count(),
            'marcas' => $marcas,
            'faltaRecalcular' => $hallazgos->contains(fn ($h) => $h['codigo'] === 'sin_evaluacion' && $h['severidad'] === DetectorInconsistencias::ERROR),
            'puedeRecalcular' => $elemento instanceof Riesgo
                && $user->can('proponer', $elemento)
                && $elemento->motivoBloqueoRecalculo($user) === null,
            'elemento' => $elemento,
        ]);
    }
}
