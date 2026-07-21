<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <h2 class="text-sm font-bold text-gray-700">Historial de Actualizaciones</h2>
        @if($estadoModelo !== 'borrador')
            <button wire:click="abrirModal"
                    class="px-3 py-1.5 bg-indigo-50 text-indigo-700 text-xs font-semibold rounded-lg hover:bg-indigo-100 transition-colors">
                Nueva Actualización
            </button>
        @endif
    </div>

    {{-- Lista --}}
    <div class="divide-y divide-gray-100">
        @forelse ($actualizaciones as $actualizacion)
            @php
                $data  = $actualizacion->data ?? [];
                $tipo  = $data['tipo'] ?? 'legacy';
                $skipData = in_array($tipo, ['validacion', 'activacion', 'rechazo']);
                $tieneContenido = !empty($data['campos']) || !empty($data['relaciones']) || !empty($data['diff'])
                    || ($tipo === 'legacy' && collect($data)->except(['tipo', 'activated_by'])->filter(fn($v) => !is_array($v))->isNotEmpty());
                $estaAprobado = $actualizacion->estado?->nombre === 'aprobado';
                $activadoPor = $data['activated_by'] ?? null;

                if ($estaAprobado) {
                    $dataBg     = 'bg-green-50';
                    $dataBorder = 'border-green-200';
                    $dataTxt    = 'text-green-800';
                    $dataLabel  = 'Cambios realizados';
                } elseif (in_array($tipo, ['creacion', 'edicion'])) {
                    $dataBg     = 'bg-gray-50';
                    $dataBorder = 'border-gray-200';
                    $dataTxt    = 'text-gray-700';
                    $dataLabel  = $tipo === 'creacion' ? 'Datos de creación' : 'Modificaciones';
                } else {
                    $dataBg     = 'bg-amber-50';
                    $dataBorder = 'border-amber-100';
                    $dataTxt    = 'text-amber-800';
                    $dataLabel  = 'Cambios propuestos';
                }
            @endphp
            <div class="px-5 py-4">
                <div class="flex items-start justify-between gap-4 mb-2">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <x-auditoria.estado-badge :estado="$actualizacion->estado" size="sm" />
                            @if($actualizacion->user)
                                <span class="text-xs text-gray-400">{{ $actualizacion->user->name }}</span>
                            @endif
                            <span class="text-xs text-gray-400">
                                {{ \Carbon\Carbon::parse($actualizacion->created_at)->format('d/m/Y H:i') }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-700">{{ $actualizacion->mensaje }}</p>

                        {{-- Doble validación: gerencias que todavía no votaron a favor --}}
                        @if($actualizacion->estado?->nombre === 'borrador' && $actualizacion->requiereDobleValidacion())
                            @php $gerenciasPendientes = $actualizacion->gerenciasPendientes(); @endphp
                            @if($gerenciasPendientes->isNotEmpty())
                                <p class="mt-1 text-[11px] font-semibold text-amber-600">
                                    Pendiente de validación de: {{ $gerenciasPendientes->join(', ') }}
                                </p>
                            @endif
                        @endif

                        {{-- Detalle de cambios --}}
                        @if($tieneContenido && !$skipData)
                            <div class="mt-2 p-2 {{ $dataBg }} border {{ $dataBorder }} rounded-lg">
                                <div class="flex items-center justify-between mb-1.5">
                                    <p class="text-[10px] font-bold {{ $dataTxt }} uppercase">{{ $dataLabel }}</p>
                                    @if($activadoPor)
                                        <span class="text-[10px] {{ $dataTxt }} opacity-70">Aprobado por: {{ $activadoPor }}</span>
                                    @endif
                                </div>

                                {{-- Diff de campos (antes → despues) --}}
                                @if(isset($data['diff']['campos']))
                                    @foreach($data['diff']['campos'] as $campo => $cambio)
                                        <div class="text-xs {{ $dataTxt }} leading-5">
                                            <span class="font-medium">{{ $campo }}:</span>
                                            <span class="line-through opacity-50 ml-1">{{ is_bool($cambio['antes']) ? ($cambio['antes'] ? 'sí' : 'no') : ($cambio['antes'] ?? '—') }}</span>
                                            <span class="mx-1 opacity-40">→</span>
                                            <span class="font-semibold">{{ is_bool($cambio['despues']) ? ($cambio['despues'] ? 'sí' : 'no') : ($cambio['despues'] ?? '—') }}</span>
                                        </div>
                                    @endforeach
                                @endif

                                {{-- Diff de relaciones (agrega / quita / cambia) --}}
                                @if(isset($data['diff']['relaciones']))
                                    @foreach($data['diff']['relaciones'] as $rel => $cambio)
                                        <p class="text-[10px] font-semibold {{ $dataTxt }} capitalize mt-1.5 mb-0.5">{{ $rel }}</p>
                                        @foreach($cambio['agrega'] ?? [] as $item)
                                            <div class="text-xs text-green-700 pl-2 leading-5">
                                                + {{ $item['nombre'] ?? '—' }}
                                                @if(isset($item['mitigacion']))
                                                    <span class="opacity-60">(mit. {{ $item['mitigacion'] }})</span>
                                                @endif
                                            </div>
                                        @endforeach
                                        @foreach($cambio['quita'] ?? [] as $item)
                                            <div class="text-xs text-red-600 pl-2 leading-5">− {{ $item['nombre'] ?? '—' }}</div>
                                        @endforeach
                                        @foreach($cambio['cambia'] ?? [] as $item)
                                            <div class="text-xs text-amber-700 pl-2 leading-5">
                                                ~ {{ $item['nombre'] ?? '—' }}
                                                <span class="opacity-60">(mit. {{ $item['mitigacion_antes'] }} → {{ $item['mitigacion_despues'] }})</span>
                                            </div>
                                        @endforeach
                                    @endforeach
                                @endif

                                {{-- Snapshot de campos (sin diff: creación o propuesta sin antes/despues) --}}
                                @if(!isset($data['diff']) && isset($data['campos']))
                                    @foreach($data['campos'] as $campo => $valor)
                                        <div class="text-xs {{ $dataTxt }} leading-5">
                                            <span class="font-medium">{{ $campo }}:</span>
                                            {{ is_bool($valor) ? ($valor ? 'sí' : 'no') : $valor }}
                                        </div>
                                    @endforeach
                                @endif

                                {{-- Relaciones sin diff (fallback) --}}
                                @if(!isset($data['diff']) && isset($data['relaciones']))
                                    @foreach($data['relaciones'] as $rel => $ops)
                                        <div class="text-xs {{ $dataTxt }} leading-5">
                                            <span class="font-medium capitalize">{{ $rel }}:</span>
                                            @if(isset($ops['sync'])) reemplazar con {{ count($ops['sync']) }} elemento(s) @endif
                                            @if(isset($ops['attach'])) agregar {{ count($ops['attach']) }} @endif
                                            @if(isset($ops['detach'])) quitar {{ count($ops['detach']) }} @endif
                                        </div>
                                    @endforeach
                                @endif

                                {{-- Legacy: formato plano sin tipo --}}
                                @if($tipo === 'legacy' && !isset($data['campos']) && !isset($data['relaciones']) && !isset($data['diff']))
                                    @foreach(collect($data)->except(['tipo', 'activated_by']) as $campo => $valor)
                                        @if(!is_array($valor))
                                            <div class="text-xs {{ $dataTxt }} leading-5">
                                                <span class="font-medium">{{ $campo }}:</span> {{ $valor }}
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        @endif

                        {{-- Adjuntos --}}
                        @if($actualizacion->getMedia('adjuntos')->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($actualizacion->getMedia('adjuntos') as $media)
                                    <a href="{{ route('auditoria.actualizaciones.adjuntos.download', [$actualizacion, $media]) }}"
                                       class="inline-flex items-center gap-1 px-2 py-1 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-100 transition-colors">
                                        <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                        {{ $media->file_name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Acciones de transición --}}
                    <div class="flex items-center gap-1 shrink-0">
                        {{-- Validar: solo cuando el elemento ya salió del borrador --}}
                        @if($estadoModelo !== 'borrador')
                            @can('validar', $actualizacion)
                                <button wire:click="validarActualizacion({{ $actualizacion->id }})"
                                        wire:confirm="¿Validar esta actualización?"
                                        class="px-2.5 py-1 bg-blue-50 text-blue-700 text-[11px] font-bold rounded-lg hover:bg-blue-100 transition-colors">
                                    Validar
                                </button>
                            @endcan
                        @endif

                        {{-- Aprobar individualmente: solo en elemento aprobado (en validado, la aprobación es del elemento completo) --}}
                        @if($estadoModelo === 'aprobado')
                            @can('aprobar', $actualizacion)
                                <button wire:click="aprobarActualizacion({{ $actualizacion->id }})"
                                        wire:confirm="¿Aprobar esta actualización? Los cambios propuestos se aplicarán al registro."
                                        class="px-2.5 py-1 bg-green-50 text-green-700 text-[11px] font-bold rounded-lg hover:bg-green-100 transition-colors">
                                    Aprobar
                                </button>
                            @endcan
                        @endif

                        {{-- Rechazar: solo cuando el elemento ya salió del borrador --}}
                        @if($estadoModelo !== 'borrador')
                            @can('rechazar', $actualizacion)
                                <button wire:click="rechazarActualizacion({{ $actualizacion->id }})"
                                        wire:confirm="¿Rechazar esta actualización?"
                                        class="px-2.5 py-1 bg-red-50 text-red-700 text-[11px] font-bold rounded-lg hover:bg-red-100 transition-colors">
                                    Rechazar
                                </button>
                            @endcan
                        @endif

                        {{-- Cancelar: el propio creador puede retirar su propuesta mientras esté en borrador --}}
                        @can('cancelar', $actualizacion)
                            <button wire:click="cancelarActualizacion({{ $actualizacion->id }})"
                                    wire:confirm="¿Cancelar esta propuesta?"
                                    class="px-2.5 py-1 bg-gray-100 text-gray-600 text-[11px] font-bold rounded-lg hover:bg-gray-200 transition-colors">
                                Cancelar
                            </button>
                        @endcan
                    </div>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-gray-400 italic text-sm">
                Sin actualizaciones todavía.
            </div>
        @endforelse
    </div>

    {{-- Modal nueva actualización --}}
    @if($modalAbierto)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-lg max-w-lg w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">Nueva Actualización</h3>
                <button wire:click="cerrarModal" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mensaje <span class="text-red-500">*</span></label>
                    <textarea wire:model="mensaje" rows="3" required
                              placeholder="Describe el cambio o actualización..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    @error('mensaje') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                @if(!empty($camposEditables))
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-3">Cambios propuestos (opcional)</p>
                        <div class="space-y-3">
                            @foreach($camposEditables as $campo => $etiqueta)
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ $etiqueta }}</label>
                                    <input type="text" wire:model="cambios.{{ $campo }}"
                                           placeholder="Nuevo valor (dejar vacío para no cambiar)"
                                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-gray-50">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Adjuntos --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Adjuntos (opcional)</label>
                    <input type="file" wire:model="archivos" multiple
                           class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-xs text-gray-400 mt-1">PDF, Word, Excel o imágenes. Hasta 10&nbsp;MB por archivo.</p>
                    <div wire:loading wire:target="archivos" class="text-xs text-indigo-600 mt-1">Subiendo archivos…</div>
                    @error('archivos.*') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3 justify-end">
                <button wire:click="cerrarModal"
                        class="px-4 py-2 text-gray-700 text-sm font-medium hover:bg-gray-100 rounded-lg transition-colors">
                    Cancelar
                </button>
                <button wire:click="guardar"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    Guardar
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
