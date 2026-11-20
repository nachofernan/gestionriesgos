<?php

namespace App\Services\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ValidacionMasivaService
{
    /**
     * Analiza dependencias de una entidad para mostrar en el modal. Además de
     * bloqueantes y opcionales devuelve los `impedimentos`: lo que la cascada no
     * puede resolver (un riesgo sin objetivo, un plan sin estimación), para que el
     * modal lo muestre al abrir y no deje confirmar. No reemplaza el chequeo de
     * ejecutar(), que sigue siendo la regla. Cada bloqueante trae `bloqueo` con lo
     * que le falta para procesarse (null si nada).
     * Tests: un_riesgo_sin_objetivo_muestra_el_impedimento_al_abrir_y_no_deja_confirmar,
     * un_riesgo_mitigar_sin_plan_muestra_el_impedimento_al_abrir,
     * un_plan_sin_estimacion_como_bloqueante_no_se_puede_seleccionar,
     * un_plan_sin_estimacion_como_entidad_principal_muestra_el_impedimento.
     *
     * @return array{bloqueantes: array, opcionales: array, impedimentos: list<string>}
     */
    public function analizar(Model $entidad, string $accion, User $usuario): array
    {
        $analisis = match (true) {
            $entidad instanceof Riesgo => $this->analizarRiesgo($entidad, $accion, $usuario),
            $entidad instanceof PlanAccion => $this->analizarPlanAccion($entidad, $accion, $usuario),
            default => ['bloqueantes' => [], 'opcionales' => [], 'impedimentos' => []],
        };

        $analisis['impedimentos'] = [...$this->motivosBloqueo($entidad), ...$analisis['impedimentos']];

        return $analisis;
    }

    /**
     * Ejecuta la validación/aprobación en cascada según las selecciones del usuario.
     *
     * @param  array  $bloqueantesSeleccionados  [['tipo' => 'objetivo', 'id' => 1, 'nivel' => 0], ...]
     * @param  array  $opcionalesSeleccionados  [['tipo' => 'control', 'id' => 5], ...]
     * @param  array|null  $comite  ['numero', 'fecha'] del comité que aprobó (D-039); lo comparten
     *                              todas las aprobaciones de la cascada. Ignorado al validar.
     * @return array{ok?: true, exitosos?: array, error?: string, fallidos?: array}
     */
    public function ejecutar(
        Model $entidad,
        string $accion,
        array $bloqueantesSeleccionados,
        array $opcionalesSeleccionados,
        User $usuario,
        ?array $comite = null
    ): array {
        $fallidos = [];
        $exitosos = [];
        $bloqueanteFallo = false;

        // Ordenar bloqueantes: nivel mayor primero (objetivos antes que riesgos, etc.)
        usort($bloqueantesSeleccionados, fn ($a, $b) => ($b['nivel'] ?? 0) <=> ($a['nivel'] ?? 0));

        DB::beginTransaction();
        try {
            // 1. Procesar bloqueantes
            foreach ($bloqueantesSeleccionados as $item) {
                $modelo = $this->resolverModelo($item['tipo'], $item['id']);
                if (! Gate::forUser($usuario)->allows($accion, $modelo)) {
                    $fallidos[] = ['nombre' => $modelo->nombre, 'razon' => 'Sin permisos'];
                    $bloqueanteFallo = true;

                    continue;
                }
                // Un plan sin estimación de su valor no se valida ni se aprueba (D-050).
                if ($motivos = $this->motivosBloqueo($modelo)) {
                    $fallidos[] = ['nombre' => $modelo->nombre, 'razon' => implode(' ', $motivos)];
                    $bloqueanteFallo = true;

                    continue;
                }
                $this->procesarTransicion($modelo, $accion, $usuario, $comite);
                $exitosos[] = $modelo->nombre;
            }

            if ($bloqueanteFallo) {
                DB::rollBack();

                return [
                    'error' => 'No se pudo completar la validación porque un prerequisito no pudo procesarse.',
                    'fallidos' => $fallidos,
                    'exitosos' => $exitosos,
                ];
            }

            // 2. Procesar opcionales (fallos no abortan)
            foreach ($opcionalesSeleccionados as $item) {
                $modelo = $this->resolverModelo($item['tipo'], $item['id']);
                if (! Gate::forUser($usuario)->allows($accion, $modelo)) {
                    $fallidos[] = ['nombre' => $modelo->nombre, 'razon' => 'Sin permisos'];

                    continue;
                }
                // Un control sin cuestionario se queda donde está (D-051); el resto sigue.
                if ($motivos = $this->motivosBloqueo($modelo)) {
                    $fallidos[] = ['nombre' => $modelo->nombre, 'razon' => implode(' ', $motivos)];

                    continue;
                }
                $this->procesarTransicion($modelo, $accion, $usuario, $comite);
                $exitosos[] = $modelo->nombre;
            }

            // 3. Prerequisitos duros del Riesgo, evaluados recién acá para que los
            // bloqueantes ya procesados en el paso 1 (ej. un plan de acción
            // seleccionado junto al riesgo) cuenten con su nuevo estado.
            if ($entidad instanceof Riesgo) {
                $motivos = $accion === 'validar'
                    ? $entidad->motivosBloqueoValidacion()
                    : $entidad->motivosBloqueoAprobacion();
                if (! empty($motivos)) {
                    DB::rollBack();

                    return ['error' => implode(' ', $motivos)];
                }
            }
            if ($motivos = $this->motivosBloqueo($entidad)) {
                DB::rollBack();

                return ['error' => implode(' ', $motivos)];
            }

            // 4. Procesar entidad principal
            $this->procesarTransicion($entidad, $accion, $usuario, $comite);
            $exitosos[] = $entidad->nombre;

            DB::commit();

            return ['ok' => true, 'exitosos' => $exitosos, 'fallidos' => $fallidos];

        } catch (\Throwable $e) {
            DB::rollBack();

            return ['error' => 'Error inesperado: '.$e->getMessage()];
        }
    }

    // -------------------------------------------------------
    // Análisis por tipo de entidad
    // -------------------------------------------------------

    private function analizarRiesgo(Riesgo $riesgo, string $accion, User $usuario): array
    {
        $bloqueantes = [];
        $opcionales = [];

        // Estados que satisfacen el prerequisito para cada acción
        $nivelRequerido = $accion === 'validar' ? ['validado', 'aprobado'] : ['aprobado'];
        // Estado en que deben estar los hijos opcionales para ser ofrecidos
        $estadoFuente = $accion === 'validar' ? 'borrador' : 'validado';

        $objetivos = $riesgo->objetivos()->with('estado')->get();
        $tieneObjetivoValido = $objetivos->contains(
            fn ($o) => in_array($o->estado?->nombre, $nivelRequerido)
        );

        $impedimentos = [];
        $verbo = $accion === 'validar' ? 'validar' : 'aprobar';

        if (! $tieneObjetivoValido) {
            foreach ($objetivos as $obj) {
                $bloqueantes[] = [
                    'tipo' => 'objetivo',
                    'id' => $obj->id,
                    'nombre' => $obj->nombre,
                    'estado' => $obj->estado?->nombre ?? 'borrador',
                    'puede_validar' => Gate::forUser($usuario)->allows($accion, $obj),
                    'interno' => $obj->interno,
                    'bloqueo' => null,
                    'nivel' => 0,
                    'parent_tipo' => null,
                    'parent_id' => null,
                ];
            }

            $impedimentos[] = match (true) {
                $objetivos->isEmpty() => "El riesgo no tiene objetivos asociados: hay que asociarle uno para poder {$verbo}lo.",
                ! $this->haySeleccionable($bloqueantes, 'objetivo') => "Ningún objetivo asociado se puede {$verbo} desde acá (ver el motivo de cada uno).",
                default => null,
            };
        }

        // Si la respuesta es mitigar, el plan de acción es un bloqueante (no
        // opcional como los controles): sin un plan en el estado requerido no se
        // puede validar/aprobar el riesgo (ver Riesgo::motivosBloqueoValidacion()
        // y motivosBloqueoAprobacion()).
        if ($riesgo->respuesta === RespuestaRiesgo::Mitigar) {
            $planes = $riesgo->planesAccion()->with('estado')->get();
            $tienePlanValido = $planes->contains(
                fn ($p) => in_array($p->estado?->nombre, $nivelRequerido)
            );

            if (! $tienePlanValido) {
                foreach ($planes as $plan) {
                    $bloqueantes[] = [
                        'tipo' => 'plan',
                        'id' => $plan->id,
                        'nombre' => $plan->nombre,
                        'estado' => $plan->estado?->nombre ?? 'borrador',
                        'puede_validar' => Gate::forUser($usuario)->allows($accion, $plan),
                        'interno' => $plan->interno,
                        'bloqueo' => implode(' ', $this->motivosBloqueo($plan)) ?: null,
                        'nivel' => 0,
                        'parent_tipo' => null,
                        'parent_id' => null,
                    ];
                }

                $impedimentos[] = match (true) {
                    $planes->isEmpty() => "Un riesgo con respuesta \"Reducir/Mitigar\" necesita un plan de acción asociado para poder {$verbo}lo.",
                    ! $this->haySeleccionable($bloqueantes, 'plan') => "Ningún plan de acción asociado se puede {$verbo} desde acá (ver el motivo de cada uno).",
                    default => null,
                };
            }
        }

        $impedimentos = array_values(array_filter($impedimentos));

        // Un hijo interno es gestión propia del área: no se ofrece (D-044).
        $controles = $riesgo->controles()->with('estado')->get();
        foreach ($controles as $ctrl) {
            if ($ctrl->estado?->nombre === $estadoFuente && ! $ctrl->interno) {
                $opcionales[] = [
                    'tipo' => 'control',
                    'id' => $ctrl->id,
                    'nombre' => $ctrl->nombre,
                    'estado' => $ctrl->estado?->nombre ?? 'borrador',
                    'puede_validar' => Gate::forUser($usuario)->allows($accion, $ctrl),
                ];
            }
        }

        return compact('bloqueantes', 'opcionales', 'impedimentos');
    }

    /** Si el grupo $tipo tiene algún bloqueante que el usuario puede procesar (permiso y sin bloqueo). */
    private function haySeleccionable(array $bloqueantes, string $tipo): bool
    {
        return collect($bloqueantes)->where('tipo', $tipo)
            ->contains(fn ($b) => $b['puede_validar'] && ! $b['bloqueo']);
    }

    /**
     * El plan de acción se valida/aprueba de forma independiente del estado del
     * riesgo: la causalidad es al revés (el riesgo depende del plan, ver
     * analizarRiesgo() y Riesgo::motivosBloqueoValidacion()/motivosBloqueoAprobacion()),
     * así que acá no hay bloqueantes — sólo tareas ofrecidas como opcionales. Lo
     * que sí exige el plan (su estimación, D-050) lo revisa ejecutar().
     */
    private function analizarPlanAccion(PlanAccion $plan, string $accion, User $usuario): array
    {
        $bloqueantes = [];
        $opcionales = [];

        $estadoFuente = $accion === 'validar' ? 'borrador' : 'validado';

        $tareas = $plan->tareas()->with('estado')->get();
        foreach ($tareas as $tarea) {
            if ($tarea->estado?->nombre === $estadoFuente && ! $tarea->interno) {
                $opcionales[] = [
                    'tipo' => 'tarea',
                    'id' => $tarea->id,
                    'nombre' => $tarea->nombre,
                    'estado' => $tarea->estado?->nombre ?? 'borrador',
                    'puede_validar' => Gate::forUser($usuario)->allows($accion, $tarea),
                ];
            }
        }

        $impedimentos = [];

        return compact('bloqueantes', 'opcionales', 'impedimentos');
    }

    // -------------------------------------------------------
    // Transiciones de estado
    // -------------------------------------------------------

    private function procesarTransicion(Model $entidad, string $accion, User $usuario, ?array $comite = null): void
    {
        if ($accion === 'validar') {
            $entidad->update(['estado_id' => Estado::validado()->id]);
            $entidad->actualizaciones()
                ->where('estado_id', Estado::borrador()->id)
                ->update(['estado_id' => Estado::validado()->id]);
            $entidad->actualizaciones()->create([
                'user_id' => $usuario->id,
                'mensaje' => 'Validado por '.$usuario->name,
                'estado_id' => Estado::validado()->id,
                'data' => ['tipo' => 'validacion'],
            ]);
        } else {
            // reorder('id') limpia el latest() de la relación y ordena por id en vez
            // de created_at: la columna es timestamp (precisión de 1 segundo) y dos
            // actualizaciones seguidas pueden empatar, así que created_at solo no
            // desempata de forma confiable. id es autoincremental y sí lo es. Acá
            // importa que la más reciente quede aplicada al final (gana).
            $pendientes = $entidad->actualizaciones()
                ->where('estado_id', Estado::validado()->id)
                ->reorder('id')
                ->get();
            foreach ($pendientes as $act) {
                $act->aplicarCambios($usuario);
                $act->update(['estado_id' => Estado::aprobado()->id]);
            }
            $entidad->update(['estado_id' => Estado::aprobado()->id]);
            $entidad->actualizaciones()->create([
                'user_id' => $usuario->id,
                'mensaje' => 'Aprobado por '.$usuario->name,
                'estado_id' => Estado::aprobado()->id,
                'data' => null,
            ] + Actualizacion::columnasComite($comite));
        }

        // La actividad del plan también registra el cambio de estado de sus tareas (D-031).
        if ($entidad instanceof Tarea) {
            $entidad->registrarEnSusPlanes($accion === 'validar' ? 'validada' : 'aprobada', $usuario);
        }
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function resolverModelo(string $tipo, int $id): Model
    {
        $class = match ($tipo) {
            'riesgo' => Riesgo::class,
            'plan' => PlanAccion::class,
            'objetivo' => Objetivo::class,
            'control' => Control::class,
            'tarea' => Tarea::class,
            default => throw new \InvalidArgumentException("Tipo desconocido: {$tipo}"),
        };

        return $class::findOrFail($id);
    }

    /**
     * Lo que le falta a un plan o un control para validarse o aprobarse: la
     * estimación del plan (D-050) o el cuestionario del control (D-051). Vacío
     * para el resto, y también si no le falta nada.
     * Tests: la_validacion_masiva_no_valida_un_plan_sin_estimacion,
     * la_validacion_masiva_no_valida_un_control_sin_cuestionario.
     *
     * @return list<string>
     */
    private function motivosBloqueo(Model $modelo): array
    {
        return $modelo instanceof PlanAccion || $modelo instanceof Control
            ? $modelo->motivosBloqueoValidacion()
            : [];
    }
}
