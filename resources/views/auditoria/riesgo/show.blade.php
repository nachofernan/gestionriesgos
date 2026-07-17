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
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Información</h2>

                @if($riesgo->descripcion)
                    <p class="text-sm text-gray-700 mb-4">{{ $riesgo->descripcion }}</p>
                @endif

                @php
                    $cardMap = [
                        'verde'    => ['bg' => 'bg-green-50',  'text' => 'text-green-700',  'sub' => 'text-green-500'],
                        'amarillo' => ['bg' => 'bg-yellow-50', 'text' => 'text-yellow-700', 'sub' => 'text-yellow-500'],
                        'rojo'     => ['bg' => 'bg-red-50',    'text' => 'text-red-700',    'sub' => 'text-red-500'],
                    ];
                    $cTotal = $cardMap[$riesgo->clasificacion_total['color']];
                @endphp
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="{{ $cTotal['bg'] }} rounded-xl p-3 text-center">
                        <div class="text-3xl font-extrabold {{ $cTotal['text'] }}">{{ $riesgo->valor_total }}</div>
                        <div class="text-[10px] {{ $cTotal['sub'] }} uppercase font-bold tracking-wider mt-0.5">Valor Total</div>
                    </div>
                    <div class="rounded-xl p-3 text-center"
                         x-data="{
                             residual: {{ $riesgo->valor_residual }},
                             get color() {
                                 if (this.residual <= 9)  return { bg: 'bg-green-50',  text: 'text-green-700',  sub: 'text-green-500' };
                                 if (this.residual <= 13) return { bg: 'bg-yellow-50', text: 'text-yellow-700', sub: 'text-yellow-500' };
                                 return { bg: 'bg-red-50', text: 'text-red-700', sub: 'text-red-500' };
                             }
                         }"
                         x-bind:class="color.bg"
                         @residual-actualizado.window="residual = $event.detail.valor">
                        <div class="text-3xl font-extrabold" x-bind:class="color.text" x-text="residual"></div>
                        <div class="text-[10px] uppercase font-bold tracking-wider mt-0.5" x-bind:class="color.sub">Residual</div>
                    </div>
                </div>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-400 font-medium">Estado</dt>
                        <dd><x-auditoria.estado-punto :estado="$riesgo->estado" /></dd>
                    </div>
                    @if($riesgo->codigo)
                        <div class="flex justify-between">
                            <dt class="text-gray-400 font-medium">Código</dt>
                            <dd class="font-mono text-xs font-bold text-gray-600 uppercase">{{ $riesgo->codigo }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-400 font-medium">Impacto</dt>
                        <dd class="font-bold text-gray-800">{{ $riesgo->impacto }}<span class="text-xs text-gray-400 font-normal">/10</span></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 font-medium">Probabilidad</dt>
                        <dd class="font-bold text-gray-800">{{ $riesgo->probabilidad }}<span class="text-xs text-gray-400 font-normal">/10</span></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 font-medium">Criticidad</dt>
                        <dd>
                            @if($riesgo->mayor_criticidad)
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">Mayor criticidad</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-500">Normal</span>
                            @endif
                        </dd>
                    </div>
                    @if($riesgo->respuesta)
                        <div class="flex justify-between">
                            <dt class="text-gray-400 font-medium">Respuesta</dt>
                            <dd class="font-bold text-gray-800">{{ $riesgo->respuesta->label() }}</dd>
                        </div>
                    @endif
                    @if($riesgo->fundamento)
                        {{-- Apilado y no en fila: es texto libre y largo. --}}
                        <div>
                            <dt class="text-gray-400 font-medium mb-1">Fundamento</dt>
                            <dd class="text-gray-700 whitespace-pre-line">{{ $riesgo->fundamento }}</dd>
                        </div>
                    @endif
                    @if($riesgo->area)
                        <div class="flex justify-between">
                            <dt class="text-gray-400 font-medium">Área</dt>
                            <dd class="text-gray-700">{{ $riesgo->area->nombre }}</dd>
                        </div>
                    @endif
                    @if($riesgo->user)
                        <div class="flex justify-between">
                            <dt class="text-gray-400 font-medium">Registrado por</dt>
                            <dd class="text-gray-700">{{ $riesgo->user->name }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-400 font-medium">Fecha de creación</dt>
                        <dd class="text-gray-700">{{ $riesgo->created_at->format('d/m/Y') }}</dd>
                    </div>
                </dl>
            </div>

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
