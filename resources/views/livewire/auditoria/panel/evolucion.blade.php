@php
    $r = $reporte['resumen'];
    $conMovimiento = collect($reporte['planes'])->filter(fn ($p) => ($p['variacion'] ?? 0) !== 0 || $p['tareas_completadas'] > 0);
    $sinMovimiento = count($reporte['planes']) - $conMovimiento->count();
@endphp

<div class="space-y-6">

    {{-- Movimiento de riesgos --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            ['valor' => $r['inicio'], 'titulo' => 'Aprobados al '.$reporte['desde']->format('d/m/Y'), 'clase' => 'text-gray-700'],
            ['valor' => '+'.$r['aprobados'], 'titulo' => 'Aprobados en el período', 'clase' => 'text-green-600'],
            ['valor' => $r['rechazados'], 'titulo' => 'Rechazados en el período', 'clase' => 'text-red-600'],
            ['valor' => $r['final'], 'titulo' => 'Aprobados al '.$reporte['hasta']->format('d/m/Y'), 'clase' => 'text-indigo-600'],
        ] as $t)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="text-3xl font-extrabold {{ $t['clase'] }}">{{ $t['valor'] }}</div>
                <div class="text-xs font-medium text-gray-500 mt-1">{{ $t['titulo'] }}</div>
            </div>
        @endforeach
    </div>

    @if($reporte['sinFecha'] > 0)
        <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs px-3 py-2">
            {{ $reporte['sinFecha'] }} {{ Str::plural('riesgo', $reporte['sinFecha']) }} aprobados no tienen fecha de aprobación registrada
            (vienen de la carga inicial): se cuentan como aprobados antes del período.
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach([['titulo' => 'Por tipo de riesgo', 'filas' => $reporte['porTipo']], ['titulo' => 'Por gerencia', 'filas' => $reporte['porGerencia']]] as $tabla)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
                <div class="px-4 py-3 border-b border-gray-100 text-sm font-bold text-gray-900">{{ $tabla['titulo'] }}</div>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-600">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium"></th>
                            <th class="px-3 py-2 text-right font-medium">Al inicio</th>
                            <th class="px-3 py-2 text-right font-medium">Aprobados</th>
                            <th class="px-3 py-2 text-right font-medium">Rechazados</th>
                            <th class="px-3 py-2 text-right font-medium">Al final</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($tabla['filas'] as $f)
                            <tr>
                                <td class="px-4 py-2 text-gray-800">{{ $f['nombre'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-600">{{ $f['inicio'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums {{ $f['aprobados'] ? 'text-green-600 font-semibold' : 'text-gray-300' }}">{{ $f['aprobados'] ? '+'.$f['aprobados'] : '·' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums {{ $f['rechazados'] ? 'text-red-600 font-semibold' : 'text-gray-300' }}">{{ $f['rechazados'] ?: '·' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-bold text-gray-900">{{ $f['final'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400 italic">Sin movimiento.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>

    {{-- Riesgos aprobados en el período --}}
    @if(count($reporte['aprobados']))
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-gray-100 text-sm font-bold text-gray-900">Riesgos aprobados en el período</div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-600">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">Riesgo</th>
                        <th class="px-3 py-2 text-left font-medium">Tipo</th>
                        <th class="px-3 py-2 text-left font-medium">Gerencia</th>
                        <th class="px-3 py-2 text-right font-medium">Aprobado el</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($reporte['aprobados'] as $a)
                        <tr>
                            <td class="px-4 py-2"><a href="{{ route('auditoria.riesgos.show', $a['id']) }}" class="text-indigo-700 hover:underline">{{ $a['nombre'] }}</a></td>
                            <td class="px-3 py-2 text-gray-600">{{ $a['tipo'] }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $a['gerencia'] }}</td>
                            <td class="px-3 py-2 text-right text-gray-600 tabular-nums">{{ $a['aprobado_en']->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Avance de planes --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-gray-100">
            <div class="text-sm font-bold text-gray-900">Avance de planes en el período</div>
            <div class="text-[11px] text-gray-400 mt-0.5">
                Reconstruido desde el historial de avance de las tareas aprobadas.
                @if($sinMovimiento > 0) {{ $sinMovimiento }} {{ Str::plural('plan', $sinMovimiento) }} sin movimiento no se listan (sí están en el Excel). @endif
            </div>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-600">
                <tr>
                    <th class="px-4 py-2 text-left font-medium">Plan de acción</th>
                    <th class="px-3 py-2 text-left font-medium">Gerencia</th>
                    <th class="px-3 py-2 text-right font-medium">Al inicio</th>
                    <th class="px-3 py-2 text-right font-medium">Al final</th>
                    <th class="px-3 py-2 text-right font-medium">Variación</th>
                    <th class="px-3 py-2 text-right font-medium">Tareas completadas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($conMovimiento as $p)
                    <tr>
                        <td class="px-4 py-2"><a href="{{ route('auditoria.planes.show', $p['id']) }}" class="text-indigo-700 hover:underline">{{ $p['nombre'] }}</a></td>
                        <td class="px-3 py-2 text-gray-600">{{ $p['gerencia'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-600">{{ $p['avance_inicio'] ?? '—' }}%</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-900 font-semibold">{{ $p['avance_final'] ?? '—' }}%</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold {{ ($p['variacion'] ?? 0) > 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ ($p['variacion'] ?? 0) > 0 ? '+' : '' }}{{ $p['variacion'] }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ $p['tareas_completadas'] ?: '·' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400 italic">Ningún plan registró avance en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
