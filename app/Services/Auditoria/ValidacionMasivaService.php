<?php

namespace App\Services\Auditoria;

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
            $entidad instanceof Riesgo     => $this->analizarRiesgo($entidad, $accion, $usuario),
            $entidad instanceof PlanAccion => $this->analizarPlanAccion($entidad, $accion, $usuario),
            default                        => ['bloqueantes' => [], 'opcionales' => []],
        };
    }

    /**
     * Ejecuta la validación/aprobación en cascada según las selecciones del usuario.
     *
     * @param array $bloqueantesSeleccionados  [['tipo' => 'objetivo', 'id' => 1, 'nivel' => 0], ...]
     * @param array $opcionalesSeleccionados   [['tipo' => 'control', 'id' => 5], ...]
     * @return array{ok?: true, exitosos?: array, error?: string, fallidos?: array}
     */
    public function ejecutar(
        Model $entidad,
        string $accion,
        array $bloqueantesSeleccionados,
        array $opcionalesSeleccionados,
        User $usuario
    ): array {
        if ($entidad instanceof Riesgo && $accion === 'validar') {
            $motivos = $entidad->motivosBloqueoValidacion();
            if (!empty($motivos)) {
                return ['error' => implode(' ', $motivos)];
            }
        }

        $fallidos = [];
        $exitosos = [];
        $bloqueanteFallo = false;

        // Ordenar bloqueantes: nivel mayor primero (objetivos antes que riesgos, etc.)
        usort($bloqueantesSeleccionados, fn($a, $b) => ($b['nivel'] ?? 0) <=> ($a['nivel'] ?? 0));

        DB::beginTransaction();
        try {
            // 1. Procesar bloqueantes
            foreach ($bloqueantesSeleccionados as $item) {
                $modelo = $this->resolverModelo($item['tipo'], $item['id']);
                if (!Gate::forUser($usuario)->allows($accion, $modelo)) {
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
                    'error'    => 'No se pudo completar la validación porque un prerequisito no pudo procesarse.',
                    'fallidos' => $fallidos,
                    'exitosos' => $exitosos,
                ];
            }

            // 2. Procesar opcionales (fallos no abortan)
            foreach ($opcionalesSeleccionados as $item) {
                $modelo = $this->resolverModelo($item['tipo'], $item['id']);
                if (!Gate::forUser($usuario)->allows($accion, $modelo)) {
                    $fallidos[] = ['nombre' => $modelo->nombre, 'razon' => 'Sin permisos'];
                    continue;
                }
                $this->procesarTransicion($modelo, $accion, $usuario);
                $exitosos[] = $modelo->nombre;
            }

            // 3. Procesar entidad principal
            $this->procesarTransicion($entidad, $accion, $usuario);
            $exitosos[] = $entidad->nombre;

            DB::commit();
            return ['ok' => true, 'exitosos' => $exitosos, 'fallidos' => $fallidos];

        } catch (\Throwable $e) {
            DB::rollBack();
            return ['error' => 'Error inesperado: ' . $e->getMessage()];
        }
    }

    // -------------------------------------------------------
    // Análisis por tipo de entidad
    // -------------------------------------------------------

    private function analizarRiesgo(Riesgo $riesgo, string $accion, User $usuario): array
    {
        $bloqueantes = [];
        $opcionales  = [];

        // Estados que satisfacen el prerequisito para cada acción
        $nivelRequerido = $accion === 'validar' ? ['validado', 'aprobado'] : ['aprobado'];
        // Estado en que deben estar los hijos opcionales para ser ofrecidos
        $estadoFuente = $accion === 'validar' ? 'borrador' : 'validado';

        $objetivos = $riesgo->objetivos()->with('estado')->get();
        $tieneObjetivoValido = $objetivos->contains(
            fn($o) => in_array($o->estado?->nombre, $nivelRequerido)
        );

        if (!$tieneObjetivoValido && $objetivos->isNotEmpty()) {
            foreach ($objetivos as $obj) {
                $bloqueantes[] = [
                    'tipo'          => 'objetivo',
                    'id'            => $obj->id,
                    'nombre'        => $obj->nombre,
                    'estado'        => $obj->estado?->nombre ?? 'borrador',
                    'puede_validar' => Gate::forUser($usuario)->allows($accion, $obj),
                    'nivel'         => 0,
                    'parent_tipo'   => null,
                    'parent_id'     => null,
                ];
            }
        }

        $controles = $riesgo->controles()->with('estado')->get();
        foreach ($controles as $ctrl) {
            if ($ctrl->estado?->nombre === $estadoFuente) {
                $opcionales[] = [
                    'tipo'          => 'control',
                    'id'            => $ctrl->id,
                    'nombre'        => $ctrl->nombre,
                    'estado'        => $ctrl->estado?->nombre ?? 'borrador',
                    'puede_validar' => Gate::forUser($usuario)->allows($accion, $ctrl),
                ];
            }
        }

        return compact('bloqueantes', 'opcionales');
    }

    private function analizarPlanAccion(PlanAccion $plan, string $accion, User $usuario): array
    {
        $bloqueantes = [];
        $opcionales  = [];

        $nivelRequerido = $accion === 'validar' ? ['validado', 'aprobado'] : ['aprobado'];
        $estadoFuente   = $accion === 'validar' ? 'borrador' : 'validado';

        $riesgos = $plan->riesgos()->with(['estado', 'objetivos.estado'])->get();
        $tieneRiesgoValido = $riesgos->contains(
            fn($r) => in_array($r->estado?->nombre, $nivelRequerido)
        );

        if (!$tieneRiesgoValido && $riesgos->isNotEmpty()) {
            $objetivosAgregados = [];

            foreach ($riesgos as $riesgo) {
                $bloqueantes[] = [
                    'tipo'          => 'riesgo',
                    'id'            => $riesgo->id,
                    'nombre'        => $riesgo->nombre,
                    'estado'        => $riesgo->estado?->nombre ?? 'borrador',
                    'puede_validar' => Gate::forUser($usuario)->allows($accion, $riesgo),
                    'nivel'         => 0,
                    'parent_tipo'   => null,
                    'parent_id'     => null,
                ];

                // Verificar si el riesgo también necesita sus objetivos
                $tieneObjetivoValido = $riesgo->objetivos->contains(
                    fn($o) => in_array($o->estado?->nombre, $nivelRequerido)
                );
                if (!$tieneObjetivoValido) {
                    foreach ($riesgo->objetivos as $obj) {
                        if (in_array($obj->id, $objetivosAgregados)) continue;
                        $objetivosAgregados[] = $obj->id;
                        $bloqueantes[] = [
                            'tipo'          => 'objetivo',
                            'id'            => $obj->id,
                            'nombre'        => $obj->nombre,
                            'estado'        => $obj->estado?->nombre ?? 'borrador',
                            'puede_validar' => Gate::forUser($usuario)->allows($accion, $obj),
                            'nivel'         => 1,
                            'parent_tipo'   => 'riesgo',
                            'parent_id'     => $riesgo->id,
                        ];
                    }
                }
            }
        }

        $tareas = $plan->tareas()->with('estado')->get();
        foreach ($tareas as $tarea) {
            if ($tarea->estado?->nombre === $estadoFuente) {
                $opcionales[] = [
                    'tipo'          => 'tarea',
                    'id'            => $tarea->id,
                    'nombre'        => $tarea->nombre,
                    'estado'        => $tarea->estado?->nombre ?? 'borrador',
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
                'user_id'   => $usuario->id,
                'mensaje'   => 'Validado por ' . $usuario->name,
                'estado_id' => Estado::validado()->id,
                'data'      => ['tipo' => 'validacion'],
            ]);
        } else {
            $pendientes = $entidad->actualizaciones()
                ->where('estado_id', Estado::validado()->id)
                ->get();
            foreach ($pendientes as $act) {
                $this->aplicarCambiosActualizacion($act, $entidad);
                $act->update(['estado_id' => Estado::aprobado()->id]);
            }
            $entidad->update(['estado_id' => Estado::aprobado()->id]);
            $entidad->actualizaciones()->create([
                'user_id'   => $usuario->id,
                'mensaje'   => 'Aprobado por ' . $usuario->name,
                'estado_id' => Estado::aprobado()->id,
                'data'      => null,
            ]);
        }
    }

    private function aplicarCambiosActualizacion($actualizacion, Model $model): void
    {
        $data = $actualizacion->data ?? [];
        if (empty($data)) return;

        $tipo = $data['tipo'] ?? null;
        if ($tipo !== null && $tipo !== 'cambio') return;

        if (!empty($data['campos'])) {
            $model->update($data['campos']);
        }

        if (!empty($data['relaciones'])) {
            foreach ($data['relaciones'] as $relacion => $ops) {
                if (isset($ops['sync']))   $model->$relacion()->sync($ops['sync']);
                if (isset($ops['attach'])) $model->$relacion()->attach($ops['attach']);
                if (isset($ops['detach'])) $model->$relacion()->detach($ops['detach']);
            }
        }
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function resolverModelo(string $tipo, int $id): Model
    {
        $class = match ($tipo) {
            'riesgo'   => Riesgo::class,
            'plan'     => PlanAccion::class,
            'objetivo' => Objetivo::class,
            'control'  => Control::class,
            'tarea'    => Tarea::class,
            default    => throw new \InvalidArgumentException("Tipo desconocido: {$tipo}"),
        };

        return $class::findOrFail($id);
    }
}
