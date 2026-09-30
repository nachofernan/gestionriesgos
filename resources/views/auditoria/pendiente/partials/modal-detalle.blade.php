{{--
    Modal de vista rápida de Pendientes, para entidades (modo 'entidad') y
    propuestas de cambio (modo 'propuesta'). Alpine puro, sin ida y vuelta a
    Livewire: cada fila embebe el payload que arma PendienteController::detalles()
    vía @js() y dispara 'abrir-detalle-pendiente'; este modal sólo escucha y pinta.
    Los botones del pie no mutan nada por su cuenta: disparan los mismos flujos que
    la fila (modal de cascada, modal de acción de la actualización, form de
    rechazar), que autorizan al mutar. Los flags puede_* vienen del controlador.
--}}
<div x-data="{ abierto: false, item: {}, procesados: [] }"
     x-on:abrir-detalle-pendiente.window="item = $event.detail; abierto = true"
     x-on:cascada-procesada.window="procesados.push($event.detail.tipo + '-' + $event.detail.id)"
     x-on:actualizacion-procesada.window="procesados.push('actualizacion-' + $event.detail.id)"
     x-on:keydown.escape.window="abierto = false">

    <div x-show="abierto" x-cloak
         class="fixed inset-0 z-40 bg-gray-500 bg-opacity-75 transition-opacity"
         x-on:click="abierto = false"></div>

    <div x-show="abierto" x-cloak
         class="fixed inset-0 z-50 flex items-start justify-center px-4 pt-16 pb-8 overflow-y-auto">
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg" x-on:click.stop>

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-0.5"
                       x-text="item.modo === 'propuesta' ? 'Propuesta de cambio · ' + item.tipo_label : item.tipo_label"></p>
                    <h3 class="text-base font-extrabold text-gray-900 leading-tight">
                        <span x-text="item.nombre"></span>
                    </h3>
                </div>
                <button type="button" x-on:click="abierto = false"
                        class="text-gray-400 hover:text-gray-600 transition-colors p-1 shrink-0">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            {{-- Metadatos --}}
            <div class="px-6 py-3 border-b border-gray-100 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <template x-if="item.modo === 'entidad'">
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
                </template>
                <template x-if="item.modo === 'propuesta'">
                    <span class="font-semibold"
                          :class="item.situacion === 'Espera al comité' ? 'text-blue-600' : 'text-amber-700'"
                          x-text="item.situacion"></span>
                </template>
                <span class="text-gray-300">&middot;</span>
                <span class="text-gray-600" x-text="item.area"></span>
                <template x-if="item.propietario">
                    <span class="text-gray-500">
                        <span class="text-gray-300">&middot;</span>
                        <span x-text="item.propietario"></span>
                    </span>
                </template>
                <template x-if="item.fecha">
                    <span class="text-gray-400" :title="item.fecha_completa">
                        <span class="text-gray-300">&middot;</span>
                        <span x-text="item.fecha"></span>
                    </span>
                </template>
            </div>

            {{-- Cuerpo: entidad --}}
            <template x-if="item.modo === 'entidad'">
                <div class="px-6 py-4 space-y-4">
                    <p class="text-sm text-gray-600 whitespace-pre-line" x-text="item.descripcion || 'Sin descripción.'"></p>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2" x-show="(item.datos || []).length">
                        <template x-for="fila in item.datos" :key="fila.etiqueta">
                            <div class="min-w-0">
                                <dt class="text-[11px] font-bold text-gray-400 uppercase tracking-wide" x-text="fila.etiqueta"></dt>
                                <dd class="text-sm font-semibold"
                                    :class="{
                                        'text-green-700': fila.color === 'green',
                                        'text-amber-700': fila.color === 'amber',
                                        'text-red-700':   fila.color === 'red',
                                        'text-gray-800':  !fila.color,
                                    }"
                                    x-text="fila.valor"></dd>
                            </div>
                        </template>
                    </dl>
                </div>
            </template>

            {{-- Cuerpo: propuesta de cambio --}}
            <template x-if="item.modo === 'propuesta'">
                <div class="px-6 py-4 space-y-3">
                    <template x-if="item.votos">
                        <p class="text-xs text-gray-500 bg-amber-50 rounded-lg px-3 py-2">
                            <span class="font-bold text-amber-800">Riesgo compartido:</span>
                            a favor <span class="font-semibold" x-text="item.votos.a_favor.join(', ') || '—'"></span>
                            · faltan <span class="font-semibold" x-text="item.votos.faltan.join(', ') || '—'"></span>
                        </p>
                    </template>

                    <dl class="space-y-1.5" x-show="item.diff.campos.length">
                        <template x-for="fila in item.diff.campos" :key="fila.campo">
                            <div class="text-sm leading-5 grid grid-cols-[7.5rem_1fr] gap-2">
                                <dt class="font-semibold text-gray-500" x-text="fila.etiqueta"></dt>
                                <dd class="min-w-0 break-words">
                                    <span class="line-through text-gray-400 decoration-gray-300" x-text="fila.antes"></span>
                                    <span class="mx-1 text-amber-500">→</span>
                                    <span class="font-semibold text-gray-900" x-text="fila.despues"></span>
                                </dd>
                            </div>
                        </template>
                    </dl>

                    <template x-for="(rel, parte) in item.diff.relaciones" :key="parte">
                        <div class="text-sm grid grid-cols-[7.5rem_1fr] gap-2">
                            <p class="font-semibold text-gray-500" x-text="rel.etiqueta"></p>
                            <div class="space-y-1 min-w-0">
                                <template x-for="(el, i) in rel.items" :key="i">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-5 h-5 shrink-0 inline-flex items-center justify-center rounded-md text-xs font-extrabold"
                                              :class="{
                                                  'bg-emerald-50 text-emerald-700': el.op === 'agrega',
                                                  'bg-rose-50 text-rose-700':       el.op === 'quita',
                                                  'bg-amber-50 text-amber-700':     el.op === 'cambia',
                                              }"
                                              x-text="{ agrega: '+', quita: '−', cambia: '~' }[el.op]"></span>
                                        <span class="truncate"
                                              :class="el.op === 'quita' ? 'text-gray-500 line-through decoration-rose-300' : 'text-gray-800 font-medium'"
                                              x-text="el.nombre"></span>
                                        <span x-show="el.detalle" class="shrink-0 text-[11px] font-bold text-gray-500" x-text="el.detalle"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="!item.diff.campos.length && !Object.keys(item.diff.relaciones).length">
                        <p class="text-sm text-gray-400 italic">La propuesta no trae un detalle de cambios.</p>
                    </template>

                    <template x-if="item.propagar_mitigacion">
                        <p class="inline-block text-[10px] font-bold text-indigo-700 bg-indigo-50 rounded-full px-2 py-0.5">Se aplica también a los riesgos asociados</p>
                    </template>

                    <template x-if="item.mensaje">
                        <p class="text-sm text-gray-500 italic whitespace-pre-line" x-text="'“' + item.mensaje + '”'"></p>
                    </template>
                </div>
            </template>

            {{-- Pie: ir al elemento + acciones --}}
            <div class="px-6 py-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <a x-show="item.puede_ver && item.url" :href="item.url" class="text-xs font-bold text-indigo-600 hover:text-indigo-700"
                       x-text="item.modo === 'propuesta' ? 'Ver ' + (item.tipo_label || '').toLowerCase() + ' →' : 'Ver ficha completa →'"></a>
                    <span x-show="!item.puede_ver" class="text-xs text-gray-400 italic">No tenés acceso a la ficha en este estado</span>
                </div>

                <div class="flex items-center gap-2">
                    <span x-show="procesados.includes(item.clave)" class="text-xs font-bold text-green-600">Hecho ✓</span>

                    <template x-if="!procesados.includes(item.clave)">
                        <div class="flex items-center gap-2">
                            <template x-if="item.puede_accion">
                                <button type="button"
                                        x-on:click="abierto = false; item.modo === 'propuesta'
                                            ? Livewire.dispatch('abrir-accion-actualizacion', { actualizacionId: item.id, accion: item.accion })
                                            : Livewire.dispatch('abrir-validacion-cascada', { tipo: item.tipo, id: item.id, accion: item.accion, sinRedireccion: true })"
                                        class="px-3 py-1.5 text-xs font-bold rounded-lg transition-colors"
                                        :class="item.accion === 'validar' ? 'bg-blue-50 text-blue-700 hover:bg-blue-100' : 'bg-green-50 text-green-700 hover:bg-green-100'"
                                        x-text="item.accion === 'validar' ? 'Validar' : 'Aprobar'"></button>
                            </template>

                            <template x-if="item.puede_rechazar && item.modo === 'propuesta'">
                                <button type="button"
                                        x-on:click="abierto = false; Livewire.dispatch('abrir-accion-actualizacion', { actualizacionId: item.id, accion: 'rechazar' })"
                                        class="px-3 py-1.5 bg-red-50 text-red-700 text-xs font-bold rounded-lg hover:bg-red-100 transition-colors">
                                    Rechazar
                                </button>
                            </template>

                            <template x-if="item.puede_rechazar && item.modo === 'entidad'">
                                <form :action="item.url_rechazar" method="POST" onsubmit="return confirm('¿Rechazar este elemento?')">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-700 text-xs font-bold rounded-lg hover:bg-red-100 transition-colors">
                                        Rechazar
                                    </button>
                                </form>
                            </template>
                        </div>
                    </template>

                    <button type="button" x-on:click="abierto = false"
                            class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">
                        Cerrar
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
