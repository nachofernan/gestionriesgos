@php
    $paleta = [
        'verde'    => ['tint' => 'bg-green-50', 'full' => 'bg-green-500'],
        'amarillo' => ['tint' => 'bg-amber-50', 'full' => 'bg-amber-400'],
        'rojo'     => ['tint' => 'bg-red-50',   'full' => 'bg-red-500'],
    ];
    $tiles = [
        ['etiqueta' => 'critico',  'titulo' => 'Críticos',  'clase' => 'text-red-600'],
        ['etiqueta' => 'moderado', 'titulo' => 'Moderados', 'clase' => 'text-amber-600'],
        ['etiqueta' => 'bajo',     'titulo' => 'Bajos',     'clase' => 'text-green-600'],
    ];
@endphp

<div class="space-y-6">

    {{-- KPIs: inherente → residual --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-3xl font-extrabold text-indigo-600">{{ $total }}</div>
            <div class="text-xs font-medium text-gray-500 mt-1">{{ Str::plural('Riesgo', $total) }} en tu vista</div>
        </div>
        @foreach($tiles as $t)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-baseline gap-2">
                    <span class="text-lg font-bold text-gray-400" title="Inherente">{{ $kpis['inherente'][$t['etiqueta']] }}</span>
                    <span class="text-gray-300">→</span>
                    <span class="text-3xl font-extrabold {{ $t['clase'] }}" title="Residual">{{ $kpis['residual'][$t['etiqueta']] }}</span>
                </div>
                <div class="text-xs font-medium text-gray-500 mt-1">{{ $t['titulo'] }} <span class="text-gray-400">(inherente → residual)</span></div>
            </div>
        @endforeach
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-3xl font-extrabold {{ $kpis['plan']['vencido'] ? 'text-red-600' : 'text-gray-400' }}">{{ $kpis['plan']['vencido'] }}</div>
            <div class="text-xs font-medium text-gray-500 mt-1">Con plan vencido <span class="text-gray-400">· {{ $kpis['plan']['sin_plan'] }} sin plan</span></div>
        </div>
    </div>

    {{-- Accesos: Pendientes y Vencimientos --}}
    <div class="flex flex-wrap gap-3 text-sm">
        @if($pendientes['mostrar'])
            <a href="{{ route('auditoria.pendientes.index') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 hover:border-indigo-300 transition">
                <span class="font-bold text-indigo-600">{{ $pendientes['total'] }}</span>
                <span class="text-gray-600">pendientes para {{ $pendientes['accion'] }}</span>
                <span class="text-indigo-600">→</span>
            </a>
        @endif
        <a href="{{ route('auditoria.vencimientos.index') }}"
           class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 hover:border-indigo-300 transition">
            <span class="font-bold text-red-600">{{ $vencimientos['vencidas'] }}</span>
            <span class="text-gray-600">tareas vencidas ·</span>
            <span class="font-bold text-amber-600">{{ $vencimientos['porVencer'] }}</span>
            <span class="text-gray-600">por vencer en 30 días</span>
            <span class="text-indigo-600">→</span>
        </a>
    </div>

    {{-- Matriz de calor: inherente vs residual (simulación) --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="mb-4">
            <h2 class="text-lg font-bold text-gray-900">Matriz de calor</h2>
            <p class="text-xs text-gray-500 mt-0.5">
                Impacto × probabilidad. A la derecha, una simulación visual de cómo queda después de mitigar: reparte la
                mitigación de cada riesgo entre ambos ejes. No reemplaza el valor residual real.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach([['titulo' => 'Inherente', 'celdas' => $celdas], ['titulo' => 'Residual (simulación)', 'celdas' => $celdasResidual]] as $matriz)
                <div>
                    <div class="text-xs font-semibold text-gray-600 text-center mb-2">{{ $matriz['titulo'] }}</div>
                    <div class="overflow-x-auto">
                        <div class="flex">
                            <div class="flex flex-col items-center justify-center pr-2">
                                <span class="text-[11px] font-semibold text-gray-500 -rotate-90 whitespace-nowrap">Impacto →</span>
                            </div>
                            <table class="border-separate" style="border-spacing: 3px;">
                                <tbody>
                                    @for($imp = 10; $imp >= 0; $imp--)
                                        <tr>
                                            <td class="text-[10px] text-gray-400 font-mono pr-1 text-right align-middle">{{ $imp }}</td>
                                            @for($prob = 0; $prob <= 10; $prob++)
                                                @php
                                                    $cls = \App\Models\Auditoria\Riesgo::clasificacion($imp + $prob);
                                                    $count = $matriz['celdas'][$imp.'-'.$prob] ?? 0;
                                                    $bg = $count ? $paleta[$cls['color']]['full'].' text-white' : $paleta[$cls['color']]['tint'].' text-transparent';
                                                @endphp
                                                <td>
                                                    <div class="w-8 h-8 rounded-md text-xs font-bold flex items-center justify-center {{ $bg }}"
                                                         title="Impacto {{ $imp }} · Prob. {{ $prob }}{{ $count ? ' · '.$count.' riesgo(s)' : '' }}">
                                                        {{ $count ?: '·' }}
                                                    </div>
                                                </td>
                                            @endfor
                                        </tr>
                                    @endfor
                                    <tr>
                                        <td></td>
                                        @for($prob = 0; $prob <= 10; $prob++)
                                            <td class="text-[10px] text-gray-400 font-mono text-center pt-1">{{ $prob }}</td>
                                        @endfor
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-[11px] font-semibold text-gray-500 text-center mt-1 pl-8">Probabilidad →</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center gap-4 text-xs mt-4">
            @foreach(['rojo' => 'Crítico (14-20)', 'amarillo' => 'Moderado (10-13)', 'verde' => 'Bajo (0-9)'] as $color => $label)
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded {{ $paleta[$color]['full'] }}"></span>
                    <span class="text-gray-500">{{ $label }}</span>
                </span>
            @endforeach
        </div>
    </div>
</div>
