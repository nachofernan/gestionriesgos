{{--
    Fila de entidades (Riesgo/Control/Objetivo/PlanAccion/Tarea) pendientes de
    validar o aprobar. Variables esperadas: $items, $tipo, $cfg, $accion,
    $detalles (payload del modal armado en PendienteController, con los permisos
    ya resueltos: los botones de la fila y los del modal leen los mismos flags).
--}}
<div>
    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">{{ $cfg['label'] }} ({{ $items->count() }})</h3>
    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100 overflow-hidden">
        @foreach($items as $item)
            @php $detalle = $detalles["{$tipo}-{$item->id}"]; @endphp
            <div class="px-4 py-3 flex items-center justify-between gap-4"
                 x-data="{ procesado: false, mensaje: '' }"
                 x-on:cascada-procesada.window="if ($event.detail.tipo === '{{ $tipo }}' && $event.detail.id === {{ $item->id }}) { procesado = true; mensaje = $event.detail.mensaje }">

                <x-auditoria.estado-punto :estado="$item->estado" soloPunto x-show="!procesado" />
                <div class="min-w-0 flex-1" :class="procesado ? 'opacity-40' : ''">
                    <button type="button"
                            x-on:click="$dispatch('abrir-detalle-pendiente', @js($detalle))"
                            class="text-sm font-semibold text-gray-800 hover:text-indigo-600 truncate block text-left">
                        {{ $item->nombre }}
                    </button>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $item->area?->nombre ?? 'Sin área' }}
                        @if($item->user)
                            &middot; {{ $item->user->name }}
                        @endif
                    </p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <span x-show="procesado" x-cloak class="text-xs font-bold text-green-600" x-text="mensaje || 'Hecho ✓'"></span>

                    <template x-if="!procesado">
                        <div class="flex items-center gap-2">
                            @if($detalle['puede_accion'])
                                <button type="button"
                                        onclick="Livewire.dispatch('abrir-validacion-cascada', { tipo: '{{ $tipo }}', id: {{ $item->id }}, accion: '{{ $accion }}', sinRedireccion: true })"
                                        class="px-3 py-1 {{ $accion === 'validar' ? 'bg-blue-50 text-blue-700 hover:bg-blue-100' : 'bg-green-50 text-green-700 hover:bg-green-100' }} text-xs font-bold rounded-lg transition-colors">
                                    {{ ucfirst($accion) }}
                                </button>
                            @endif
                            @if($detalle['puede_rechazar'])
                                <form action="{{ $detalle['url_rechazar'] }}" method="POST"
                                      onsubmit="return confirm('¿Rechazar este elemento?')">
                                    @csrf
                                    <button type="submit" class="px-3 py-1 bg-red-50 text-red-700 text-xs font-bold rounded-lg hover:bg-red-100 transition-colors">
                                        Rechazar
                                    </button>
                                </form>
                            @endif
                        </div>
                    </template>
                </div>
            </div>
        @endforeach
    </div>
</div>
