<?php

namespace App\Livewire\Auditoria\RevisionInicial;

use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\RevisionInicial;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use App\Services\Auditoria\RevisionInicial\DetectorInconsistencias;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pantalla transitoria de revisión de la carga inicial (D-024). Lista cada
 * elemento con sus inconsistencias (DetectorInconsistencias) y su estado de
 * firma por gerencia; el responsable o el coordinador lo marca revisado y el
 * gerente lo firma (RevisionInicialPolicy). Los arreglos se hacen en el show
 * de cada elemento, con el circuito de propuestas de siempre. Comité y auditor
 * ven todo en solo lectura. Se apaga con config('auditoria.revision_inicial.activa').
 * Tests: RevisionInicialPantallaTest.
 */
class Index extends Component
{
    use WithPagination;

    /** @var array<string, array{modelo: class-string, label: string, ruta: string}> */
    public const TIPOS = [
        'riesgo' => ['modelo' => Riesgo::class, 'label' => 'Riesgos', 'ruta' => 'auditoria.riesgos.show'],
        'control' => ['modelo' => Control::class, 'label' => 'Controles', 'ruta' => 'auditoria.controles.show'],
        'objetivo' => ['modelo' => Objetivo::class, 'label' => 'Objetivos', 'ruta' => 'auditoria.objetivos.show'],
        'plan' => ['modelo' => PlanAccion::class, 'label' => 'Planes de acción', 'ruta' => 'auditoria.planes.show'],
        'tarea' => ['modelo' => Tarea::class, 'label' => 'Tareas', 'ruta' => 'auditoria.tareas.show'],
    ];

    public string $tipo = 'riesgo';

    public string $search = '';

    /** pendiente (incluye lo revisado sin firmar) | firmado */
    public string $filtroRevision = 'pendiente';

    /** errores | advertencias | limpios */
    public string $filtroHallazgos = '';

    public ?int $filtroGerencia = null;

    public ?string $mensaje = null;

    protected $queryString = [
        'tipo' => ['except' => 'riesgo'],
        'search' => ['except' => ''],
        'filtroRevision' => ['except' => 'pendiente'],
        'filtroHallazgos' => ['except' => ''],
        'filtroGerencia' => ['except' => null],
    ];

    public function mount(): void
    {
        abort_unless(config('auditoria.revision_inicial.activa'), 404);
    }

    public function updating($propiedad): void
    {
        if (in_array($propiedad, ['tipo', 'search', 'filtroRevision', 'filtroHallazgos', 'filtroGerencia'])) {
            $this->resetPage();
        }
    }

    /** Marca revisado un elemento ante una gerencia. */
    public function marcarRevisado(string $tipo, int $id, int $gerenciaId, ?string $comentario = null): void
    {
        $this->resetErrorBag();
        $this->mensaje = null;
        [$elemento, $gerencia] = $this->resolver($tipo, $id, $gerenciaId);
        $this->authorize('revisar', [RevisionInicial::class, $elemento, $gerencia]);

        RevisionInicial::registrarRevisado($elemento, $gerencia, Auth::user(), $comentario);

        $this->mensaje = "«{$elemento->nombre}» quedó revisado.";
        $this->dispatch('revision-registrada');
    }

    /**
     * Firma del gerente. Las inconsistencias se recalculan acá, no se confía
     * en las que vio la pantalla: si algo cambió entre medio, manda lo actual.
     */
    public function firmar(string $tipo, int $id, int $gerenciaId, ?string $comentario = null): void
    {
        $this->resetErrorBag();
        $this->mensaje = null;
        [$elemento, $gerencia] = $this->resolver($tipo, $id, $gerenciaId);
        $this->authorize('firmar', [RevisionInicial::class, $elemento, $gerencia]);

        if ($this->cambiosAValidar($elemento)->isNotEmpty()) {
            throw ValidationException::withMessages(['revision' => 'Tiene cambios sin validar: usá «Validar y firmar».']);
        }

        $hallazgos = (new DetectorInconsistencias)->detectar($elemento);
        RevisionInicial::registrarFirma($elemento, $gerencia, Auth::user(), $hallazgos, $comentario);

        $this->mensaje = "«{$elemento->nombre}» quedó firmado.";
        $this->dispatch('revision-registrada');
    }

    /**
     * Valida todas las propuestas en borrador del elemento que el gerente puede
     * validar (con el circuito de siempre, Actualizacion::marcarValidada()) y lo
     * firma. Todo o nada: si después de validar la firma no procede (errores, o
     * advertencias sin comentario), no queda validado nada.
     * Tests: validar_y_firmar_valida_los_cambios_y_firma,
     * si_la_firma_no_procede_no_se_valida_nada, con_cambios_sin_validar_no_se_firma_a_secas.
     */
    public function validarYFirmar(string $tipo, int $id, int $gerenciaId, ?string $comentario = null): void
    {
        $this->resetErrorBag();
        $this->mensaje = null;
        [$elemento, $gerencia] = $this->resolver($tipo, $id, $gerenciaId);
        $this->authorize('firmar', [RevisionInicial::class, $elemento, $gerencia]);

        $cambios = $this->cambiosAValidar($elemento);

        DB::transaction(function () use ($cambios, $tipo, $id, $gerencia, $comentario) {
            foreach ($cambios as $cambio) {
                $this->authorize('validar', $cambio);
                $cambio->marcarValidada(Auth::user());
            }

            $elemento = DetectorInconsistencias::consulta(self::TIPOS[$tipo]['modelo'])->findOrFail($id);
            $hallazgos = (new DetectorInconsistencias)->detectar($elemento);
            RevisionInicial::registrarFirma($elemento, $gerencia, Auth::user(), $hallazgos, $comentario);
        });

        $this->mensaje = "«{$elemento->nombre}»: {$cambios->count()} cambio(s) validado(s) y firmado.";
        $this->dispatch('revision-registrada');
    }

    /** Una línea de texto por cambio de la propuesta, a partir de Actualizacion::diffLegible(). */
    private static function lineasDeCambio(Actualizacion $propuesta): array
    {
        $diff = $propuesta->diffLegible();
        $verbos = ['agrega' => 'agregar', 'quita' => 'quitar', 'cambia' => 'cambiar'];

        return [
            ...array_map(fn ($c) => "{$c['etiqueta']}: {$c['antes']} → {$c['despues']}", $diff['campos']),
            ...collect($diff['relaciones'])->flatMap(fn ($r) => array_map(
                fn ($i) => "{$r['etiqueta']}: ".($verbos[$i['op']] ?? $i['op'])." {$i['nombre']}".($i['detalle'] ? " ({$i['detalle']})" : ''),
                $r['items'],
            ))->all(),
        ];
    }

    /** Propuestas en borrador del elemento que el usuario puede validar. */
    private function cambiosAValidar(Model $elemento): Collection
    {
        return $elemento->actualizaciones()->propuestasPendientes()
            ->where('estado_id', Estado::borrador()->id)
            ->get()
            ->each(fn (Actualizacion $a) => $a->setRelation('actualizable', $elemento))
            ->filter(fn (Actualizacion $a) => Gate::allows('validar', $a))
            ->values();
    }

    /** @return array{0: Model, 1: Area} */
    private function resolver(string $tipo, int $id, int $gerenciaId): array
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404);

        return [
            DetectorInconsistencias::consulta(self::TIPOS[$tipo]['modelo'])->findOrFail($id),
            Area::findOrFail($gerenciaId),
        ];
    }

    /** Comité, auditor y superusuario miran todas las gerencias. */
    private function miraTodo(User $user): bool
    {
        return ! $user->area_id || $user->esComite() || $user->esAuditor();
    }

    /**
     * Elementos que el usuario tiene en la revisión: los de su área y sub-áreas
     * (el sesgo gerencial de Vencimientos y el Panel) y aquellos de los que es
     * responsable. Los rechazados (borrado) no se revisan.
     */
    private function consultaPara(string $modelo, User $user): Builder
    {
        $query = DetectorInconsistencias::consulta($modelo)
            ->with(['user', 'area', 'estado', 'revisionesIniciales.revisadoPor', 'revisionesIniciales.firmadoPor'])
            ->where('estado_id', '!=', Estado::borrado()?->id);

        if ($this->miraTodo($user)) {
            return $query;
        }

        $ids = $user->area->obtenerIdsSubarbol();

        return $query->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('area_id', $ids));
    }

    /**
     * Cada elemento con sus inconsistencias y una entrada por gerencia firmante
     * (revisión registrada, si la hay).
     */
    private function filas(string $modelo, User $user, array $mapa, DetectorInconsistencias $detector, DetectorInconsistencias $previa, Collection $borradores): Collection
    {
        return $this->consultaPara($modelo, $user)->orderBy('nombre')->get()
            ->map(function (Model $elemento) use ($detector, $previa, $borradores, $mapa) {
                $cambios = ($borradores[$elemento->getMorphClass().'#'.$elemento->id] ?? collect())
                    ->each(fn (Actualizacion $a) => $a->setRelation('actualizable', $elemento));

                $gerencias = RevisionInicial::gerenciasDe($elemento, $mapa)->map(fn (Area $g) => [
                    'gerencia' => $g,
                    'revision' => $elemento->revisionesIniciales->firstWhere('gerencia_id', $g->id),
                ]);

                $firmadas = $gerencias->filter(fn ($g) => $g['revision']?->firmado_en)->count();

                return [
                    'elemento' => $elemento,
                    'hallazgos' => $detector->detectar($elemento),
                    'gerencias' => $gerencias,
                    // Propuestas en borrador (de empleados) y cómo quedaría validándolas.
                    'cambios' => $cambios,
                    'lineasCambios' => $cambios->mapWithKeys(fn (Actualizacion $a) => [$a->id => self::lineasDeCambio($a)]),
                    'hallazgosPrevios' => $cambios->isNotEmpty() ? $previa->detectar($elemento) : null,
                    'estado' => $gerencias->isNotEmpty() && $firmadas === $gerencias->count() ? 'firmado' : 'pendiente',
                ];
            });
    }

    private function filtrar(Collection $filas): Collection
    {
        return $filas
            ->when($this->search, fn ($c) => $c->filter(fn ($f) => str_contains(mb_strtolower($f['elemento']->nombre), mb_strtolower($this->search))))
            ->when($this->filtroRevision, fn ($c) => $c->where('estado', $this->filtroRevision))
            ->when($this->filtroGerencia, fn ($c) => $c->filter(fn ($f) => $f['gerencias']->contains(fn ($g) => $g['gerencia']->id === $this->filtroGerencia)))
            ->when($this->filtroHallazgos, fn ($c) => $c->filter(fn ($f) => match ($this->filtroHallazgos) {
                'errores' => DetectorInconsistencias::tieneErrores($f['hallazgos']),
                'advertencias' => ! DetectorInconsistencias::tieneErrores($f['hallazgos']) && $f['hallazgos'] !== [],
                'limpios' => $f['hallazgos'] === [],
                default => true,
            }))
            ->values();
    }

    /**
     * Avance por gerencia sobre los cinco tipos: cuántas firmas lleva, cuántas
     * están revisadas sin firmar, cuántas pendientes y cuántos elementos tienen
     * errores. Cuenta pares elemento-gerencia.
     */
    private function resumen(array $filasPorTipo): Collection
    {
        $resumen = [];

        foreach ($filasPorTipo as $filas) {
            foreach ($filas as $fila) {
                $conErrores = DetectorInconsistencias::tieneErrores($fila['hallazgos']);
                foreach ($fila['gerencias'] as ['gerencia' => $g, 'revision' => $r]) {
                    $resumen[$g->id] ??= ['gerencia' => $g, 'total' => 0, 'firmados' => 0, 'revisados' => 0, 'pendientes' => 0, 'con_errores' => 0];
                    $resumen[$g->id]['total']++;
                    $resumen[$g->id][match (true) {
                        (bool) $r?->firmado_en => 'firmados',
                        (bool) $r?->revisado_en => 'revisados',
                        default => 'pendientes',
                    }]++;
                    $resumen[$g->id]['con_errores'] += $conErrores ? 1 : 0;
                }
            }
        }

        return collect($resumen)->sortBy(fn ($r) => $r['gerencia']->nombre)->values();
    }

    public function render()
    {
        $user = Auth::user();
        $mapa = RevisionInicial::mapaGerencias();

        $detector = new DetectorInconsistencias;
        $previa = new DetectorInconsistencias(contarBorradores: true);
        $borradores = Actualizacion::query()->propuestasPendientes()
            ->where('estado_id', Estado::borrador()->id)
            ->with('user')
            ->latest()
            ->get()
            ->groupBy(fn (Actualizacion $a) => $a->actualizable_type.'#'.$a->actualizable_id);
        $filasPorTipo = collect(self::TIPOS)->map(fn ($cfg) => $this->filas($cfg['modelo'], $user, $mapa, $detector, $previa, $borradores));
        $filas = $this->filtrar($filasPorTipo[$this->tipo] ?? collect());

        $pagina = $this->getPage();
        $paginadas = new LengthAwarePaginator($filas->forPage($pagina, 15), $filas->count(), 15, $pagina);

        return view('livewire.auditoria.revision-inicial.index', [
            'tipos' => self::TIPOS,
            'filas' => $paginadas,
            'conteoPorTipo' => $filasPorTipo->map->count(),
            'resumen' => $this->resumen($filasPorTipo->all()),
            'soloLectura' => $this->miraTodo($user),
        ]);
    }
}
