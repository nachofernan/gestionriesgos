@php
    // Colores por clasificación (bajo/moderado/crítico). "tint" para cubos vacíos
    // (dibuja las bandas de severidad aunque no haya riesgos); "full" para cubos
    // con riesgos.
    $paleta = [
        'verde'    => ['tint' => 'bg-green-50', 'full' => 'bg-green-500'],
        'amarillo' => ['tint' => 'bg-amber-50', 'full' => 'bg-amber-400'],
        'rojo'     => ['tint' => 'bg-red-50',   'full' => 'bg-red-500'],
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
                Estado de situación de la matriz que podés ver: cuánto mitigan los controles y planes, y qué tenés por hacer.
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

    {{-- Filtros --}}
    <div class="flex items-center gap-3 flex-wrap">
        <span class="text-xs font-medium text-gray-500">Filtrar por</span>
        <select wire:model.live="filtroTipo"
                class="text-xs border-gray-300 rounded-lg py-1.5 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">Todos los tipos</option>
            @foreach($tipos as $tipo)
                <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
            @endforeach
        </select>
        <select wire:model.live="filtroRespuesta"
                class="text-xs border-gray-300 rounded-lg py-1.5 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">Todas las respuestas</option>
            @foreach($respuestas as $respuesta)
                <option value="{{ $respuesta->value }}">{{ $respuesta->label() }}</option>
            @endforeach
        </select>
        @if($filtroTipo !== '' || $filtroRespuesta !== '')
            <button type="button" wire:click="limpiarFiltros"
                    class="text-xs text-indigo-600 hover:underline">Limpiar filtros</button>
        @endif
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

    {{-- Antes y después de mitigar: rieles interactivos + listado --}}
    <div wire:key="rieles-{{ $soloAprobados ? 'apr' : 'val' }}-{{ $filtroTipo }}-{{ $filtroRespuesta }}"
         x-data="{
            riesgos: @js($riesgosJs),
            sel: null,
            hov: null,
            seleccionar(rail, v) { this.sel = (this.sel && this.sel.rail === rail && this.sel.v === v) ? null : { rail, v }; },
            activa(rail, v) { return this.sel && this.sel.rail === rail && this.sel.v === v; },
            resaltada(rail, v) { return this.hov && ((rail === 'inherente' && this.hov.total === v) || (rail === 'residual' && this.hov.residual === v)); },
            lista() { return this.sel ? this.riesgos.filter(r => (this.sel.rail === 'inherente' ? r.total : r.residual) === this.sel.v) : []; }
         }"
         class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">

        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Antes y después de mitigar</h2>
                <p class="text-xs text-gray-500 mt-0.5">Cada cubo es un valor de riesgo (0 nulo → 20 máximo). Hacé clic en un cubo para ver sus riesgos; al pasar el mouse sobre uno, se marca dónde queda en ambos rieles.</p>
            </div>
            <div class="text-right">
                <div class="text-2xl font-extrabold text-indigo-600">{{ $pctMitigado }}%</div>
                <div class="text-[11px] text-gray-500">exposición mitigada ({{ $mitigado }} de {{ $exposicionTotal }})</div>
            </div>
        </div>

        @php
            $filas = [
                ['rail' => 'inherente', 'titulo' => 'Antes de mitigar',   'sub' => 'valor inherente', 'pista' => $pistaInherente],
                ['rail' => 'residual',  'titulo' => 'Después de mitigar', 'sub' => 'valor residual',  'pista' => $pistaResidual],
            ];
        @endphp

        <div class="overflow-x-auto">
            <div class="space-y-2 min-w-max py-2">
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
                                <button type="button"
                                    @if($n) @click="seleccionar('{{ $fila['rail'] }}', {{ $v }})" @else disabled @endif
                                    :class="{
                                        'ring-2 ring-indigo-600 ring-offset-1': activa('{{ $fila['rail'] }}', {{ $v }}),
                                        'ring-2 ring-indigo-500 ring-offset-1': resaltada('{{ $fila['rail'] }}', {{ $v }}),
                                        'opacity-20': hov && !resaltada('{{ $fila['rail'] }}', {{ $v }})
                                    }"
                                    class="w-8 h-8 rounded-md text-xs font-bold flex items-center justify-center transition {{ $bg }} {{ $n ? 'cursor-pointer' : 'cursor-default' }}"
                                    title="Valor {{ $v }} · {{ ucfirst($cls['etiqueta']) }}{{ $n ? ' · '.$n.' riesgo(s)' : '' }}">
                                    {{ $n ?: '·' }}
                                </button>
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

        {{-- Listado de los riesgos del cubo seleccionado --}}
        <div class="mt-5 border-t border-gray-100 pt-5">
            <template x-if="!sel">
                <div class="text-center text-sm text-gray-400 italic py-6">
                    Hacé clic en un cubo de cualquier riel para ver los riesgos con ese valor.
                </div>
            </template>
            <template x-if="sel">
                <div>
                    <div class="text-xs font-semibold text-gray-500 mb-3">
                        Riesgos con
                        <span x-text="sel.rail === 'inherente' ? 'valor inherente' : 'valor residual'"></span>
                        = <span class="font-mono text-gray-700" x-text="sel.v"></span>
                        <span class="text-gray-300 font-normal">— pasá el mouse por uno para ubicarlo en ambos rieles</span>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-2">
                        <template x-for="r in lista()" :key="r.codigo">
                            <a :href="r.url"
                               @mouseenter="hov = r" @mouseleave="hov = null"
                               class="block rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50/40 px-3 py-2 transition">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-xs text-gray-500" x-text="r.codigo"></span>
                                    <span class="text-[11px] font-semibold text-gray-600">
                                        <span x-text="'total ' + r.total"></span>
                                        <span class="text-gray-300">→</span>
                                        <span x-text="'residual ' + r.residual"></span>
                                    </span>
                                </div>
                                <div class="text-sm font-medium text-gray-800 mt-0.5 truncate" x-text="r.nombre"></div>
                                <div class="text-[11px] text-gray-400 mt-0.5">
                                    <span x-text="r.tipo"></span> · <span class="capitalize" x-text="r.estado"></span>
                                </div>

                                {{-- Motivos de la baja: controles y planes, con su aporte real a la mitigación --}}
                                <div class="mt-2 pt-2 border-t border-gray-100 space-y-0.5" x-show="r.controles.length || r.planes.length">
                                    <template x-for="(c, idx) in r.controles" :key="'ctrl-' + idx">
                                        <div class="text-[11px] flex items-center justify-between gap-2">
                                            <span class="text-gray-500 truncate" x-text="'Control: ' + c.nombre"></span>
                                            <span class="whitespace-nowrap font-medium text-green-600" x-text="'−' + c.mitigacion"></span>
                                        </div>
                                    </template>
                                    <template x-for="(p, idx) in r.planes" :key="'plan-' + idx">
                                        <div class="text-[11px] flex items-center justify-between gap-2">
                                            <span class="text-gray-500 truncate" x-text="'Plan: ' + p.nombre"></span>
                                            <span class="whitespace-nowrap font-medium"
                                                  :class="p.aporta ? 'text-green-600' : 'text-gray-400'"
                                                  x-text="p.aporta ? ('−' + p.mitigacion) : ((p.avance ?? 0) + '% · no descuenta')"></span>
                                        </div>
                                    </template>
                                </div>
                            </a>
                        </template>
                    </div>
                </div>
            </template>
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

    {{-- Matriz de calor 2D: inherente vs residual (simulación), en paralelo --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Matriz de calor <span class="font-normal text-gray-400 text-sm">— inherente vs. residual</span></h2>
                <p class="text-xs text-gray-500 mt-0.5">Impacto × probabilidad. La suma define la severidad (misma escala que los rieles de arriba). A la derecha, la simulación de cómo queda el mapa después de mitigar.</p>
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

        <div class="rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-700 text-[11px] px-3 py-2 mb-5">
            La columna "residual" es una simulación de cómo está el mapa actual a fines exclusivamente visuales:
            reparte la mitigación de cada riesgo proporcionalmente entre impacto y probabilidad para poder ubicarlo
            en la grilla. No reemplaza el valor residual real, que sigue siendo la suma única.
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach([['titulo' => 'Inherente', 'celdas' => $celdas], ['titulo' => 'Residual — simulación', 'celdas' => $celdasResidual]] as $matriz)
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
                                                    <div class="w-9 h-9 rounded-md text-xs font-bold flex items-center justify-center {{ $bg }}"
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
    </div>

</div>
