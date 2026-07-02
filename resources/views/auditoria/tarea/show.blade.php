@extends('layouts.auditoria')
@section('title', $tarea->nombre)

@section('content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        @php $tareaVencida = $tarea->fecha && $tarea->fecha->lt(now()->startOfDay()) && $tarea->porcentaje_avance < 100; @endphp
        <div class="flex items-center gap-3">
            <a href="{{ route('auditoria.tareas.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </a>
            <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-2xl font-extrabold {{ $tareaVencida ? 'text-red-700' : 'text-gray-900' }}">{{ $tarea->nombre }}</h1>
                @if($tareaVencida)
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">Vencida</span>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @include('auditoria.partials.estado-acciones', ['item' => $tarea, 'routePrefix' => 'tareas'])
            @if($tarea->estado?->nombre === 'borrador' || !$tarea->estado)
                <a href="{{ route('auditoria.tareas.edit', $tarea) }}"
                   class="px-4 py-2 bg-indigo-50 text-indigo-700 text-sm font-bold rounded-xl hover:bg-indigo-100 transition-colors">
                    Editar
                </a>
            @endif
            <form action="{{ route('auditoria.tareas.destroy', $tarea) }}" method="POST"
                  onsubmit="return confirm('¿Eliminar esta tarea?')">
                @csrf @method('DELETE')
                <button type="submit"
                    class="px-4 py-2 bg-red-50 text-red-700 text-sm font-bold rounded-xl hover:bg-red-100 transition-colors">
                    Eliminar
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="space-y-4">
            {{-- Información --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Información</h2>

                @if($tarea->descripcion)
                    <p class="text-sm text-gray-700 mb-4">{{ $tarea->descripcion }}</p>
                @endif

                <div class="mb-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-xs text-gray-400 font-medium">Avance</span>
                        <span class="text-lg font-extrabold {{ $tarea->porcentaje_avance === 100 ? 'text-green-600' : 'text-amber-600' }}">
                            {{ $tarea->porcentaje_avance }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden border border-gray-200 p-px">
                        <div class="h-full rounded-full transition-all
                            {{ $tarea->porcentaje_avance === 100 ? 'bg-green-500' : ($tareaVencida ? 'bg-red-400' : 'bg-amber-500') }}"
                             style="width: {{ $tarea->porcentaje_avance }}%"></div>
                    </div>
                    <p class="text-center text-[10px] font-extrabold uppercase mt-1
                        {{ $tarea->porcentaje_avance === 100 ? 'text-green-600' : ($tareaVencida ? 'text-red-600' : 'text-amber-600') }}">
                        @if($tarea->porcentaje_avance === 100) Completada
                        @elseif($tareaVencida) Vencida
                        @else En Progreso
                        @endif
                    </p>
                </div>

                <div class="mb-4">
                    <x-auditoria.estado-badge :estado="$tarea->estado" />
                </div>

                <dl class="space-y-3 text-sm">
                    @if($tarea->area)
                        <div class="flex justify-between">
                            <dt class="text-gray-400 font-medium">Área</dt>
                            <dd class="text-gray-700">{{ $tarea->area->nombre }}</dd>
                        </div>
                    @endif
                    @if($tarea->fecha)
                        <div class="flex justify-between">
                            <dt class="text-gray-400 font-medium">Fecha límite</dt>
                            <dd class="font-semibold {{ $tareaVencida ? 'text-red-700' : 'text-gray-800' }}">
                                {{ \Carbon\Carbon::parse($tarea->fecha)->format('d/m/Y') }}
                                @if($tareaVencida)
                                    <span class="ml-1 text-[10px] font-bold bg-red-100 text-red-700 px-1.5 py-0.5 rounded">Vencida</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if($tarea->user)
                        <div class="flex justify-between">
                            <dt class="text-gray-400 font-medium">Asignado a</dt>
                            <dd class="text-gray-700">{{ $tarea->user->name }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-400 font-medium">Fecha de creación</dt>
                        <dd class="text-gray-700">{{ $tarea->created_at->format('d/m/Y') }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Planes de Acción --}}
            <x-auditoria.card-seccion titulo="Planes de Acción">
                    @forelse ($tarea->planesAccion as $plan)
                        <div class="px-5 py-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <button type="button"
                                            onclick="Livewire.dispatch('ver-plan', {id: {{$plan->id}}})"
                                            class="text-sm font-semibold text-indigo-700 hover:text-indigo-900 truncate text-left">
                                        {{ $plan->nombre }}
                                    </button>
                                    <x-auditoria.estado-badge :estado="$plan->estado" size="xs" />
                                </div>
                                <span class="font-mono text-[10px] text-gray-400 uppercase shrink-0 ml-2">{{ $plan->codigo }}</span>
                            </div>
                            @if($plan->riesgos->count())
                                <div class="flex flex-wrap gap-1">
                                    @foreach($plan->riesgos as $riesgo)
                                        <button type="button"
                                                onclick="Livewire.dispatch('ver-riesgo', {id: {{$riesgo->id}}})"
                                                class="px-2 py-0.5 rounded text-[10px] font-medium bg-orange-50 text-orange-700 border border-orange-100 hover:border-orange-300 transition-colors">
                                            {{ $riesgo->nombre }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-gray-400 italic text-sm">
                            Esta tarea no está asociada a ningún plan todavía.<br>
                            <a href="{{ route('auditoria.planes.index') }}" class="text-indigo-600 hover:underline text-xs mt-1 inline-block">
                                Asignarla desde un Plan de Acción
                            </a>
                        </div>
                    @endforelse
            </x-auditoria.card-seccion>
        </div>

        <div class="lg:col-span-2 space-y-4">
            @livewire('auditoria.actualizaciones.gestion-actualizaciones', ['modelType' => 'tarea', 'modelId' => $tarea->id])
        </div>

    </div>

</div>
@livewire('auditoria.validacion-cascada-modal')
@endsection
