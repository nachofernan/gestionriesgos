{{--
    Partial reutilizable: badge de estado + botones de transición.
    Variables esperadas: $modelo, $routePrefix (ej. 'riesgos'), $item (el modelo instance)
--}}
@php
    $modelTipo = match($routePrefix ?? '') {
        'riesgos'   => 'riesgo',
        'planes'    => 'plan',
        'objetivos' => 'objetivo',
        'controles' => 'control',
        'tareas'    => 'tarea',
        default     => null,
    };
@endphp

<div class="flex items-center gap-2 flex-wrap">
    <x-auditoria.estado-badge :estado="$item->estado" />

    @can('validar', $item)
        @if($modelTipo)
            <button type="button"
                    onclick="Livewire.dispatch('abrir-validacion-cascada', { tipo: '{{ $modelTipo }}', id: {{ $item->id }}, accion: 'validar' })"
                    class="px-3 py-1 bg-blue-50 text-blue-700 text-xs font-bold rounded-lg hover:bg-blue-100 transition-colors">
                Validar
            </button>
        @else
            <form action="{{ route('auditoria.' . $routePrefix . '.validar', $item) }}" method="POST" class="inline">
                @csrf
                <button type="submit"
                        class="px-3 py-1 bg-blue-50 text-blue-700 text-xs font-bold rounded-lg hover:bg-blue-100 transition-colors">
                    Validar
                </button>
            </form>
        @endif
    @endcan

    @can('activar', $item)
        @if($modelTipo)
            <button type="button"
                    onclick="Livewire.dispatch('abrir-validacion-cascada', { tipo: '{{ $modelTipo }}', id: {{ $item->id }}, accion: 'activar' })"
                    class="px-3 py-1 bg-green-50 text-green-700 text-xs font-bold rounded-lg hover:bg-green-100 transition-colors">
                Activar
            </button>
        @else
            <form action="{{ route('auditoria.' . $routePrefix . '.activar', $item) }}" method="POST" class="inline">
                @csrf
                <button type="submit"
                        class="px-3 py-1 bg-green-50 text-green-700 text-xs font-bold rounded-lg hover:bg-green-100 transition-colors">
                    Activar
                </button>
            </form>
        @endif
    @endcan

    @can('rechazar', $item)
        <form action="{{ route('auditoria.' . $routePrefix . '.rechazar', $item) }}" method="POST" class="inline"
              onsubmit="return confirm('¿Rechazar este elemento?')">
            @csrf
            <button type="submit"
                    class="px-3 py-1 bg-red-50 text-red-700 text-xs font-bold rounded-lg hover:bg-red-100 transition-colors">
                Rechazar
            </button>
        </form>
    @endcan
</div>
