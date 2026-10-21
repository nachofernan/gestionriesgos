@php $planesPorGerencia = collect($reporte['planes'])->groupBy('gerencia'); @endphp

<div class="space-y-6">

    {{-- Subtotales por gerencia --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
        @if(empty($reporte['gerencias']))
            <div class="p-10 text-center text-sm text-gray-400 italic">No hay planes de acción con estos filtros.</div>
        @else
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <th rowspan="2" class="px-4 py-2 text-left font-semibold">Gerencia</th>
                        <th colspan="5" class="px-3 pt-2 pb-1 text-center font-semibold border-l border-gray-200">Planes</th>
                        <th rowspan="2" class="px-3 py-2 text-right font-semibold border-l border-gray-200">Riesgos<br>que mitigan</th>
                        <th colspan="4" class="px-3 pt-2 pb-1 text-center font-semibold border-l border-gray-200">Tareas</th>
                    </tr>
                    <tr class="bg-gray-50 text-xs text-gray-600">
                        <th class="px-3 pb-2 pt-1 text-right font-medium border-l border-gray-200">Total</th>
                        <th class="px-3 pb-2 pt-1 text-right font-medium">En fecha</th>
                        <th class="px-3 pb-2 pt-1 text-right font-medium">Vencidos</th>
                        <th class="px-3 pb-2 pt-1 text-right font-medium">Con avance</th>
                        <th class="px-3 pb-2 pt-1 text-right font-medium">Sin avance</th>
                        <th class="px-3 pb-2 pt-1 text-right font-medium border-l border-gray-200">Total</th>
                        <th class="px-3 pb-2 pt-1 text-right font-medium">Vencidas</th>
                        <th class="px-3 pb-2 pt-1 text-right font-medium">Con avance</th>
                        <th class="px-3 pb-2 pt-1 text-right font-medium">Sin avance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 tabular-nums">
                    @foreach($reporte['gerencias'] as $g)
                        <tr>
                            <td class="px-4 py-2 text-gray-800 whitespace-nowrap">{{ $g['nombre'] }}</td>
                            <td class="px-3 py-2 text-right font-bold text-gray-900 border-l border-gray-200">{{ $g['planes'] }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ $g['planes_en_fecha'] ?: '·' }}</td>
                            <td class="px-3 py-2 text-right {{ $g['planes_vencidos'] ? 'text-red-600 font-semibold' : 'text-gray-300' }}">{{ $g['planes_vencidos'] ?: '·' }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ $g['planes_con_avance'] ?: '·' }}</td>
                            <td class="px-3 py-2 text-right {{ $g['planes_sin_avance'] ? 'text-amber-600 font-semibold' : 'text-gray-300' }}">{{ $g['planes_sin_avance'] ?: '·' }}</td>
                            <td class="px-3 py-2 text-right text-gray-700 border-l border-gray-200">{{ $g['riesgos'] }}</td>
                            <td class="px-3 py-2 text-right font-bold text-gray-900 border-l border-gray-200">{{ $g['tareas'] }}</td>
                            <td class="px-3 py-2 text-right {{ $g['tareas_vencidas'] ? 'text-red-600 font-semibold' : 'text-gray-300' }}">{{ $g['tareas_vencidas'] ?: '·' }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ $g['tareas_con_avance'] ?: '·' }}</td>
                            <td class="px-3 py-2 text-right {{ $g['tareas_sin_avance'] ? 'text-amber-600 font-semibold' : 'text-gray-300' }}">{{ $g['tareas_sin_avance'] ?: '·' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Detalle de planes, agrupado por gerencia --}}
    @foreach($planesPorGerencia as $gerencia => $planes)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-gray-100 text-sm font-bold text-gray-900">{{ $gerencia }}</div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-600">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">Plan de acción</th>
                        <th class="px-3 py-2 text-left font-medium">Riesgos que mitiga</th>
                        <th class="px-3 py-2 text-left font-medium w-40">Avance</th>
                        <th class="px-3 py-2 text-right font-medium">Vencimiento</th>
                        <th class="px-3 py-2 text-right font-medium">Situación</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($planes as $p)
                        <tr class="align-top">
                            <td class="px-4 py-2">
                                <a href="{{ route('auditoria.planes.show', $p['id']) }}" class="text-indigo-700 hover:underline">{{ $p['nombre'] }}</a>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-600">
                                {{ implode(' · ', $p['riesgos']) ?: '—' }}
                            </td>
                            <td class="px-3 py-2">
                                @if($p['avance'] === null)
                                    <span class="text-xs text-gray-400 italic">sin tareas aprobadas</span>
                                @else
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-24 rounded-full bg-gray-100 overflow-hidden">
                                            <div class="h-full {{ $p['avance'] === 100 ? 'bg-green-500' : 'bg-indigo-500' }}" style="width: {{ $p['avance'] }}%"></div>
                                        </div>
                                        <span class="text-xs tabular-nums text-gray-700">{{ $p['avance'] }}%</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right text-xs tabular-nums text-gray-600">{{ $p['vencimiento']?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <span class="inline-block text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $p['vencido'] ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                    {{ $p['vencido'] ? 'Vencido' : 'En fecha' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <p class="text-[11px] text-gray-400">
        Los filtros de tipo y respuesta son de riesgos y no aplican a esta pestaña. El avance de un plan es el promedio
        de sus tareas aprobadas; "vencido" es que tiene una tarea pendiente con la fecha pasada. El Excel trae además el
        detalle de cada tarea.
    </p>
</div>
