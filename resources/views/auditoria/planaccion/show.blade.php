@extends('layouts.auditoria')
@section('title', $planAccion->nombre)

@section('content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('auditoria.planes.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </a>
            <div>
                <span class="font-mono text-xs text-gray-400 uppercase font-bold">{{ $planAccion->codigo }}</span>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl font-extrabold text-gray-900">{{ $planAccion->nombre }}</h1>
                    <x-auditoria.estado-badge :estado="$planAccion->estado" />
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @include('auditoria.partials.estado-acciones', ['item' => $planAccion, 'routePrefix' => 'planes'])
            @if($planAccion->estado?->nombre === 'borrador' || !$planAccion->estado)
                <a href="{{ route('auditoria.planes.edit', $planAccion) }}"
                   class="px-4 py-2 bg-indigo-50 text-indigo-700 text-sm font-bold rounded-xl hover:bg-indigo-100 transition-colors">
                    Editar
                </a>
            @endif
            <form action="{{ route('auditoria.planes.destroy', $planAccion) }}" method="POST"
                  onsubmit="return confirm('¿Eliminar este plan de acción?')">
                @csrf @method('DELETE')
                <button type="submit"
                    class="px-4 py-2 bg-red-50 text-red-700 text-sm font-bold rounded-xl hover:bg-red-100 transition-colors">
                    Eliminar
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Columna izquierda: Info + Riesgos --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Información</h2>

                @if($planAccion->descripcion)
                    <p class="text-sm text-gray-700 mb-4">{{ $planAccion->descripcion }}</p>
                @endif

                @php
                    // Avance sólo sobre tareas aprobadas (ver PlanAccion::getAvanceAttribute).
                    $avg = $planAccion->avance;
                @endphp
                @if($avg !== null)
                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-xs text-gray-400 font-medium">Avance General</span>
                            <span class="text-sm font-extrabold {{ $avg === 100 ? 'text-green-600' : 'text-amber-600' }}">{{ $avg }}%</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                            <div class="h-2.5 rounded-full transition-all {{ $avg === 100 ? 'bg-green-500' : 'bg-amber-500' }}" style="width: {{ $avg }}%"></div>
                        </div>
                    </div>
                @endif

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-400 font-medium">Estado</dt>
                        <dd><x-auditoria.estado-punto :estado="$planAccion->estado" /></dd>
                    </div>
                    @if($planAccion->codigo)
                        <div class="flex justify-between">
                            <dt class="text-gray-400 font-medium">Código</dt>
                            <dd class="font-mono text-xs font-bold text-gray-600 uppercase">{{ $planAccion->codigo }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-400 font-medium">Vencimiento</dt>
                        <dd>
                            @if($planAccion->vencimiento)
                                <span class="{{ $planAccion->esta_vencido ? 'text-red-700 font-bold' : 'text-gray-700' }}">
                                    {{ $planAccion->vencimiento->format('d/m/Y') }}
                                </span>
                                @if($planAccion->esta_vencido)
                                    <span class="ml-1.5 text-[10px] font-bold bg-red-100 text-red-700 px-1.5 py-0.5 rounded">Vencido</span>
                                @endif
                            @else
                                <span class="text-gray-400">Sin fecha definida</span>
                            @endif
                        </dd>
                    </div>
                    @if($planAccion->area)
                        <div class="flex justify-between">
                            <dt class="text-gray-400 font-medium">Área</dt>
                            <dd class="text-gray-700">{{ $planAccion->area->nombre }}</dd>
                        </div>
                    @endif
                    @if($planAccion->user)
                        <div class="flex justify-between">
                            <dt class="text-gray-400 font-medium">Responsable</dt>
                            <dd class="text-gray-700">{{ $planAccion->user->name }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-400 font-medium">Fecha de creación</dt>
                        <dd class="text-gray-700">{{ $planAccion->created_at->format('d/m/Y') }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Riesgos asociados --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Riesgos</h2>
                @forelse ($planAccion->riesgos as $riesgo)
                    <div class="flex items-center justify-between p-2.5 mb-2 rounded-lg bg-orange-50/50 border border-orange-100 hover:border-orange-200 transition-colors cursor-pointer"
                         onclick="Livewire.dispatch('ver-riesgo', {id: {{$riesgo->id}}})">
                        <div class="flex items-center gap-2 min-w-0">
                            <x-auditoria.estado-punto :estado="$riesgo->estado" soloPunto />
                            <span class="text-sm font-medium text-gray-700 truncate">{{ $riesgo->nombre }}</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-[10px] font-bold text-orange-700">{{ $riesgo->valor_total }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 italic">Sin riesgos asociados.</p>
                @endforelse
            </div>
        </div>

        {{-- Columna derecha: Tareas + Actualizaciones --}}
        <div class="space-y-4">
            @livewire('auditoria.plan-accion.show.gestion-tareas', ['plan' => $planAccion])
            @livewire('auditoria.actualizaciones.gestion-actualizaciones', ['modelType' => 'plan', 'modelId' => $planAccion->id])
        </div>

    </div>

</div>

@livewire('auditoria.validacion-cascada-modal')
@endsection
