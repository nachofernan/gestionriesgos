{{-- Tabla cruzada de los reportes por gerencia y por tipo. Recibe $reporte (ver ReportePorGerencia/ReportePorTipo). --}}
@php
    $colorCelda = ['critico' => 'text-red-600', 'moderado' => 'text-amber-600', 'bajo' => 'text-green-600', 'vencido' => 'text-red-600'];
@endphp

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
    @if($reporte['totales']['total'] === 0)
        <div class="p-10 text-center text-sm text-gray-400 italic">No hay riesgos con estos filtros.</div>
    @else
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                    <th rowspan="2" class="px-4 py-2 text-left font-semibold sticky left-0 bg-gray-50">{{ $reporte['encabezado'] }}</th>
                    <th rowspan="2" class="px-3 py-2 text-right font-semibold border-l border-gray-200">Total</th>
                    @foreach($reporte['grupos'] as $g)
                        @if(count($g['columnas']))
                            <th colspan="{{ count($g['columnas']) }}" class="px-3 pt-2 pb-1 text-center font-semibold border-l border-gray-200">{{ $g['titulo'] }}</th>
                        @endif
                    @endforeach
                </tr>
                <tr class="bg-gray-50 text-xs text-gray-600">
                    @foreach($reporte['grupos'] as $g)
                        @foreach($g['columnas'] as $etiqueta)
                            <th class="px-3 pb-2 pt-1 text-right font-medium whitespace-nowrap {{ $loop->first ? 'border-l border-gray-200' : '' }}">{{ $etiqueta }}</th>
                        @endforeach
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach(array_merge($reporte['filas'], [$reporte['totales']]) as $fila)
                    @php $esTotal = $loop->last; @endphp
                    <tr class="{{ $esTotal ? 'bg-gray-50 font-bold' : 'hover:bg-gray-50/60' }}">
                        <td class="px-4 py-2 text-gray-800 whitespace-nowrap sticky left-0 {{ $esTotal ? 'bg-gray-50' : 'bg-white' }}">{{ $fila['nombre'] }}</td>
                        <td class="px-3 py-2 text-right font-bold text-gray-900 border-l border-gray-200">{{ $fila['total'] }}</td>
                        @foreach($reporte['grupos'] as $g)
                            @foreach(array_keys($g['columnas']) as $clave)
                                @php $n = $fila[$g['clave']][$clave] ?? 0; @endphp
                                <td class="px-3 py-2 text-right tabular-nums {{ $loop->first ? 'border-l border-gray-200' : '' }}
                                           {{ $n ? ($colorCelda[$clave] ?? 'text-gray-800') : 'text-gray-300' }}">
                                    {{ $n ?: '·' }}
                                </td>
                            @endforeach
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
