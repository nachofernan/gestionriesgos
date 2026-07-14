{{--
    Partial reutilizable: botones de transición de estado.
    El badge de estado NO va acá — vive junto al título de cada vista show.
    Variables esperadas: $routePrefix (ej. 'riesgos'), $item (el modelo instance)
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
    // Estilo compartido de los botones para que todos tengan el mismo peso visual.
    $btn = 'px-4 py-2 text-sm font-bold rounded-xl transition-colors';
@endphp

@can('validar', $item)
    @if($modelTipo)
        <button type="button"
                onclick="Livewire.dispatch('abrir-validacion-cascada', { tipo: '{{ $modelTipo }}', id: {{ $item->id }}, accion: 'validar' })"
                class="{{ $btn }} bg-blue-50 text-blue-700 hover:bg-blue-100">
            Validar
        </button>
    @else
        <form action="{{ route('auditoria.' . $routePrefix . '.validar', $item) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="{{ $btn }} bg-blue-50 text-blue-700 hover:bg-blue-100">
                Validar
            </button>
        </form>
    @endif
@endcan

@can('aprobar', $item)
    @if($modelTipo)
        <button type="button"
                onclick="Livewire.dispatch('abrir-validacion-cascada', { tipo: '{{ $modelTipo }}', id: {{ $item->id }}, accion: 'aprobar' })"
                class="{{ $btn }} bg-green-50 text-green-700 hover:bg-green-100">
            Aprobar
        </button>
    @else
        <form action="{{ route('auditoria.' . $routePrefix . '.aprobar', $item) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="{{ $btn }} bg-green-50 text-green-700 hover:bg-green-100">
                Aprobar
            </button>
        </form>
    @endif
@endcan

@can('rechazar', $item)
    <form action="{{ route('auditoria.' . $routePrefix . '.rechazar', $item) }}" method="POST" class="inline"
          onsubmit="return confirm('¿Rechazar este elemento?')">
        @csrf
        <button type="submit" class="{{ $btn }} bg-red-50 text-red-700 hover:bg-red-100">
            Rechazar
        </button>
    </form>
@endcan
