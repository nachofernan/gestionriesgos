{{--
    Fila de Actualizaciones (cambios propuestos) pendientes de validar o aprobar.
    Variables esperadas: $items (Actualizacion con actualizable/user cargados), $accion.
--}}
@php
    $labelTipo = fn($actualizacion) => match(get_class($actualizacion->actualizable)) {
        \App\Models\Auditoria\Riesgo::class     => 'Riesgo',
        \App\Models\Auditoria\Control::class    => 'Control',
        \App\Models\Auditoria\Objetivo::class   => 'Objetivo',
        \App\Models\Auditoria\PlanAccion::class => 'Plan de Acción',
        \App\Models\Auditoria\Tarea::class      => 'Tarea',
        default                                  => 'Elemento',
    };
@endphp
<div>
    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Cambios propuestos ({{ $items->count() }})</h3>
    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100 overflow-hidden">
        @foreach($items as $actualizacion)
            <div class="px-4 py-3 flex items-center justify-between gap-4"
                 x-data="{ procesado: false }"
                 x-on:actualizacion-procesada.window="if ($event.detail.id === {{ $actualizacion->id }}) procesado = true">

                <div class="min-w-0 flex-1" :class="procesado ? 'opacity-40' : ''">
                    <p class="text-sm font-semibold text-gray-800 truncate">
                        <span class="text-[10px] font-bold text-gray-400 uppercase mr-1.5">{{ $labelTipo($actualizacion) }}</span>
                        {{ $actualizacion->actualizable?->nombre ?? '(elemento eliminado)' }}
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $actualizacion->actualizable?->area?->nombre ?? 'Sin área' }}
                        @if($actualizacion->user)
                            &middot; {{ $actualizacion->user->name }}
                        @endif
                        &middot; {{ $actualizacion->created_at->diffForHumans() }}
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $actualizacion->mensaje }}</p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <span x-show="procesado" x-cloak class="text-xs font-bold text-green-600">Hecho ✓</span>

                    <template x-if="!procesado">
                        <div class="flex items-center gap-2">
                            @can($accion, $actualizacion)
                                <button type="button"
                                        onclick="Livewire.dispatch('abrir-accion-actualizacion', { actualizacionId: {{ $actualizacion->id }}, accion: '{{ $accion }}' })"
                                        class="px-3 py-1 {{ $accion === 'validar' ? 'bg-blue-50 text-blue-700 hover:bg-blue-100' : 'bg-green-50 text-green-700 hover:bg-green-100' }} text-xs font-bold rounded-lg transition-colors">
                                    {{ ucfirst($accion) }}
                                </button>
                            @endcan
                            @can('rechazar', $actualizacion)
                                <button type="button"
                                        onclick="Livewire.dispatch('abrir-accion-actualizacion', { actualizacionId: {{ $actualizacion->id }}, accion: 'rechazar' })"
                                        class="px-3 py-1 bg-red-50 text-red-700 text-xs font-bold rounded-lg hover:bg-red-100 transition-colors">
                                    Rechazar
                                </button>
                            @endcan
                        </div>
                    </template>
                </div>
            </div>
        @endforeach
    </div>
</div>
