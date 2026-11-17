<?php

namespace App\Livewire\Auditoria\RevisionInicial;

use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\RevisionInicial;
use App\Models\Auditoria\Riesgo;
use App\Services\Auditoria\RevisionInicial\DetectorInconsistencias;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Card "Para la revisión inicial" de la ficha de un elemento (D-048, D-049):
 * sus inconsistencias según DetectorInconsistencias, cada una con su acción
 * (Recalcular, o un ancla al bloque donde se arregla), un <style> que marca en
 * rojo o ámbar esos bloques, y los botones de "Marcar revisado" y "Firmar"
 * ("Validar y firmar" si hay cambios en borrador) para quien la Policy deja.
 * Firmado, se reduce a una línea. La ve todo el que ve el elemento.
 * Se refresca con el evento "{tipo}-actualizado" que emiten los bloques, y lo
 * emite al validar cambios para que los bloques se refresquen.
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

    /** Acción abierta en la card: null | 'revisar' | 'firmar'. */
    public ?string $accion = null;

    public string $comentario = '';

    public ?string $mensaje = null;

    public function mount(string $tipo, int $modeloId): void
    {
        $this->tipo = $tipo;
        $this->modeloId = $modeloId;
    }

    protected function getListeners(): array
    {
        return ["{$this->tipo}-actualizado" => '$refresh'];
    }

    public function abrir(string $accion): void
    {
        $this->resetErrorBag();
        $this->accion = in_array($accion, ['revisar', 'firmar']) ? $accion : null;
        $this->comentario = '';
    }

    public function cancelar(): void
    {
        $this->resetErrorBag();
        $this->accion = null;
    }

    /**
     * Marca revisado ante la gerencia del elemento (RevisionInicialPolicy::revisar).
     * Tests: desde_la_ficha_el_coordinador_marca_revisado, quien_no_firma_recibe_403_desde_la_ficha.
     */
    public function marcarRevisado(): void
    {
        [$elemento, $gerencia] = $this->resolver();
        $this->authorize('revisar', [RevisionInicial::class, $elemento, $gerencia]);

        RevisionInicial::registrarRevisado($elemento, $gerencia, Auth::user(), $this->comentario);

        $this->cerrar('Quedó revisado.');
    }

    /**
     * Firma del gerente (RevisionInicialPolicy::firmar). Con cambios en borrador
     * que puede validar, los valida y firma todo junto
     * (RevisionInicial::validarYFirmar()); si no, firma a secas. Las
     * inconsistencias se recalculan acá, no se confía en las que mostró la card.
     * Tests: desde_la_ficha_el_gerente_firma, desde_la_ficha_el_gerente_valida_y_firma,
     * desde_la_ficha_no_se_firma_con_errores, quien_no_firma_recibe_403_desde_la_ficha.
     */
    public function firmar(): void
    {
        [$elemento, $gerencia] = $this->resolver();
        $this->authorize('firmar', [RevisionInicial::class, $elemento, $gerencia]);

        $validados = RevisionInicial::validarYFirmar($elemento, $gerencia, Auth::user(), $this->comentario);

        if ($validados > 0) {
            $this->dispatch("{$this->tipo}-actualizado");
        }

        $this->cerrar($validados > 0 ? "{$validados} cambio(s) validado(s) y firmado." : 'Quedó firmado.');
    }

    private function cerrar(string $mensaje): void
    {
        $this->accion = null;
        $this->comentario = '';
        $this->mensaje = $mensaje;
    }

    /** @return array{0: Model, 1: Area} */
    private function resolver(): array
    {
        $this->resetErrorBag();
        $this->mensaje = null;
        $elemento = $this->cargar();
        abort_unless($elemento, 404);
        $gerencia = RevisionInicial::gerenciasDe($elemento)->first();
        abort_unless($gerencia, 403);

        return [$elemento, $gerencia];
    }

    /** El elemento con los conteos del detector, o null si la revisión está apagada o está borrado. */
    private function cargar(): ?Model
    {
        if (! config('auditoria.revision_inicial.activa')) {
            return null;
        }

        return DetectorInconsistencias::consulta(Index::TIPOS[$this->tipo]['modelo'])
            ->with(['area', 'user', 'revisionesIniciales.revisadoPor', 'revisionesIniciales.firmadoPor'])
            ->where('estado_id', '!=', Estado::borrado()?->id)
            ->find($this->modeloId);
    }

    public function render()
    {
        $elemento = $this->cargar();

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
        $puedeFirmar = $gerencia && $user->can('firmar', [RevisionInicial::class, $elemento, $gerencia]);

        return view('livewire.auditoria.revision-inicial.pendientes-ficha', [
            'visible' => true,
            'firma' => null,
            'revision' => $revision,
            'hallazgos' => $hallazgos,
            'errores' => $hallazgos->where('severidad', DetectorInconsistencias::ERROR)->count(),
            'advertencias' => $hallazgos->where('severidad', DetectorInconsistencias::ADVERTENCIA)->count(),
            'marcas' => $marcas,
            'faltaRecalcular' => $hallazgos->contains(fn ($h) => $h['codigo'] === 'sin_evaluacion' && $h['severidad'] === DetectorInconsistencias::ERROR),
            'puedeRecalcular' => $elemento instanceof Riesgo
                && $user->can('proponer', $elemento)
                && $elemento->motivoBloqueoRecalculo($user) === null,
            'puedeRevisar' => $gerencia && ! $revision?->revisado_en && $user->can('revisar', [RevisionInicial::class, $elemento, $gerencia]),
            'puedeFirmar' => $puedeFirmar,
            'cambiosAValidar' => $puedeFirmar ? RevisionInicial::cambiosAValidar($elemento, $user)->count() : 0,
            'elemento' => $elemento,
        ]);
    }
}
