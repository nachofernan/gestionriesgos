@extends('layouts.auditoria')
@section('title', $riesgo->nombre)

@section('content')
@php
    $gerencia = $riesgo->area?->gerencia();
@endphp
<div class="space-y-5">

    {{-- Encabezado --}}
    <header class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div class="flex items-start gap-3 min-w-0">
            <a href="{{ route('auditoria.riesgos.index') }}" title="Volver a riesgos"
               class="mt-1.5 shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-400 hover:text-gray-700 hover:bg-white transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </a>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap text-xs">
                    <x-auditoria.estado-badge :estado="$riesgo->estado" size="sm" />
                    <x-auditoria.interno-badge :activo="$riesgo->interno ?? false" size="sm" />
                    @include('auditoria.partials.comite-aprobacion', ['item' => $riesgo])
                    @if($riesgo->mayor_criticidad)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700">Mayor criticidad</span>
                    @endif
                </div>
                <h1 class="mt-1 text-2xl sm:text-[28px] font-black tracking-tight text-gray-900 leading-tight">{{ $riesgo->nombre }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ collect([$riesgo->tipoRiesgo?->nombre, $riesgo->area?->nombre, $gerencia && $gerencia->id !== $riesgo->area?->id ? $gerencia->nombre : null])->filter()->join(' · ') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap lg:justify-end">
            @include('auditoria.partials.estado-acciones', ['item' => $riesgo, 'routePrefix' => 'riesgos'])

            @if($eliminacion)
                @include('auditoria.riesgo.partials.modal-eliminacion', ['riesgo' => $riesgo, 'dependientes' => $eliminacion])
            @endif
        </div>
    </header>

    {{-- Propuestas pendientes: cuántas, cuáles me tocan y dónde están --}}
    @livewire('auditoria.riesgo.show.resumen-propuestas', ['riesgo' => $riesgo])
    @livewire('auditoria.revision-inicial.pendientes-ficha', ['tipo' => 'riesgo', 'modeloId' => $riesgo->id])

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

        {{-- La historia del riesgo: qué es, a qué amenaza, qué lo mitiga --}}
        <div class="lg:col-span-8 space-y-5 order-2 lg:order-1">
            @livewire('auditoria.riesgo.show.ficha-riesgo', ['riesgo' => $riesgo])
            @livewire('auditoria.riesgo.show.gestion-objetivos', ['riesgo' => $riesgo])
            @livewire('auditoria.riesgo.show.gestion-controles', ['riesgo' => $riesgo])
            @livewire('auditoria.riesgo.show.gestion-planes', ['riesgo' => $riesgo])
            @livewire('auditoria.actualizaciones.conversacion', ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
        </div>

        {{-- Lateral: cuánto vale, quién lo gestiona, qué pasó --}}
        <aside class="lg:col-span-4 space-y-5 order-1 lg:order-2 lg:sticky lg:top-6 lg:max-h-[calc(100vh-3rem)] lg:overflow-y-auto lg:pb-2 lg:-mr-2 lg:pr-2">
            @livewire('auditoria.riesgo.show.info-riesgo', ['riesgo' => $riesgo])
            {{-- Mientras se edita la mitigación de los controles (edición directa o propuesta), la card de valor
                 se achica a residual + proyección para que la referencia quede a la vista justo debajo. --}}
            <div id="referencia-valoracion" class="hidden">
                @include('auditoria.partials.referencia-valoracion-control')
            </div>
            <style>
                body:has(#controles[data-editando]) #referencia-valoracion { display: block; }
                body:has(#controles[data-editando]) #valor [data-detalle-valor] { display: none; }
                body:has(#controles[data-editando]) #valor > div { padding-bottom: 1rem; }
            </style>
            @livewire('auditoria.actualizaciones.gestion-actualizaciones', ['modelType' => 'riesgo', 'modelId' => $riesgo->id, 'variante' => 'timeline'])
        </aside>

    </div>
</div>

@livewire('auditoria.validacion-cascada-modal')
@endsection
