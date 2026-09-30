@extends('layouts.auditoria')
@section('title', $tarea->nombre)

@section('content')
@php
    $gerencia = $tarea->area?->gerencia();
    $tareaVencida = $tarea->fecha && $tarea->fecha->lt(now()->startOfDay()) && $tarea->porcentaje_avance < 100;
    $planes = \App\Models\Auditoria\Estado::ordenarColeccion($tarea->planesAccion);
@endphp
<div class="space-y-5">

    {{-- Encabezado --}}
    <header class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div class="flex items-start gap-3 min-w-0">
            <a href="{{ route('auditoria.tareas.index') }}" title="Volver a tareas"
               class="mt-1.5 shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-400 hover:text-gray-700 hover:bg-white transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </a>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap text-xs">
                    <span class="font-bold text-gray-500 uppercase">Tarea</span>
                    <x-auditoria.estado-badge :estado="$tarea->estado" size="sm" />
                    @if($tareaVencida)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700">Vencida</span>
                    @endif
                </div>
                <h1 class="mt-1 text-2xl sm:text-[28px] font-black tracking-tight text-gray-900 leading-tight">{{ $tarea->nombre }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ collect([$tarea->area?->nombre, $gerencia && $gerencia->id !== $tarea->area?->id ? $gerencia->nombre : null])->filter()->join(' · ') ?: 'Sin área' }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap lg:justify-end">
            @include('auditoria.partials.estado-acciones', ['item' => $tarea, 'routePrefix' => 'tareas'])

            @can('delete', $tarea)
                <div class="relative" x-data="{ abierto: false }" x-on:click.outside="abierto = false">
                    <button type="button" x-on:click="abierto = !abierto" title="Más acciones"
                        class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-white border border-gray-200 text-gray-500 hover:text-gray-800 hover:bg-gray-50 transition-colors">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                    </button>
                    <div x-show="abierto" x-cloak x-transition.origin.top.right
                         class="absolute right-0 mt-2 w-44 rounded-xl bg-white border border-gray-200 shadow-lg py-1 z-30">
                        <form action="{{ route('auditoria.tareas.destroy', $tarea) }}" method="POST"
                              onsubmit="return confirm('¿Eliminar esta tarea? Esta acción no se puede deshacer.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                Eliminar tarea
                            </button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

        {{-- Qué es la tarea y para qué planes (y riesgos) trabaja --}}
        <div class="lg:col-span-8 space-y-5 order-2 lg:order-1">
            @livewire('auditoria.tarea.show.ficha-tarea', ['tarea' => $tarea])

            {{-- Sólo lectura: la tarea se asigna desde el plan, así que no hace falta Livewire. --}}
            <x-auditoria.bloque-riesgo ancla="planes" titulo="Planes de acción" :contador="$planes->count()"
                subtitulo="La tarea se asigna desde cada plan.">
                @forelse ($planes as $plan)
                    @php $avance = $plan->avance; @endphp
                    <div class="rounded-xl border border-gray-100 bg-gray-50/60 px-3 py-2.5 space-y-2">
                        <div class="flex items-center gap-3">
                            <x-auditoria.estado-punto :estado="$plan->estado" soloPunto />
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('auditoria.planes.show', $plan) }}" class="block text-sm font-semibold text-gray-800 hover:text-indigo-700 truncate">
                                    {{ $plan->nombre }}
                                </a>
                                <p class="text-[11px] text-gray-500 truncate">
                                    {{ $plan->tareas->count() }} {{ $plan->tareas->count() === 1 ? 'tarea' : 'tareas' }}
                                    @if($plan->area) · {{ $plan->area->nombre }} @endif
                                </p>
                            </div>
                            <div class="shrink-0 flex items-center gap-1.5" title="Avance del plan: promedio de sus tareas aprobadas">
                                @if($avance === null)
                                    <span class="text-[11px] text-gray-400">sin tareas aprobadas</span>
                                @else
                                    <div class="w-16 h-1.5 rounded-full bg-gray-200 overflow-hidden">
                                        <div class="h-full rounded-full {{ $avance === 100 ? 'bg-green-500' : 'bg-indigo-500' }}" style="width: {{ $avance }}%"></div>
                                    </div>
                                    <span class="w-8 text-right text-[11px] font-bold tabular-nums {{ $avance === 100 ? 'text-green-700' : 'text-gray-600' }}">{{ $avance }}%</span>
                                @endif
                            </div>
                        </div>
                        @if($plan->riesgos->isNotEmpty())
                            <div class="pl-5 flex flex-wrap gap-1">
                                @foreach(\App\Models\Auditoria\Estado::ordenarColeccion($plan->riesgos) as $riesgo)
                                    <button type="button" onclick="Livewire.dispatch('ver-riesgo', {id: {{ $riesgo->id }}})"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-white border border-gray-200 text-gray-700 hover:border-indigo-300 hover:text-indigo-700 transition-colors">
                                        {{ \Illuminate\Support\Str::limit($riesgo->nombre, 40) }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-400 italic py-2">
                        Esta tarea no está asignada a ningún plan todavía.
                        <a href="{{ route('auditoria.planes.index') }}" class="not-italic font-semibold text-indigo-600 hover:underline">Asignarla desde un plan →</a>
                    </p>
                @endforelse
            </x-auditoria.bloque-riesgo>

            @livewire('auditoria.actualizaciones.conversacion', ['modelType' => 'tarea', 'modelId' => $tarea->id])
        </div>

        {{-- Lateral: cuánto avanzó, cuándo vence, qué pasó --}}
        <aside class="lg:col-span-4 space-y-5 order-1 lg:order-2 lg:sticky lg:top-6 lg:max-h-[calc(100vh-3rem)] lg:overflow-y-auto lg:pb-2 lg:-mr-2 lg:pr-2">
            @livewire('auditoria.tarea.show.info-tarea', ['tarea' => $tarea])
            @livewire('auditoria.actualizaciones.gestion-actualizaciones', ['modelType' => 'tarea', 'modelId' => $tarea->id, 'variante' => 'timeline'])
        </aside>

    </div>
</div>

@livewire('auditoria.validacion-cascada-modal')
@endsection
