@extends('layouts.auditoria')
@section('title', $objetivo->nombre)

@section('content')
@php
    $gerencia = $objetivo->area?->gerencia();
    $hoy = now()->startOfDay();

    // Planes vinculados: los de los riesgos del objetivo (ya filtrados por visibilidad
    // en ObjetivoController::show), sin repetir.
    $planes = \App\Models\Auditoria\Estado::ordenarColeccion(
        $objetivo->riesgos->flatMap->planesAccion->unique('id')->values()
    );
@endphp
<div class="space-y-5">

    {{-- Encabezado --}}
    <header class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div class="flex items-start gap-3 min-w-0">
            <a href="{{ route('auditoria.objetivos.index') }}" title="Volver a objetivos"
               class="mt-1.5 shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-400 hover:text-gray-700 hover:bg-white transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </a>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap text-xs">
                    <span class="font-bold text-gray-500 uppercase">Objetivo</span>
                    <x-auditoria.estado-badge :estado="$objetivo->estado" size="sm" />
                    @if($objetivo->estrategico)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700">Estratégico</span>
                    @endif
                    @if($objetivo->peis)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">PEIS</span>
                    @endif
                </div>
                <h1 class="mt-1 text-2xl sm:text-[28px] font-black tracking-tight text-gray-900 leading-tight">{{ $objetivo->nombre }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ collect([$objetivo->area?->nombre, $gerencia && $gerencia->id !== $objetivo->area?->id ? $gerencia->nombre : null])->filter()->join(' · ') ?: 'Sin área' }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap lg:justify-end">
            @include('auditoria.partials.estado-acciones', ['item' => $objetivo, 'routePrefix' => 'objetivos'])

            @can('delete', $objetivo)
                <div class="relative" x-data="{ abierto: false }" x-on:click.outside="abierto = false">
                    <button type="button" x-on:click="abierto = !abierto" title="Más acciones"
                        class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-white border border-gray-200 text-gray-500 hover:text-gray-800 hover:bg-gray-50 transition-colors">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                    </button>
                    <div x-show="abierto" x-cloak x-transition.origin.top.right
                         class="absolute right-0 mt-2 w-44 rounded-xl bg-white border border-gray-200 shadow-lg py-1 z-30">
                        <form action="{{ route('auditoria.objetivos.destroy', $objetivo) }}" method="POST"
                              onsubmit="return confirm('¿Eliminar este objetivo? Esta acción no se puede deshacer.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                Eliminar objetivo
                            </button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

        {{-- Qué es el objetivo, qué riesgos lo tienen y con qué planes se trabaja --}}
        <div class="lg:col-span-8 space-y-5 order-2 lg:order-1">
            @livewire('auditoria.objetivo.show.ficha-objetivo', ['objetivo' => $objetivo])

            {{-- Sólo lectura: la asociación se gestiona desde cada riesgo, así que no hace falta Livewire. --}}
            <x-auditoria.bloque-riesgo ancla="riesgos" titulo="Riesgos asociados" :contador="$objetivo->riesgos->count()"
                subtitulo="La asociación se gestiona desde cada riesgo.">
                @forelse ($objetivo->riesgos as $riesgo)
                    <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-gray-100 bg-gray-50/60 cursor-pointer hover:border-indigo-200 hover:bg-white transition-colors"
                         onclick="Livewire.dispatch('ver-riesgo', {id: {{ $riesgo->id }}})">
                        <x-auditoria.estado-punto :estado="$riesgo->estado" soloPunto />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">
                                @if($riesgo->codigo) <span class="font-mono text-[11px] font-bold text-gray-400 mr-1">{{ $riesgo->codigo }}</span> @endif
                                {{ $riesgo->nombre }}
                            </p>
                            <p class="text-[11px] text-gray-500 truncate">
                                {{ collect([$riesgo->tipoRiesgo?->nombre, $riesgo->area?->nombre])->filter()->join(' · ') ?: '—' }}
                            </p>
                        </div>
                        <span class="shrink-0 w-20 text-right text-xs tabular-nums text-gray-500" title="Valor total → residual">
                            {{ $riesgo->valor_total }} → <strong class="text-gray-900">{{ $riesgo->valor_residual }}</strong>
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 italic py-2">Este objetivo no está asociado a ningún riesgo todavía.</p>
                @endforelse
            </x-auditoria.bloque-riesgo>

            <x-auditoria.bloque-riesgo ancla="planes" titulo="Planes de acción vinculados" :contador="$planes->count()"
                subtitulo="Los planes de los riesgos asociados.">
                @forelse ($planes as $plan)
                    @php
                        $tareas = \App\Models\Auditoria\Estado::ordenarColeccion($plan->tareas);
                        $vencidas = $tareas->filter(fn ($t) => $t->fecha && $t->fecha->lt($hoy) && $t->porcentaje_avance < 100)->count();
                        $avance = $tareas->isNotEmpty() ? (int) round($tareas->avg('porcentaje_avance')) : 0;
                    @endphp
                    <div x-data="{ abierto: false }" class="rounded-xl border border-gray-100 bg-gray-50/60 overflow-hidden">
                        <div class="flex items-center gap-3 px-3 py-2.5">
                            <button type="button" x-on:click="abierto = !abierto" title="Ver tareas"
                                class="shrink-0 text-gray-400 hover:text-gray-700 {{ $tareas->isEmpty() ? 'invisible' : '' }}">
                                <svg class="h-4 w-4 transition-transform" :class="abierto && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                            <x-auditoria.estado-punto :estado="$plan->estado" soloPunto />
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('auditoria.planes.show', $plan) }}" class="block text-sm font-semibold text-gray-800 hover:text-indigo-700 truncate">
                                    <span class="font-mono text-[11px] font-bold text-gray-400 mr-1">{{ $plan->codigo }}</span>
                                    {{ $plan->nombre }}
                                </a>
                                <p class="text-[11px] text-gray-500 truncate">
                                    {{ $tareas->count() }} {{ $tareas->count() === 1 ? 'tarea' : 'tareas' }}
                                    @if($vencidas > 0) · <span class="font-bold text-red-600">{{ $vencidas }} {{ $vencidas === 1 ? 'vencida' : 'vencidas' }}</span> @endif
                                </p>
                            </div>
                            <div class="shrink-0 flex items-center gap-1.5" title="Avance promedio de las tareas">
                                <div class="w-16 h-1.5 rounded-full bg-gray-200 overflow-hidden">
                                    <div class="h-full rounded-full {{ $avance === 100 ? 'bg-green-500' : 'bg-indigo-500' }}" style="width: {{ $avance }}%"></div>
                                </div>
                                <span class="w-8 text-right text-[11px] font-bold tabular-nums {{ $avance === 100 ? 'text-green-700' : 'text-gray-600' }}">{{ $avance }}%</span>
                            </div>
                        </div>

                        <ul x-show="abierto" x-cloak class="border-t border-gray-100 bg-white divide-y divide-gray-50">
                            @foreach($tareas as $tarea)
                                @php $vencida = $tarea->fecha && $tarea->fecha->lt($hoy) && $tarea->porcentaje_avance < 100; @endphp
                                <li class="flex items-center gap-3 pl-10 pr-3 py-2 cursor-pointer hover:bg-gray-50 {{ $vencida ? 'bg-red-50/40' : '' }}"
                                    onclick="Livewire.dispatch('ver-tarea', {id: {{ $tarea->id }}})">
                                    <span class="flex-1 min-w-0 text-sm truncate {{ $vencida ? 'font-semibold text-red-700' : 'text-gray-700' }}">{{ $tarea->nombre }}</span>
                                    @if($tarea->fecha)
                                        <span class="shrink-0 text-[11px] tabular-nums {{ $vencida ? 'text-red-600 font-bold' : 'text-gray-400' }}">{{ $tarea->fecha->format('d/m/Y') }}</span>
                                    @endif
                                    <span class="shrink-0 w-9 text-right text-[11px] font-bold tabular-nums {{ $tarea->porcentaje_avance === 100 ? 'text-green-600' : ($vencida ? 'text-red-600' : 'text-amber-600') }}">{{ $tarea->porcentaje_avance }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 italic py-2">Ninguno de sus riesgos tiene planes de acción.</p>
                @endforelse
            </x-auditoria.bloque-riesgo>

            @livewire('auditoria.actualizaciones.conversacion', ['modelType' => 'objetivo', 'modelId' => $objetivo->id])
        </div>

        {{-- Lateral: cuándo vence, cómo se clasifica, qué pasó --}}
        <aside class="lg:col-span-4 space-y-5 order-1 lg:order-2 lg:sticky lg:top-6 lg:max-h-[calc(100vh-3rem)] lg:overflow-y-auto lg:pb-2 lg:-mr-2 lg:pr-2">
            @livewire('auditoria.objetivo.show.info-objetivo', ['objetivo' => $objetivo])
            @livewire('auditoria.actualizaciones.gestion-actualizaciones', ['modelType' => 'objetivo', 'modelId' => $objetivo->id, 'variante' => 'timeline'])
        </aside>

    </div>
</div>

@livewire('auditoria.validacion-cascada-modal')
@endsection
