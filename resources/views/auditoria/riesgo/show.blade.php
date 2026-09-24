@extends('layouts.auditoria')
@section('title', $riesgo->nombre)

@section('content')
@php
    $compartido = $riesgo->esMultigerencia();
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
                    @if($riesgo->codigo)
                        <span class="font-mono font-bold text-gray-500 uppercase">{{ $riesgo->codigo }}</span>
                    @endif
                    <x-auditoria.estado-badge :estado="$riesgo->estado" size="sm" />
                    @if($compartido)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 text-violet-700"
                              title="Cada cambio necesita el voto de todas sus gerencias">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Compartido
                        </span>
                    @endif
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

            @can('delete', $riesgo)
                <div class="relative" x-data="{ abierto: false }" x-on:click.outside="abierto = false">
                    <button type="button" x-on:click="abierto = !abierto" title="Más acciones"
                        class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-white border border-gray-200 text-gray-500 hover:text-gray-800 hover:bg-gray-50 transition-colors">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                    </button>
                    <div x-show="abierto" x-cloak x-transition.origin.top.right
                         class="absolute right-0 mt-2 w-44 rounded-xl bg-white border border-gray-200 shadow-lg py-1 z-30">
                        <form action="{{ route('auditoria.riesgos.destroy', $riesgo) }}" method="POST"
                              onsubmit="return confirm('¿Eliminar este riesgo? Esta acción no se puede deshacer.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                Eliminar riesgo
                            </button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </header>

    {{-- Propuestas pendientes: cuántas, cuáles me tocan y dónde están --}}
    @livewire('auditoria.riesgo.show.resumen-propuestas', ['riesgo' => $riesgo])

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

        {{-- La historia del riesgo: qué es, a qué amenaza, qué lo mitiga --}}
        <div class="lg:col-span-8 space-y-5 order-2 lg:order-1">
            @livewire('auditoria.riesgo.show.ficha-riesgo', ['riesgo' => $riesgo])
            {{-- Fichas en JS plano y no Alpine: un x-data envolviendo los componentes deja sin enganchar
                 sus wire:* (ver conversacion-riesgo.blade.php). Se ocultan con `hidden` y no con @if, para
                 que los tres sigan montados y escuchando eventos. --}}
            <div id="fichas" class="scroll-mt-24 space-y-3">
                @php
                    $pestanas = [
                        'planes' => ['Planes', $riesgo->planesAccion()->count()],
                        'controles' => ['Controles', $riesgo->controles()->count()],
                        'objetivos' => ['Objetivos', $riesgo->objetivos()->count()],
                    ];
                @endphp
                <nav class="grid grid-cols-3 items-center">
                    @foreach($pestanas as $clave => [$etiqueta, $cantidad])
                        <button type="button" data-ficha-boton="{{ $clave }}" @if($clave === 'planes') data-activa @endif onclick="mostrarFicha('{{ $clave }}')"
                            class="relative py-1.5 text-sm font-bold text-center transition-colors
                                   {{ $clave === 'planes' ? 'text-gray-900' : 'text-gray-400 hover:text-gray-700' }}
                                   {{ !$loop->first ? 'before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:h-4 before:w-px before:bg-gray-200' : '' }}">
                            {{ $etiqueta }} <span class="font-semibold tabular-nums">({{ $cantidad }})</span>
                        </button>
                    @endforeach
                </nav>

                <div data-ficha="planes">
                    @livewire('auditoria.riesgo.show.gestion-planes', ['riesgo' => $riesgo])
                </div>
                <div data-ficha="controles" class="hidden">
                    @livewire('auditoria.riesgo.show.gestion-controles', ['riesgo' => $riesgo])
                </div>
                <div data-ficha="objetivos" class="hidden">
                    @livewire('auditoria.riesgo.show.gestion-objetivos', ['riesgo' => $riesgo])
                </div>
            </div>
            {{-- Con una ficha en edición, las otras se bloquean: cambiar de ficha la ocultaría a medio editar. --}}
            <style>
                #fichas:has([data-editando]) [data-ficha-boton]:not([data-activa]) { opacity: .35; pointer-events: none; }
            </style>
            <script>
                function mostrarFicha(clave) {
                    if (document.querySelector('#fichas [data-editando]')) return;
                    document.querySelectorAll('[data-ficha]').forEach(el => el.classList.toggle('hidden', el.dataset.ficha !== clave));
                    document.querySelectorAll('[data-ficha-boton]').forEach(el => {
                        const activa = el.dataset.fichaBoton === clave;
                        el.toggleAttribute('data-activa', activa);
                        el.classList.toggle('text-gray-900', activa);
                        el.classList.toggle('text-gray-400', !activa);
                        el.classList.toggle('hover:text-gray-700', !activa);
                    });
                }

                // Los links #planes/#controles/#objetivos de otros bloques cambian de ficha antes de scrollear.
                document.addEventListener('click', e => {
                    const a = e.target.closest('a[href^="#"]');
                    if (!a || !document.querySelector(`[data-ficha="${a.hash.slice(1)}"]`)) return;
                    e.preventDefault();
                    mostrarFicha(a.hash.slice(1));
                    document.getElementById('fichas').scrollIntoView({ behavior: 'smooth' });
                });
            </script>
            @livewire('auditoria.riesgo.show.conversacion-riesgo', ['riesgo' => $riesgo])
        </div>

        {{-- Lateral: cuánto vale, quién lo gestiona, qué pasó --}}
        <aside class="lg:col-span-4 space-y-5 order-1 lg:order-2 lg:sticky lg:top-6 lg:max-h-[calc(100vh-3rem)] lg:overflow-y-auto lg:pb-2 lg:-mr-2 lg:pr-2">
            @livewire('auditoria.riesgo.show.info-riesgo', ['riesgo' => $riesgo])
            @livewire('auditoria.riesgo.show.gestion-areas', ['riesgo' => $riesgo])
            @livewire('auditoria.actualizaciones.gestion-actualizaciones', ['modelType' => 'riesgo', 'modelId' => $riesgo->id, 'variante' => 'timeline'])
        </aside>

    </div>
</div>

@livewire('auditoria.validacion-cascada-modal')
@endsection
