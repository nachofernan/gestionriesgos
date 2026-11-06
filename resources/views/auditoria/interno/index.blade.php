@extends('layouts.auditoria')
@section('title', 'Interno')

@php
    $chip = [
        'verde'    => 'bg-green-100 text-green-800',
        'amarillo' => 'bg-yellow-100 text-yellow-800',
        'rojo'     => 'bg-red-100 text-red-800',
    ];
    $colorGrupo = [
        'Vencidas'   => 'text-red-700 bg-red-50',
        'Por vencer' => 'text-amber-800 bg-amber-50',
        'En plazo'   => 'text-green-700 bg-green-50',
        'Sin fecha'  => 'text-gray-600 bg-gray-50',
    ];
@endphp

@section('content')
<div class="space-y-8">

    <div>
        <h1 class="flex items-center gap-2 text-2xl font-extrabold text-gray-900">
            Gestión interna <x-auditoria.interno-badge :activo="true" />
        </h1>
        <p class="text-sm text-gray-500 mt-1 max-w-3xl">
            Los borradores internos de tu área: lo que se gestiona sin pasar por el comité. Los valores son
            <strong>proyectados</strong>: cuentan los controles, planes y tareas aprobados o internos. No son los valores oficiales.
        </p>
    </div>

    {{-- Resumen --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        @foreach([
            'Riesgos' => $riesgos->count(),
            'Controles' => $cantidadControles,
            'Objetivos' => $cantidadObjetivos,
            'Planes' => $planes->count(),
            'Tareas abiertas' => collect($vencimientos)->sum(fn ($g) => $g->count()),
        ] as $etiqueta => $cantidad)
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">{{ $etiqueta }}</p>
                <p class="text-2xl font-black tabular-nums text-gray-900">{{ $cantidad }}</p>
            </div>
        @endforeach
    </div>

    {{-- Riesgos --}}
    <section class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-extrabold text-gray-900">Riesgos internos</h2>
            <p class="text-xs text-gray-400 mt-0.5">Ordenados por residual proyectado, el más alto primero.</p>
        </div>
        @if($riesgos->isEmpty())
            <p class="px-5 py-6 text-sm text-gray-400">No hay riesgos internos.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left">Riesgo</th>
                            <th class="px-4 py-3 text-left">Área</th>
                            <th class="px-4 py-3 text-center">Total</th>
                            <th class="px-4 py-3 text-center">Mitigación proyectada</th>
                            <th class="px-4 py-3 text-center">Residual proyectado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($riesgos as $riesgo)
                            @php $clasif = \App\Models\Auditoria\Riesgo::clasificacion($riesgo->valor_residual_proyectado); @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <a href="{{ route('auditoria.riesgos.show', $riesgo) }}" class="font-semibold text-gray-900 hover:text-indigo-700">{{ $riesgo->nombre }}</a>
                                    <p class="text-xs text-gray-400">{{ $riesgo->tipoRiesgo?->nombre }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $riesgo->area?->nombre }}</td>
                                <td class="px-4 py-3 text-center tabular-nums font-bold text-gray-700">{{ $riesgo->valor_total }}</td>
                                <td class="px-4 py-3 text-center tabular-nums text-indigo-700">
                                    −{{ $riesgo->mitigacion_controles_proyectada + $riesgo->mitigacion_planes_proyectada }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-extrabold {{ $chip[$clasif['color']] }}">
                                        <span class="tabular-nums">{{ $riesgo->valor_residual_proyectado }}</span>
                                        <span class="uppercase text-[10px]">{{ $clasif['etiqueta'] }}</span>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Planes --}}
    <section class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-extrabold text-gray-900">Planes de acción internos</h2>
            <p class="text-xs text-gray-400 mt-0.5">El avance proyectado promedia sus tareas aprobadas o internas.</p>
        </div>
        @if($planes->isEmpty())
            <p class="px-5 py-6 text-sm text-gray-400">No hay planes internos.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($planes as $plan)
                    @php $avance = $plan->avance_proyectado; @endphp
                    <li class="px-5 py-3 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-6">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('auditoria.planes.show', $plan) }}" class="font-semibold text-gray-900 hover:text-indigo-700">{{ $plan->nombre }}</a>
                            <p class="text-xs text-gray-400">
                                {{ $plan->area?->nombre }} · {{ $plan->riesgos_count }} {{ $plan->riesgos_count === 1 ? 'riesgo' : 'riesgos' }}
                                · {{ $plan->tareasProyectadas()->count() }} de {{ $plan->tareas->count() }} tareas cuentan
                            </p>
                        </div>
                        <div class="flex items-center gap-3 sm:w-64">
                            <div class="h-2 flex-1 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full bg-violet-500" style="width: {{ $avance ?? 0 }}%"></div>
                            </div>
                            <span class="w-12 text-right text-sm font-black tabular-nums text-gray-800">{{ $avance === null ? '—' : $avance.'%' }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Vencimientos --}}
    <section class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-extrabold text-gray-900">Vencimientos de tareas internas</h2>
            <p class="text-xs text-gray-400 mt-0.5">Tareas internas sin terminar; "por vencer" son los próximos {{ $diasPorVencer }} días.</p>
        </div>
        @if(collect($vencimientos)->every(fn ($g) => $g->isEmpty()))
            <p class="px-5 py-6 text-sm text-gray-400">No hay tareas internas abiertas.</p>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($vencimientos as $grupo => $tareas)
                    @continue($tareas->isEmpty())
                    <div class="px-5 py-3">
                        <p class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-extrabold uppercase tracking-wider {{ $colorGrupo[$grupo] }}">
                            {{ $grupo }} · {{ $tareas->count() }}
                        </p>
                        <ul class="mt-2 space-y-1.5">
                            @foreach($tareas as $tarea)
                                <li class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 text-sm">
                                    <a href="{{ route('auditoria.tareas.show', $tarea) }}" class="min-w-0 flex-1 font-semibold text-gray-800 hover:text-indigo-700">{{ $tarea->nombre }}</a>
                                    <span class="text-xs text-gray-400 sm:w-48 truncate">{{ $tarea->planesAccion->pluck('nombre')->join(', ') ?: 'Sin plan' }}</span>
                                    <span class="text-xs tabular-nums text-gray-500 sm:w-20">{{ $tarea->fecha?->format('d/m/Y') ?? '—' }}</span>
                                    <span class="text-xs font-bold tabular-nums text-gray-700 sm:w-10 sm:text-right">{{ $tarea->porcentaje_avance }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
