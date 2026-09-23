@extends('layouts.auditoria')
@section('title', $riesgo->nombre)

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('auditoria.riesgos.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl font-extrabold text-gray-900">{{ $riesgo->nombre }}</h1>
                    <x-auditoria.estado-badge :estado="$riesgo->estado" />
                </div>
                <p class="text-sm text-gray-500">{{ $riesgo->tipoRiesgo->nombre ?? '—' }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @include('auditoria.partials.estado-acciones', ['item' => $riesgo, 'routePrefix' => 'riesgos'])
            @if($riesgo->estado?->nombre === 'borrador' || !$riesgo->estado)
                <a href="{{ route('auditoria.riesgos.edit', $riesgo) }}"
                   class="px-4 py-2 bg-indigo-50 text-indigo-700 text-sm font-bold rounded-xl hover:bg-indigo-100 transition-colors">
                    Editar
                </a>
            @endif
            <form action="{{ route('auditoria.riesgos.destroy', $riesgo) }}" method="POST"
                  onsubmit="return confirm('¿Eliminar este riesgo? Esta acción no se puede deshacer.')">
                @csrf @method('DELETE')
                <button type="submit"
                    class="px-4 py-2 bg-red-50 text-red-700 text-sm font-bold rounded-xl hover:bg-red-100 transition-colors">
                    Eliminar
                </button>
            </form>
        </div>
    </div>

    {{-- Grid de dos columnas iguales --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Columna izquierda: Info + Objetivos + Controles --}}
        <div class="space-y-4">

            {{-- Información del riesgo --}}
            @livewire('auditoria.riesgo.show.info-riesgo', ['riesgo' => $riesgo])

            {{-- Gerencias --}}
            @livewire('auditoria.riesgo.show.gestion-areas', ['riesgo' => $riesgo])

            {{-- Objetivos --}}
            @livewire('auditoria.riesgo.show.gestion-objetivos', ['riesgo' => $riesgo])

            {{-- Controles --}}
            @livewire('auditoria.riesgo.show.gestion-controles', ['riesgo' => $riesgo])

        </div>

        {{-- Columna derecha: Planes de Acción + Actualizaciones --}}
        <div class="space-y-4">
            @livewire('auditoria.riesgo.show.gestion-planes', ['riesgo' => $riesgo])
            @livewire('auditoria.actualizaciones.gestion-actualizaciones', ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
        </div>

    </div>

</div>

@livewire('auditoria.validacion-cascada-modal')
@endsection
