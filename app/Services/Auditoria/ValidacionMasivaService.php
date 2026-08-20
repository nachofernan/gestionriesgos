<?php

namespace App\Services\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
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
     * Analiza dependencias de una entidad para mostrar en el modal.
     *
     * @return array{bloqueantes: array, opcionales: array}
     */
    public function analizar(Model $entidad, string $accion, User $usuario): array
    {
        return match (true) {
            $entidad instanceof Riesgo => $this->analizarRiesgo($entidad, $accion, $usuario),
            $entidad instanceof PlanAccion => $this->analizarPlanAccion($entidad, $accion, $usuario),
            default => ['bloqueantes' => [], 'opcionales' => []],
        };
    }

    /**
     * Ejecuta la validación/aprobación en cascada según las selecciones del usuario.
     *
     * @param  array  $bloqueantesSeleccionados  [['tipo' => 'objetivo', 'id' => 1, 'nivel' => 0], ...]
     * @param  array  $opcionalesSeleccionados  [['tipo' => 'control', 'id' => 5], ...]
     * @return array{ok?: true, exitosos?: array, error?: string, fallidos?: array}
     */
    public function ejecutar(
        Model $entidad,
        string $accion,
        array $bloqueantesSeleccionados,
        array $opcionalesSeleccionados,
        User $usuario
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
                $this->procesarTransicion($modelo, $accion, $usuario);
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
                $this->procesarTransicion($modelo, $accion, $usuario);
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

            // 4. Procesar entidad principal
            $this->procesarTransicion($entidad, $accion, $usuario);
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

        if (! $tieneObjetivoValido && $objetivos->isNotEmpty()) {
            foreach ($objetivos as $obj) {
                $bloqueantes[] = [
                    'tipo' => 'objetivo',
                    'id' => $obj->id,
                    'nombre' => $obj->nombre,
                    'estado' => $obj->estado?->nombre ?? 'borrador',
                    'puede_validar' => Gate::forUser($usuario)->allows($accion, $obj),
                    'nivel' => 0,
                    'parent_tipo' => null,
                    'parent_id' => null,
                ];
            }
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

            if (! $tienePlanValido && $planes->isNotEmpty()) {
                foreach ($planes as $plan) {
                    $bloqueantes[] = [
                        'tipo' => 'plan',
                        'id' => $plan->id,
                        'nombre' => $plan->nombre,
                        'estado' => $plan->estado?->nombre ?? 'borrador',
                        'puede_validar' => Gate::forUser($usuario)->allows($accion, $plan),
                        'nivel' => 0,
                        'parent_tipo' => null,
                        'parent_id' => null,
                    ];
                }
            }
        }

        $controles = $riesgo->controles()->with('estado')->get();
        foreach ($controles as $ctrl) {
            if ($ctrl->estado?->nombre === $estadoFuente) {
                $opcionales[] = [
                    'tipo' => 'control',
                    'id' => $ctrl->id,
                    'nombre' => $ctrl->nombre,
                    'estado' => $ctrl->estado?->nombre ?? 'borrador',
                    'puede_validar' => Gate::forUser($usuario)->allows($accion, $ctrl),
                ];
            }
        }

        return compact('bloqueantes', 'opcionales');
    }

    /**
     * El plan de acción se valida/aprueba de forma independiente del estado del
     * riesgo: la causalidad es al revés (el riesgo depende del plan, ver
     * analizarRiesgo() y Riesgo::motivosBloqueoValidacion()/motivosBloqueoAprobacion()),
     * así que acá no hay bloqueantes — sólo tareas ofrecidas como opcionales.
     */
    private function analizarPlanAccion(PlanAccion $plan, string $accion, User $usuario): array
    {
        $bloqueantes = [];
        $opcionales = [];

        $estadoFuente = $accion === 'validar' ? 'borrador' : 'validado';

        $tareas = $plan->tareas()->with('estado')->get();
        foreach ($tareas as $tarea) {
            if ($tarea->estado?->nombre === $estadoFuente) {
                $opcionales[] = [
                    'tipo' => 'tarea',
                    'id' => $tarea->id,
                    'nombre' => $tarea->nombre,
                    'estado' => $tarea->estado?->nombre ?? 'borrador',
                    'puede_validar' => Gate::forUser($usuario)->allows($accion, $tarea),
                ];
            }
        }

        return compact('bloqueantes', 'opcionales');
    }

    // -------------------------------------------------------
    // Transiciones de estado
    // -------------------------------------------------------

    private function procesarTransicion(Model $entidad, string $accion, User $usuario): void
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
                $act->aplicarCambios();
                $act->update(['estado_id' => Estado::aprobado()->id]);
            }
            $entidad->update(['estado_id' => Estado::aprobado()->id]);
            $entidad->actualizaciones()->create([
                'user_id' => $usuario->id,
                'mensaje' => 'Aprobado por '.$usuario->name,
                'estado_id' => Estado::aprobado()->id,
                'data' => null,
            ]);
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
}
