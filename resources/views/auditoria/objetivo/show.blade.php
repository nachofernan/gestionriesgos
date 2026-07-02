@extends('layouts.auditoria')
@section('title', $objetivo->nombre)

@section('content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('auditoria.objetivos.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl font-extrabold text-gray-900">{{ $objetivo->nombre }}</h1>
                    @if($objetivo->estrategico)
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-700">Estratégico</span>
                    @endif
                    @if($objetivo->anticorrupcion)
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700">Anticorrupción</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @include('auditoria.partials.estado-acciones', ['item' => $objetivo, 'routePrefix' => 'objetivos'])
            @if($objetivo->estado?->nombre === 'borrador' || !$objetivo->estado)
                <a href="{{ route('auditoria.objetivos.edit', $objetivo) }}"
                   class="px-4 py-2 bg-indigo-50 text-indigo-700 text-sm font-bold rounded-xl hover:bg-indigo-100 transition-colors">
                    Editar
                </a>
            @endif
            <form action="{{ route('auditoria.objetivos.destroy', $objetivo) }}" method="POST"
                  onsubmit="return confirm('¿Eliminar este objetivo?')">
                @csrf @method('DELETE')
                <button type="submit"
                    class="px-4 py-2 bg-red-50 text-red-700 text-sm font-bold rounded-xl hover:bg-red-100 transition-colors">
                    Eliminar
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 mb-4">

        <div class="space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Información</h2>

                <div class="mb-4">
                    <x-auditoria.estado-badge :estado="$objetivo->estado" />
                </div>

                @if($objetivo->descripcion)
                    <p class="text-sm text-gray-700 mb-4">{{ $objetivo->descripcion }}</p>
                @endif

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-400 font-medium">Fecha objetivo</dt>
                        <dd class="font-semibold text-gray-800">
                            {{ $objetivo->fecha_objetivo?->format('d/m/Y') ?? 'No aplica' }}
                        </dd>
                    </div>
                    @if($objetivo->area)
                        <div class="flex justify-between">
                            <dt class="text-gray-400 font-medium">Área</dt>
                            <dd class="text-gray-700">{{ $objetivo->area->nombre }}</dd>
                        </div>
                    @endif
                    @if($objetivo->user)
                        <div class="flex justify-between">
                            <dt class="text-gray-400 font-medium">Registrado por</dt>
                            <dd class="text-gray-700">{{ $objetivo->user->name }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-400 font-medium">Fecha de creación</dt>
                        <dd class="text-gray-700">{{ $objetivo->created_at->format('d/m/Y') }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div>
            <x-auditoria.card-seccion titulo="Riesgos asociados" subtitulo="asignado desde cada riesgo">
                    @forelse ($objetivo->riesgos as $riesgo)
                        <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors cursor-pointer"
                             onclick="Livewire.dispatch('ver-riesgo', {id: {{$riesgo->id}}})">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">{{ $riesgo->nombre }}</p>
                                <p class="text-[11px] text-gray-400 mt-0.5">{{ $riesgo->tipoRiesgo?->nombre ?? '—' }}</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <x-auditoria.estado-badge :estado="$riesgo->estado" size="sm" />
                                <span class="text-[10px] font-bold bg-orange-50 text-orange-700 border border-orange-100 px-2 py-0.5 rounded-full">
                                    Total: {{ $riesgo->valor_total }}
                                </span>
                                <span class="text-[10px] font-bold bg-green-50 text-green-700 border border-green-100 px-2 py-0.5 rounded-full">
                                    Res: {{ $riesgo->valor_residual }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-gray-400 italic text-sm">
                            Este objetivo no está asociado a ningún riesgo todavía.
                        </div>
                    @endforelse
            </x-auditoria.card-seccion>
        </div>

    </div>

    {{-- Planes de acción desglosados --}}
    @php
        $planesUnicos = collect();
        foreach ($objetivo->riesgos as $riesgo) {
            foreach ($riesgo->planesAccion as $plan) {
                if (!$planesUnicos->has($plan->id)) {
                    $planesUnicos->put($plan->id, $plan);
                }
            }
        }
    @endphp

    @livewire('auditoria.actualizaciones.gestion-actualizaciones', ['modelType' => 'objetivo', 'modelId' => $objetivo->id])

    @if($planesUnicos->isNotEmpty())
        <x-auditoria.card-seccion titulo="Planes de acción vinculados">
                @foreach($planesUnicos as $plan)
                    @php $today = now()->startOfDay(); @endphp
                    <div x-data="{ abierto: false }" class="transition-colors">
                        <button type="button"
                            @click="abierto = !abierto"
                            class="w-full flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors text-left">
                            <div class="flex items-center gap-3">
                                <svg class="h-4 w-4 text-gray-400 transition-transform duration-200" :class="abierto ? 'rotate-90' : ''"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <div>
                                    <span class="text-sm font-semibold text-gray-800">{{ $plan->nombre }}</span>
                                    <span class="ml-2 font-mono text-[10px] text-gray-400 uppercase">{{ $plan->codigo }}</span>
                                    <x-auditoria.estado-badge :estado="$plan->estado" size="xs" />
                                </div>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                @php
                                    $tareasVencidas = $plan->tareas->filter(fn($t) => $t->fecha && $t->fecha->lt($today) && $t->porcentaje_avance < 100)->count();
                                @endphp
                                @if($tareasVencidas > 0)
                                    <span class="text-[10px] font-bold bg-red-100 text-red-700 px-2 py-0.5 rounded-full">
                                        {{ $tareasVencidas }} vencida(s)
                                    </span>
                                @endif
                                <span class="text-[10px] text-gray-400">{{ $plan->tareas->count() }} tarea(s)</span>
                                <a href="{{ route('auditoria.planes.show', $plan) }}"
                                   class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded-lg transition-colors"
                                   @click.stop>
                                    Ver plan
                                </a>
                            </div>
                        </button>

                        <div x-show="abierto" class="border-t border-gray-50">
                            @forelse($plan->tareas as $tarea)
                                @php
                                    $vencida = $tarea->fecha && $tarea->fecha->lt($today) && $tarea->porcentaje_avance < 100;
                                @endphp
                                <div class="flex items-center justify-between px-9 py-2.5 hover:bg-gray-50 transition-colors cursor-pointer
                                    {{ $vencida ? 'bg-red-50/40' : '' }}"
                                    onclick="Livewire.dispatch('ver-tarea', {id: {{$tarea->id}}})">
                                    <div class="flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $vencida ? 'bg-red-500' : 'bg-gray-300' }}"></span>
                                        <span class="text-sm text-gray-700 {{ $vencida ? 'font-semibold text-red-700' : '' }}">{{ $tarea->nombre }}</span>
                                        @if($vencida)
                                            <span class="text-[10px] font-bold text-red-600 bg-red-100 px-1.5 py-0.5 rounded">Vencida</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        @if($tarea->fecha)
                                            <span class="text-[11px] text-gray-400">{{ $tarea->fecha->format('d/m/Y') }}</span>
                                        @endif
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-14 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                                <div class="h-1.5 rounded-full {{ $tarea->porcentaje_avance === 100 ? 'bg-green-500' : ($vencida ? 'bg-red-400' : 'bg-amber-500') }}"
                                                     style="width: {{ $tarea->porcentaje_avance }}%"></div>
                                            </div>
                                            <span class="text-[10px] font-bold {{ $tarea->porcentaje_avance === 100 ? 'text-green-600' : ($vencida ? 'text-red-600' : 'text-amber-600') }}">
                                                {{ $tarea->porcentaje_avance }}%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="px-9 py-3 text-sm text-gray-400 italic">Sin tareas asociadas.</div>
                            @endforelse
                        </div>

                    </div>
                @endforeach
        </x-auditoria.card-seccion>
    @endif

</div>
@livewire('auditoria.validacion-cascada-modal')
@endsection
