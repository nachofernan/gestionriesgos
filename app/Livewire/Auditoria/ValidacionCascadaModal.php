<?php

namespace App\Livewire\Auditoria;

use App\Models\Auditoria\Control;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Services\Auditoria\ValidacionMasivaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de validación/aprobación en cascada: antes de validar o aprobar una
 * entidad, calcula (vía ValidacionMasivaService) qué entidades relacionadas son
 * prerequisitos bloqueantes u opcionales, deja elegir cuáles incluir, y ejecuta
 * la operación masiva sobre todo lo seleccionado.
 */
class ValidacionCascadaModal extends Component
{
    public bool $abierto = false;

    public string $tipo = '';

    public int $entidadId = 0;

    public string $accion = '';

    public string $nombre = '';

    /**
     * Si viene en true (sólo lo manda la pantalla de Pendientes), confirmar() no
     * redirige: cierra el modal y avisa por evento de navegador para que la fila
     * se marque como resuelta sin recargar el resto del listado. En cualquier
     * otro lugar (los show() de cada entidad) el comportamiento no cambia.
     */
    public bool $sinRedireccion = false;

    /** @var array<int, array{tipo:string, id:int, nombre:string, estado:string, puede_validar:bool, nivel:int}> */
    public array $bloqueantes = [];

    /** @var array<int, array{tipo:string, id:int, nombre:string, estado:string, puede_validar:bool}> */
    public array $opcionales = [];

    /** key: 'tipo:id', value: bool */
    public array $seleccionados = [];

    public ?string $error = null;

    /**
     * Contador que se incrementa en CADA intento de toggle, se acepte o se
     * rechace. Los checkboxes lo usan como sufijo de su wire:key: cuando el
     * servidor rechaza un cambio, $seleccionados no varía, así que ese valor
     * por sí solo no alcanza como key — sin este contador, Livewire no tiene
     * ninguna señal de que el nodo cambió y no lo recrea, dejando la propiedad
     * `checked` del navegador (ya invertida por el clic nativo, antes de que la
     * respuesta del servidor vuelva) desincronizada del estado real.
     */
    public int $version = 0;

    /**
     * Escucha el evento global 'abrir-validacion-cascada' (disparado desde las
     * vistas de detalle) para abrir el modal ya con el análisis de prerequisitos
     * resuelto y pre-seleccionados los items que el usuario está autorizado a validar.
     */
    #[On('abrir-validacion-cascada')]
    public function abrir(string $tipo, int $id, string $accion, bool $sinRedireccion = false): void
    {
        $this->reset();
        $this->sinRedireccion = $sinRedireccion;

        $entidad = $this->resolverModelo($tipo, $id);
        if (! $entidad) {
            return;
        }

        // Verificar permiso sobre la entidad principal
        if (! Gate::forUser(Auth::user())->allows($accion, $entidad)) {
            return;
        }

        $service = app(ValidacionMasivaService::class);
        $analisis = $service->analizar($entidad, $accion, Auth::user());

        $this->tipo = $tipo;
        $this->entidadId = $id;
        $this->accion = $accion;
        $this->nombre = $entidad->nombre;
        $this->bloqueantes = $analisis['bloqueantes'];
        $this->opcionales = $analisis['opcionales'];

        // Pre-seleccionar todos los items autorizados
        foreach ($this->bloqueantes as $item) {
            $this->seleccionados[$item['tipo'].':'.$item['id']] = $item['puede_validar'];
        }
        foreach ($this->opcionales as $item) {
            $this->seleccionados[$item['tipo'].':'.$item['id']] = $item['puede_validar'];
        }

        $this->abierto = true;
    }

    /**
     * Alterna la selección de un item, bloqueando la deselección si eso dejaría
     * el grupo de ese item (objetivo, plan, ...) sin ningún bloqueante
     * seleccionado. Cada grupo es un prerequisito independiente — no alcanza
     * con que quede seleccionado algo de otro grupo (ver confirmar()).
     */
    public function toggleSeleccion(string $tipoId): void
    {
        $this->error = null;
        $this->version++;
        $actual = $this->seleccionados[$tipoId] ?? false;

        if ($actual) {
            $item = collect($this->bloqueantes)->first(fn ($b) => $tipoId === $b['tipo'].':'.$b['id']);

            if ($item) {
                $grupo = $item['tipo'];

                $quedarianSeleccionadosEnGrupo = collect($this->bloqueantes)
                    ->where('tipo', $grupo)
                    ->filter(function ($b) use ($tipoId) {
                        $key = $b['tipo'].':'.$b['id'];

                        return $key !== $tipoId && ($this->seleccionados[$key] ?? false);
                    });

                if ($quedarianSeleccionadosEnGrupo->isEmpty()) {
                    $this->error = 'Debe mantener seleccionado al menos '.$this->labelGrupo($grupo).'.';

                    return;
                }
            }
        }

        $this->seleccionados[$tipoId] = ! $actual;
    }

    /**
     * Ejecuta la validación/aprobación en cascada (vía ValidacionMasivaService)
     * sobre la entidad principal más los bloqueantes/opcionales seleccionados.
     * Los fallidos por falta de permiso no frenan la operación: se reportan
     * aparte en el mensaje final.
     */
    public function confirmar()
    {
        $this->error = null;

        $grupoFaltante = $this->grupoBloqueanteSinSeleccion();
        if ($grupoFaltante) {
            $this->error = 'Debe seleccionar al menos '.$this->labelGrupo($grupoFaltante).' autorizado para continuar.';

            return;
        }

        $entidad = $this->resolverModelo($this->tipo, $this->entidadId);
        if (! $entidad) {
            $this->error = 'No se encontró el elemento a validar.';

            return;
        }

        $bloqueantesSeleccionados = collect($this->bloqueantes)
            ->filter(fn ($b) => $this->seleccionados[$b['tipo'].':'.$b['id']] ?? false)
            ->values()->toArray();

        $opcionalesSeleccionados = collect($this->opcionales)
            ->filter(fn ($o) => $this->seleccionados[$o['tipo'].':'.$o['id']] ?? false)
            ->values()->toArray();

        $service = app(ValidacionMasivaService::class);
        $resultado = $service->ejecutar(
            $entidad,
            $this->accion,
            $bloqueantesSeleccionados,
            $opcionalesSeleccionados,
            Auth::user()
        );

        if (isset($resultado['ok'])) {
            $this->abierto = false;
            $mensajeAccion = $this->accion === 'validar' ? 'validado' : 'aprobado';
            $total = count($resultado['exitosos'] ?? []);
            $ok = $total > 1
                ? "{$total} elementos {$mensajeAccion}s correctamente."
                : ucfirst($mensajeAccion).' correctamente.';

            if (! empty($resultado['fallidos'])) {
                $nombresF = implode(', ', array_column($resultado['fallidos'], 'nombre'));
                $ok .= " (Sin permisos para: {$nombresF})";
            }

            if ($this->sinRedireccion) {
                $this->dispatch('cascada-procesada', tipo: $this->tipo, id: $this->entidadId, mensaje: $ok);

                return null;
            }

            session()->flash('ok', $ok);

            // redirect()->back() no sirve acá: Livewire sólo intercepta to()/away()
            // (back() llama a createRedirect() directo en Laravel, sin pasar por el
            // wrapper de Livewire), así que nunca redirigía de verdad al usar el modal.
            return redirect()->to(url()->previous());
        }

        $this->error = $resultado['error'] ?? 'Error inesperado.';
    }

    public function cancelar(): void
    {
        $this->reset();
    }

    /**
     * Si algún grupo de bloqueantes (objetivo, plan, ...) no tiene ningún item
     * autorizado seleccionado, devuelve ese grupo (para el mensaje de error);
     * null si todos los grupos están satisfechos. Cada grupo es un prerequisito
     * independiente — no alcanza con que quede seleccionado algo de otro grupo.
     * Usado por confirmar() (gate real) y puedeConfirmar() (gate visual del botón).
     */
    private function grupoBloqueanteSinSeleccion(): ?string
    {
        foreach (collect($this->bloqueantes)->pluck('tipo')->unique() as $grupo) {
            $haySeleccionadoDelGrupo = collect($this->bloqueantes)
                ->where('tipo', $grupo)
                ->contains(fn ($b) => ($this->seleccionados[$b['tipo'].':'.$b['id']] ?? false) && $b['puede_validar']);

            if (! $haySeleccionadoDelGrupo) {
                return $grupo;
            }
        }

        return null;
    }

    /**
     * Gate visual para deshabilitar el botón "Confirmar" en la vista: no
     * reemplaza el chequeo real de confirmar() (que sigue corriendo aunque
     * este método fallara o se bypasseara), es una señal anticipada para que
     * el usuario no llegue a clickear algo que el servidor va a rechazar.
     */
    public function puedeConfirmar(): bool
    {
        return $this->grupoBloqueanteSinSeleccion() === null;
    }

    /** Lista ordenada para mostrar en el resumen de ejecución */
    public function resumen(): array
    {
        $lista = [];

        // Bloqueantes seleccionados (ordenados: nivel mayor primero)
        $bloqueantesOrdenados = collect($this->bloqueantes)
            ->filter(fn ($b) => $this->seleccionados[$b['tipo'].':'.$b['id']] ?? false)
            ->sortByDesc(fn ($b) => $b['nivel'] ?? 0)
            ->values();

        foreach ($bloqueantesOrdenados as $item) {
            $lista[] = ['nombre' => $item['nombre'], 'tipo' => $item['tipo']];
        }

        // Opcionales seleccionados
        foreach ($this->opcionales as $item) {
            if ($this->seleccionados[$item['tipo'].':'.$item['id']] ?? false) {
                $lista[] = ['nombre' => $item['nombre'], 'tipo' => $item['tipo']];
            }
        }

        // Entidad principal
        if ($this->nombre) {
            $lista[] = ['nombre' => $this->nombre, 'tipo' => $this->tipo, 'principal' => true];
        }

        return $lista;
    }

    public function render()
    {
        return view('livewire.auditoria.validacion-cascada-modal');
    }

    private function resolverModelo(string $tipo, int $id): ?object
    {
        $class = match ($tipo) {
            'riesgo' => Riesgo::class,
            'plan' => PlanAccion::class,
            'objetivo' => Objetivo::class,
            'control' => Control::class,
            'tarea' => Tarea::class,
            default => null,
        };

        return $class ? $class::find($id) : null;
    }

    /** Nombre legible del grupo de bloqueantes para los mensajes de error (ver blade para la etiqueta visual). */
    private function labelGrupo(string $tipo): string
    {
        return match ($tipo) {
            'objetivo' => 'un objetivo',
            'plan' => 'un plan de acción',
            'riesgo' => 'un riesgo',
            default => 'un '.$tipo,
        };
    }
}
