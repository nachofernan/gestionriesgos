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

{{-- Borrador interno (D-044): lo pone o lo saca quien edita el borrador. --}}
@if($modelTipo && $item->estado?->nombre === 'borrador')
    @if(auth()->user()->can('view', $item) && auth()->user()->can('update', $item))
        <form action="{{ route('auditoria.interno.alternar', [$modelTipo, $item->id]) }}" method="POST" class="inline">
            @csrf
            <button type="submit"
                    title="{{ $item->interno ? 'Vuelve al circuito: queda en borrador, en la bandeja del gerente' : 'Gestión propia del área: sale de Pendientes y no se valida' }}"
                    class="{{ $btn }} {{ $item->interno ? 'bg-violet-600 text-white hover:bg-violet-700' : 'bg-violet-50 text-violet-700 hover:bg-violet-100' }}">
                {{ $item->interno ? 'Quitar de interno' : 'Marcar interno' }}
            </button>
        </form>
    @endif
@endif

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

{{-- Gerente sobre un borrador, o comité sobre lo validado (D-031). Pide motivo. --}}
@can('rechazar', $item)
    <x-auditoria.boton-rechazar :url="route('auditoria.' . $routePrefix . '.rechazar', $item)"
        :clase="$btn . ' bg-red-50 text-red-700 hover:bg-red-100'" />
@endcan
