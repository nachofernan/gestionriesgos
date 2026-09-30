@extends('layouts.auditoria')
@section('title', $planAccion->nombre)

@section('content')
@php
    $gerencia = $planAccion->area?->gerencia();
@endphp
<div class="space-y-5">

    {{-- Encabezado --}}
    <header class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div class="flex items-start gap-3 min-w-0">
            <a href="{{ route('auditoria.planes.index') }}" title="Volver a planes"
               class="mt-1.5 shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-400 hover:text-gray-700 hover:bg-white transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </a>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap text-xs">
                    <span class="font-bold text-gray-500 uppercase">Plan de acción</span>
                    <x-auditoria.estado-badge :estado="$planAccion->estado" size="sm" />
                    @if($planAccion->esta_vencido)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700">Vencido</span>
                    @endif
                </div>
                <h1 class="mt-1 text-2xl sm:text-[28px] font-black tracking-tight text-gray-900 leading-tight">{{ $planAccion->nombre }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ collect([$planAccion->area?->nombre, $gerencia && $gerencia->id !== $planAccion->area?->id ? $gerencia->nombre : null])->filter()->join(' · ') ?: 'Sin área' }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap lg:justify-end">
            @include('auditoria.partials.estado-acciones', ['item' => $planAccion, 'routePrefix' => 'planes'])

            @can('delete', $planAccion)
                <div class="relative" x-data="{ abierto: false }" x-on:click.outside="abierto = false">
                    <button type="button" x-on:click="abierto = !abierto" title="Más acciones"
                        class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-white border border-gray-200 text-gray-500 hover:text-gray-800 hover:bg-gray-50 transition-colors">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                    </button>
                    <div x-show="abierto" x-cloak x-transition.origin.top.right
                         class="absolute right-0 mt-2 w-44 rounded-xl bg-white border border-gray-200 shadow-lg py-1 z-30">
                        <form action="{{ route('auditoria.planes.destroy', $planAccion) }}" method="POST"
                              onsubmit="return confirm('¿Eliminar este plan de acción? Esta acción no se puede deshacer.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                Eliminar plan
                            </button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

        {{-- Qué es el plan, con qué tareas avanza y qué riesgos mitiga --}}
        <div class="lg:col-span-8 space-y-5 order-2 lg:order-1">
            @livewire('auditoria.plan-accion.show.ficha-plan', ['plan' => $planAccion])
            @livewire('auditoria.plan-accion.show.gestion-tareas', ['plan' => $planAccion])
            @livewire('auditoria.plan-accion.show.riesgos-plan', ['plan' => $planAccion])
            @livewire('auditoria.actualizaciones.conversacion', ['modelType' => 'plan', 'modelId' => $planAccion->id])
        </div>

        {{-- Lateral: cuánto avanzó, cuándo vence, qué pasó --}}
        <aside class="lg:col-span-4 space-y-5 order-1 lg:order-2 lg:sticky lg:top-6 lg:max-h-[calc(100vh-3rem)] lg:overflow-y-auto lg:pb-2 lg:-mr-2 lg:pr-2">
            @livewire('auditoria.plan-accion.show.info-plan', ['plan' => $planAccion])
            @livewire('auditoria.actualizaciones.gestion-actualizaciones', ['modelType' => 'plan', 'modelId' => $planAccion->id, 'variante' => 'timeline'])
        </aside>

    </div>
</div>

@livewire('auditoria.validacion-cascada-modal')
@endsection
