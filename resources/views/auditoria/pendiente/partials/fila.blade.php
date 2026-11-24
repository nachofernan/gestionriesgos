{{--
    Una fila de Pendientes, pintada según su naturaleza (D-059):
    - alta: un elemento nuevo en borrador/validado, con lo que hace falta para decidir.
    - cambio: una propuesta sobre algo aprobado, con sus primeras líneas de cambio.
    - baja: una propuesta de eliminación, con la transición de estado y el motivo a la vista.
    Variable esperada: $detalle (payload de PendienteController::detalles(), con los
    permisos ya resueltos). Vive dentro del x-data de la página: usa muestra() y
    procesados. Los botones disparan los mismos flujos que el modal de detalle.
--}}
@php
    $clave = $detalle['clave'];
    $naturaleza = $detalle['naturaleza'];
    $accion = $detalle['accion'];
    $esBaja = $naturaleza === 'baja';
    $etiquetaAccion = ucfirst($accion).($esBaja ? ' baja' : '');
    $claseAccion = match (true) {
        $esBaja => 'bg-rose-600 text-white hover:bg-rose-700',
        $accion === 'validar' => 'bg-blue-50 text-blue-700 hover:bg-blue-100',
        default => 'bg-green-50 text-green-700 hover:bg-green-100',
    };
    $abrirAccion = $detalle['modo'] === 'propuesta'
        ? "Livewire.dispatch('abrir-accion-actualizacion', { actualizacionId: {$detalle['id']}, accion: '{$accion}' })"
        : "Livewire.dispatch('abrir-validacion-cascada', { tipo: '{$detalle['tipo']}', id: {$detalle['id']}, accion: '{$accion}', sinRedireccion: true })";

    $transicion = $esBaja ? collect($detalle['diff']['campos'])->firstWhere('campo', 'estado') : null;
    $tareasQueCaen = $esBaja ? count($detalle['diff']['relaciones']['tareas']['items'] ?? []) : 0;
    $datos = $detalle['modo'] === 'entidad'
        ? collect($detalle['datos'])->when($detalle['tipo'] === 'riesgo', fn ($c) => $c->whereIn('etiqueta', ['Valor total', 'Valor residual', 'Respuesta']))
            ->reject(fn ($fila) => $fila['etiqueta'] === 'PEIS')
        : collect();
    $lineas = collect($detalle['lineas'] ?? []);
@endphp
<div x-show="muestra('{{ $clave }}')" x-cloak
     class="px-4 py-3 flex items-start justify-between gap-4 {{ $detalle['junto_con'] ? 'pl-10 bg-rose-50/30' : '' }}">

    <div class="min-w-0 flex-1" :class="procesados.includes('{{ $clave }}') ? 'opacity-40' : ''">
        <div class="flex flex-wrap items-center gap-1.5">
            @if($esBaja)
                <span class="text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 tracking-wide">BAJA</span>
            @endif
            <span class="text-[10px] font-bold text-gray-400 uppercase">{{ $detalle['tipo_label'] }}</span>
            <button type="button"
                    x-on:click="$dispatch('abrir-detalle-pendiente', @js($detalle))"
                    class="text-sm font-semibold text-left hover:text-indigo-600 {{ $esBaja ? 'text-gray-900' : 'text-gray-800' }}">
                {{ $detalle['nombre'] }}
            </button>
            @if($detalle['peis'] ?? false)
                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700">PEIS</span>
            @endif
            @if($detalle['revision_inicial'])
                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-violet-50 text-violet-700" title="Se firmó en la revisión inicial apoyándose en propuestas que esperan">Revisión inicial</span>
            @endif
        </div>

        <p class="text-xs text-gray-400 mt-0.5">
            {{ $detalle['area'] }}
            @if($detalle['propietario'])
                &middot; {{ $detalle['propietario'] }}
            @endif
            @if($detalle['fecha'] ?? null)
                &middot; <span title="{{ $detalle['fecha_completa'] }}">{{ $detalle['fecha'] }}</span>
            @endif
        </p>

        @if($detalle['junto_con'])
            <p class="text-xs text-rose-700 mt-1">↳ Junto con la baja del riesgo «{{ $detalle['junto_con'] }}»</p>
        @endif

        @switch($naturaleza)
            @case('alta')
                @if($datos->isNotEmpty())
                    <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-0.5 text-xs">
                        @foreach($datos as $fila)
                            <span>
                                <span class="text-gray-400">{{ $fila['etiqueta'] }}</span>
                                <span class="font-semibold {{ match ($fila['color'] ?? null) { 'green' => 'text-green-700', 'amber' => 'text-amber-700', 'red' => 'text-red-700', default => 'text-gray-700' } }}">{{ $fila['valor'] }}</span>
                            </span>
                        @endforeach
                    </div>
                @endif
                @break

            @case('cambio')
                @if($lineas->isNotEmpty())
                    <ul class="mt-1.5 space-y-0.5 text-xs text-gray-700">
                        @foreach($lineas->take(2) as $linea)
                            <li class="truncate">{{ $linea }}</li>
                        @endforeach
                        @if($lineas->count() > 2)
                            <li class="text-gray-400">y {{ $lineas->count() - 2 }} más…</li>
                        @endif
                    </ul>
                @endif
                @if($detalle['mensaje'])
                    <p class="text-xs text-gray-500 italic mt-1 truncate">“{{ $detalle['mensaje'] }}”</p>
                @endif
                @break

            @case('baja')
                <p class="mt-1.5 text-xs">
                    <span class="text-gray-500">{{ $transicion['antes'] ?? '—' }}</span>
                    <span class="mx-1 text-rose-400">→</span>
                    <span class="font-bold text-rose-700">Eliminado</span>
                    @if($tareasQueCaen)
                        <span class="text-gray-400">&middot; caen {{ $tareasQueCaen }} {{ $tareasQueCaen === 1 ? 'tarea' : 'tareas' }} con el plan</span>
                    @endif
                </p>
                @if($detalle['mensaje'])
                    <p class="mt-1 text-sm text-gray-700 whitespace-pre-line border-l-2 border-rose-200 pl-2">{{ $detalle['mensaje'] }}</p>
                @endif
                @break
        @endswitch
    </div>

    <div class="flex items-center gap-2 shrink-0 pt-0.5">
        <span x-show="procesados.includes('{{ $clave }}')" x-cloak class="text-xs font-bold text-green-600">Hecho ✓</span>

        <template x-if="!procesados.includes('{{ $clave }}')">
            <div class="flex items-center gap-2">
                @if($detalle['puede_accion'])
                    <button type="button" onclick="{{ $abrirAccion }}"
                            class="px-3 py-1 {{ $claseAccion }} text-xs font-bold rounded-lg transition-colors">
                        {{ $etiquetaAccion }}
                    </button>
                @endif
                @if($detalle['puede_rechazar'])
                    @php
                        // Rechazar una baja es conservar el elemento: no va en rojo.
                        $claseRechazar = $esBaja
                            ? 'px-3 py-1 bg-gray-100 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-200 transition-colors'
                            : 'px-3 py-1 bg-red-50 text-red-700 text-xs font-bold rounded-lg hover:bg-red-100 transition-colors';
                    @endphp
                    @if($detalle['modo'] === 'propuesta')
                        <button type="button"
                                onclick="Livewire.dispatch('abrir-accion-actualizacion', { actualizacionId: {{ $detalle['id'] }}, accion: 'rechazar' })"
                                class="{{ $claseRechazar }}">
                            Rechazar
                        </button>
                    @else
                        <x-auditoria.boton-rechazar :url="$detalle['url_rechazar']" :clase="$claseRechazar" />
                    @endif
                @endif
            </div>
        </template>
    </div>
</div>
