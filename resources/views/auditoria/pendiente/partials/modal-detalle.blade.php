{{--
    Modal de vista rápida (solo lectura) para las filas de entidades en
    Pendientes. Alpine puro, sin ida y vuelta a Livewire: cada fila embebe sus
    propios datos vía @js() y dispara 'abrir-detalle-pendiente'; este modal sólo
    escucha y pinta. Las acciones (Validar/Aprobar/Rechazar) siguen siendo los
    botones de la fila, no se tocan.
--}}
<div x-data="{ abierto: false, item: {} }"
     x-on:abrir-detalle-pendiente.window="item = $event.detail; abierto = true"
     x-on:keydown.escape.window="abierto = false">

    <div x-show="abierto" x-cloak
         class="fixed inset-0 z-40 bg-gray-500 bg-opacity-75 transition-opacity"
         x-on:click="abierto = false"></div>

    <div x-show="abierto" x-cloak
         class="fixed inset-0 z-50 flex items-start justify-center px-4 pt-16 pb-8 overflow-y-auto">
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg" x-on:click.stop>

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-0.5" x-text="item.tipo"></p>
                    <h3 class="text-base font-extrabold text-gray-900 leading-tight" x-text="item.nombre"></h3>
                </div>
                <button type="button" x-on:click="abierto = false"
                        class="text-gray-400 hover:text-gray-600 transition-colors p-1 shrink-0">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            <div class="px-6 py-3 border-b border-gray-100 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <span class="inline-flex items-center gap-1.5 font-semibold capitalize"
                      :class="{
                          'text-blue-700':   item.estado_color === 'blue',
                          'text-green-700':  item.estado_color === 'green',
                          'text-red-700':    item.estado_color === 'red',
                          'text-purple-700': item.estado_color === 'purple',
                          'text-gray-600':   !['blue','green','red','purple'].includes(item.estado_color),
                      }">
                    <span class="w-2.5 h-2.5 rounded-full"
                          :class="{
                              'bg-blue-500':   item.estado_color === 'blue',
                              'bg-green-500':  item.estado_color === 'green',
                              'bg-red-500':    item.estado_color === 'red',
                              'bg-purple-500': item.estado_color === 'purple',
                              'bg-gray-400':   !['blue','green','red','purple'].includes(item.estado_color),
                          }"></span>
                    <span x-text="item.estado_nombre"></span>
                </span>
                <span class="text-gray-300">&middot;</span>
                <span class="text-gray-600" x-text="item.area"></span>
                <template x-if="item.propietario">
                    <span class="text-gray-500">
                        <span class="text-gray-300">&middot;</span>
                        <span x-text="item.propietario"></span>
                    </span>
                </template>
            </div>

            <div class="px-6 py-4">
                <p class="text-sm text-gray-600 whitespace-pre-line" x-text="item.descripcion || 'Sin descripción.'"></p>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                <a :href="item.url" class="text-xs font-bold text-indigo-600 hover:text-indigo-700">
                    Ver ficha completa &rarr;
                </a>
                <button type="button" x-on:click="abierto = false"
                        class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">
                    Cerrar
                </button>
            </div>

        </div>
    </div>
</div>
