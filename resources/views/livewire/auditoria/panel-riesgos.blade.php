@php
    // Colores por clasificación (bajo/moderado/crítico). "tint" para celdas vacías
    // (dibuja las bandas de severidad aunque no haya riesgos); "full" para celdas
    // con riesgos; "chip" para badges y leyendas.
    $paleta = [
        'verde'    => ['tint' => 'bg-green-50',  'full' => 'bg-green-500', 'chip' => 'bg-green-100 text-green-700', 'bar' => 'bg-green-500'],
        'amarillo' => ['tint' => 'bg-amber-50',  'full' => 'bg-amber-400', 'chip' => 'bg-amber-100 text-amber-700', 'bar' => 'bg-amber-400'],
        'rojo'     => ['tint' => 'bg-red-50',     'full' => 'bg-red-500',   'chip' => 'bg-red-100 text-red-700',     'bar' => 'bg-red-500'],
    ];
    $colorPorEtiqueta = ['bajo' => 'verde', 'moderado' => 'amarillo', 'critico' => 'rojo'];
    $mitigado = $exposicionTotal - $exposicionResidual;
    $pctMitigado = $exposicionTotal > 0 ? round($mitigado / $exposicionTotal * 100) : 0;
@endphp

<div class="space-y-8">

    {{-- Encabezado --}}
    <div class="flex items-end justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Panel de Riesgos</h1>
            <p class="text-sm text-gray-500 mt-1">
                Estado de situación de la matriz que podés ver: mapa de calor, cuánto mitigan los controles y qué tenés por hacer.
            </p>
        </div>
        <div class="flex items-center gap-5">
            <label class="flex items-center gap-2 cursor-pointer select-none" title="Encendido: sólo riesgos aprobados. Apagado: también los validados. Nunca borradores.">
                <span class="text-xs font-medium text-gray-600">Ver solo aprobados</span>
                <button type="button" wire:click="$toggle('soloAprobados')"
                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors {{ $soloAprobados ? 'bg-indigo-600' : 'bg-gray-300' }}">
                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $soloAprobados ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                </button>
            </label>
            <span class="text-xs text-gray-400">{{ $total }} {{ Str::plural('riesgo', $total) }} en tu vista</span>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
            $tiles = [
                ['label' => 'Críticos (residual)',  'valor' => $distCriticidad['critico'],  'clase' => 'text-red-600'],
                ['label' => 'Moderados (residual)', 'valor' => $distCriticidad['moderado'], 'clase' => 'text-amber-600'],
                ['label' => 'Bajos (residual)',     'valor' => $distCriticidad['bajo'],     'clase' => 'text-green-600'],
                ['label' => 'Riesgos en el panel',  'valor' => $total,                      'clase' => 'text-indigo-600'],
            ];
        @endphp
        @foreach($tiles as $t)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="text-3xl font-extrabold {{ $t['clase'] }}">{{ $t['valor'] }}</div>
                <div class="text-xs font-medium text-gray-500 mt-1">{{ $t['label'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Mapa de calor + detalle --}}
    <div wire:key="mapa-{{ $soloAprobados ? 'apr' : 'val' }}"
         x-data="{ celda: null, riesgos: @js($riesgosJs) }"
         class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Mapa de calor</h2>
                <p class="text-xs text-gray-500 mt-0.5">Riesgo inherente: impacto × probabilidad. Hacé clic en una celda para ver sus riesgos.</p>
            </div>
            <div class="flex items-center gap-3 text-xs">
                @foreach(['critico' => 'Crítico', 'moderado' => 'Moderado', 'bajo' => 'Bajo'] as $et => $label)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded {{ $paleta[$colorPorEtiqueta[$et]]['full'] }}"></span>
                        <span class="text-gray-500">{{ $label }}</span>
                    </span>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col xl:flex-row gap-6">
            {{-- Grilla --}}
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
                                            $clave = $imp.'-'.$prob;
                                            $cls = \App\Models\Auditoria\Riesgo::clasificacion($imp + $prob);
                                            $count = $celdas[$clave] ?? 0;
                                            $bg = $count ? $paleta[$cls['color']]['full'].' text-white' : $paleta[$cls['color']]['tint'].' text-transparent';
                                        @endphp
                                        <td>
                                            <button type="button"
                                                @click="celda = (celda === '{{ $clave }}' ? null : '{{ $clave }}')"
                                                x-bind:class="celda === '{{ $clave }}' ? 'ring-2 ring-indigo-600 ring-offset-1' : ''"
                                                class="w-9 h-9 rounded-md text-xs font-bold flex items-center justify-center transition {{ $bg }} {{ $count ? 'hover:opacity-80 cursor-pointer' : 'cursor-default' }}"
                                                @if(!$count) disabled @endif>
                                                {{ $count ?: '·' }}
                                            </button>
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

            {{-- Detalle de la celda seleccionada --}}
            <div class="flex-1 min-w-0 border-l border-gray-100 xl:pl-6">
                <template x-if="!celda">
                    <div class="h-full flex items-center justify-center text-center text-sm text-gray-400 italic py-10">
                        Elegí una celda del mapa para ver qué riesgos caen ahí.
                    </div>
                </template>
                <template x-if="celda">
                    <div>
                        <div class="text-xs font-semibold text-gray-500 mb-3">
                            Riesgos en la celda
                            <span class="font-mono text-gray-700" x-text="'(impacto ' + celda.split('-')[0] + ', prob. ' + celda.split('-')[1] + ')'"></span>
                        </div>
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                            <template x-for="r in riesgos.filter(x => x.celda === celda)" :key="r.codigo">
                                <a :href="r.url" class="block rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50/40 px-3 py-2 transition">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-mono text-xs text-gray-500" x-text="r.codigo"></span>
                                        <span class="text-[11px] px-1.5 py-0.5 rounded"
                                              :class="{
                                                'bg-red-100 text-red-700': r.clasificacion === 'critico',
                                                'bg-amber-100 text-amber-700': r.clasificacion === 'moderado',
                                                'bg-green-100 text-green-700': r.clasificacion === 'bajo',
                                              }"
                                              x-text="'residual ' + r.residual"></span>
                                    </div>
                                    <div class="text-sm font-medium text-gray-800 mt-0.5 truncate" x-text="r.nombre"></div>
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        <span x-text="r.tipo"></span> · <span x-text="'total ' + r.total"></span> · <span class="capitalize" x-text="r.estado"></span>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Antes y después de mitigar: dos rieles de cubitos 0→20 --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Antes y después de mitigar</h2>
                <p class="text-xs text-gray-500 mt-0.5">Cada cubo es un valor de riesgo (0 nulo → 20 máximo). Comparar las dos filas muestra cuánto corren los controles y planes el panorama hacia la izquierda.</p>
            </div>
            <div class="text-right">
                <div class="text-2xl font-extrabold text-indigo-600">{{ $pctMitigado }}%</div>
                <div class="text-[11px] text-gray-500">exposición mitigada ({{ $mitigado }} de {{ $exposicionTotal }})</div>
            </div>
        </div>

        @php
            $filas = [
                ['titulo' => 'Antes de mitigar',   'sub' => 'valor inherente', 'pista' => $pistaInherente],
                ['titulo' => 'Después de mitigar', 'sub' => 'valor residual',  'pista' => $pistaResidual],
            ];
        @endphp

        <div class="overflow-x-auto">
            <div class="space-y-2 min-w-max">
                @foreach($filas as $fila)
                    <div class="flex items-center gap-3">
                        <div class="w-32 shrink-0 text-right">
                            <div class="text-xs font-semibold text-gray-700">{{ $fila['titulo'] }}</div>
                            <div class="text-[10px] text-gray-400">{{ $fila['sub'] }}</div>
                        </div>
                        <div class="flex gap-1">
                            @for($v = 0; $v <= 20; $v++)
                                @php
                                    $cls = \App\Models\Auditoria\Riesgo::clasificacion($v);
                                    $n = $fila['pista'][$v] ?? 0;
                                    $bg = $n ? $paleta[$cls['color']]['full'].' text-white' : $paleta[$cls['color']]['tint'].' text-transparent';
                                @endphp
                                <div class="w-8 h-8 rounded-md text-xs font-bold flex items-center justify-center {{ $bg }}"
                                     title="Valor {{ $v }} · {{ ucfirst($cls['etiqueta']) }}{{ $n ? ' · '.$n.' riesgo(s)' : '' }}">
                                    {{ $n ?: '·' }}
                                </div>
                            @endfor
                        </div>
                    </div>
                @endforeach

                {{-- Eje de valor --}}
                <div class="flex items-center gap-3 pt-1">
                    <div class="w-32 shrink-0"></div>
                    <div class="flex gap-1">
                        @for($v = 0; $v <= 20; $v++)
                            <div class="w-8 text-center text-[10px] font-mono text-gray-400">{{ $v % 5 === 0 ? $v : '' }}</div>
                        @endfor
                    </div>
                </div>
            </div>
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

    {{-- Accesos expandidos: Pendientes + Vencimientos --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Pendientes --}}
        @if($pendientes['mostrar'])
            <a href="{{ route('auditoria.pendientes.index') }}"
               class="group bg-white rounded-xl border border-gray-200 shadow-sm p-6 hover:border-indigo-300 hover:shadow transition flex flex-col">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-gray-900">Pendientes</h2>
                    <span class="text-xs text-indigo-600 font-semibold group-hover:underline">Ver todo →</span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">Lo que tenés para {{ $pendientes['accion'] }}.</p>
                <div class="flex items-end gap-8 mt-5">
                    <div>
                        <div class="text-4xl font-extrabold text-indigo-600">{{ $pendientes['total'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">en total</div>
                    </div>
                    <div class="text-sm text-gray-600 space-y-1 pb-1">
                        <div><span class="font-bold text-gray-900">{{ $pendientes['entidades'] }}</span> elementos para {{ $pendientes['accion'] }}</div>
                        <div><span class="font-bold text-gray-900">{{ $pendientes['actualizaciones'] }}</span> cambios propuestos</div>
                    </div>
                </div>
            </a>
        @else
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 flex items-center justify-center text-center text-sm text-gray-400 italic">
                No tenés pendientes de validación o aprobación asignados a tu rol.
            </div>
        @endif

        {{-- Vencimientos --}}
        <a href="{{ route('auditoria.vencimientos.index') }}"
           class="group bg-white rounded-xl border border-gray-200 shadow-sm p-6 hover:border-indigo-300 hover:shadow transition flex flex-col">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-900">Vencimientos</h2>
                <span class="text-xs text-indigo-600 font-semibold group-hover:underline">Ver todo →</span>
            </div>
            <p class="text-xs text-gray-500 mt-0.5">Tareas comprometidas más cercanas y vencidas.</p>
            <div class="flex items-center gap-3 mt-4">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-red-100 text-red-700">
                    {{ $vencimientos['vencidas'] }} vencidas
                </span>
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">
                    {{ $vencimientos['porVencer'] }} por vencer
                </span>
            </div>
            <div class="mt-4 divide-y divide-gray-100 border-t border-gray-100">
                @forelse($vencimientos['proximas'] as $tarea)
                    @php $vencida = $tarea->fecha->lt($vencimientos['hoy']); @endphp
                    <div class="flex items-center justify-between py-2 gap-2">
                        <span class="text-sm text-gray-700 truncate">{{ $tarea->nombre }}</span>
                        <span class="text-xs font-medium whitespace-nowrap {{ $vencida ? 'text-red-600' : 'text-gray-500' }}">
                            {{ $tarea->fecha->format('d/m/Y') }}
                        </span>
                    </div>
                @empty
                    <div class="py-4 text-center text-sm text-gray-400 italic">Sin tareas con vencimiento a la vista.</div>
                @endforelse
            </div>
        </a>

    </div>

</div>
