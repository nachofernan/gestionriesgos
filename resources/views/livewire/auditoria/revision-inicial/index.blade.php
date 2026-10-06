<div class="space-y-6"
     x-data="{
        abierto: false,
        accion: null,
        tipo: null, id: null, gerenciaId: null,
        nombre: '', gerencia: '', advertencias: [], enCurso: [],
        comentario: '',
        abrir(datos) {
            Object.assign(this, datos, { comentario: '', abierto: true });
        },
        confirmar() {
            this.accion === 'firmar'
                ? $wire.firmar(this.tipo, this.id, this.gerenciaId, this.comentario)
                : $wire.marcarRevisado(this.tipo, this.id, this.gerenciaId, this.comentario);
        },
     }"
     x-on:revision-registrada.window="abierto = false">

    <!-- Header -->
    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Revisión de la carga inicial</h1>
        <p class="text-sm text-gray-500 mt-1 max-w-3xl">
            Lo que vino del Excel y del Redmine, elemento por elemento. Los <span class="font-semibold text-red-700">errores</span>
            hay que arreglarlos en la pantalla del elemento antes de firmar (alcanza con que el arreglo esté
            <span class="font-semibold text-sky-700">validado</span>, aunque espere al comité); las <span class="font-semibold text-amber-700">advertencias</span>
            se pueden firmar con un comentario. Revisa el responsable o el coordinador; firma el gerente.
        </p>
    </div>

    @if ($mensaje)
        <div class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm font-medium">{{ $mensaje }}</div>
    @endif
    @error('revision')
        <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm font-medium">{{ $message }}</div>
    @enderror

    <!-- Avance por gerencia -->
    @if ($resumen->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 font-semibold uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-6 py-3 text-left">Gerencia</th>
                        <th class="px-6 py-3 text-left w-1/3">Avance</th>
                        <th class="px-4 py-3 text-right">Firmados</th>
                        <th class="px-4 py-3 text-right">Revisados</th>
                        <th class="px-4 py-3 text-right">Pendientes</th>
                        <th class="px-4 py-3 text-right">Con errores</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($resumen as $r)
                        @php
                            $porcentaje = $r['total'] ? round($r['firmados'] * 100 / $r['total']) : 0;
                        @endphp
                        <tr>
                            <td class="px-6 py-3 font-semibold text-gray-800">{{ $r['gerencia']->nombre }}</td>
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                        <div class="h-2 bg-green-500" style="width: {{ $porcentaje }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 w-10 text-right">{{ $porcentaje }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right text-green-700 font-semibold">{{ $r['firmados'] }}</td>
                            <td class="px-4 py-3 text-right text-indigo-700">{{ $r['revisados'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $r['pendientes'] }}</td>
                            <td class="px-4 py-3 text-right {{ $r['con_errores'] ? 'text-red-700 font-semibold' : 'text-gray-400' }}">{{ $r['con_errores'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Pestañas por tipo -->
    <div class="flex flex-wrap gap-2">
        @foreach ($tipos as $clave => $cfg)
            <button type="button" wire:click="$set('tipo', '{{ $clave }}')"
                    class="px-3 py-2 rounded-lg text-sm font-semibold transition-colors
                        {{ $tipo === $clave ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700' }}">
                {{ $cfg['label'] }} <span class="text-xs font-normal text-gray-400">{{ $conteoPorTipo[$clave] }}</span>
            </button>
        @endforeach
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-2">Buscar</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Nombre..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-2">Revisión</label>
                <select wire:model.live="filtroRevision"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todas</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="revisado">Revisado, sin firmar</option>
                    <option value="firmado">Firmado</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-2">Inconsistencias</label>
                <select wire:model.live="filtroHallazgos"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todas</option>
                    <option value="errores">Con errores</option>
                    <option value="advertencias">Sólo advertencias</option>
                    <option value="limpios">Sin inconsistencias</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-2">Gerencia</label>
                <select wire:model.live="filtroGerencia"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todas</option>
                    @foreach ($resumen as $r)
                        <option value="{{ $r['gerencia']->id }}">{{ $r['gerencia']->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Listado -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 font-semibold uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-6 py-3 text-left">Nombre</th>
                        <th class="px-4 py-3 text-left">Área / Responsable</th>
                        <th class="px-4 py-3 text-left">Inconsistencias</th>
                        <th class="px-4 py-3 text-left">Revisión por gerencia</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($filas as $fila)
                        @php
                            $elemento = $fila['elemento'];
                            $conErrores = collect($fila['hallazgos'])->contains('severidad', 'error');
                            $advertencias = collect($fila['hallazgos'])->where('severidad', 'advertencia')->pluck('mensaje')->values();
                            $enCurso = collect($fila['hallazgos'])->where('severidad', 'en_curso')->pluck('mensaje')->values();
                        @endphp
                        <tr wire:key="fila-{{ $tipo }}-{{ $elemento->id }}" class="align-top">
                            <td class="px-6 py-3">
                                <a href="{{ route($tipos[$tipo]['ruta'], $elemento) }}"
                                   class="font-semibold text-indigo-700 hover:underline">{{ $elemento->nombre }}</a>
                                <div class="text-xs text-gray-400 mt-0.5">{{ $elemento->estado?->nombre }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <div>{{ $elemento->area?->nombre ?? '—' }}</div>
                                <div class="text-xs text-gray-400">{{ $elemento->user?->name ?? 'Sin responsable' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($fila['hallazgos'] as $h)
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                            {{ match ($h['severidad']) {
                                                'error' => 'bg-red-50 text-red-700 border border-red-200',
                                                'en_curso' => 'bg-sky-50 text-sky-800 border border-sky-200',
                                                default => 'bg-amber-50 text-amber-800 border border-amber-200',
                                            } }}">
                                            {{ $h['mensaje'] }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-green-700">Sin inconsistencias</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="space-y-2">
                                    @forelse ($fila['gerencias'] as ['gerencia' => $g, 'revision' => $r])
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($fila['gerencias']->count() > 1)
                                                <span class="text-xs font-semibold text-gray-500">{{ $g->nombre }}:</span>
                                            @endif

                                            @if ($r?->firmado_en)
                                                <span class="text-xs text-green-700 font-semibold">✓ Firmado</span>
                                                <span class="text-xs text-gray-400">{{ $r->firmadoPor?->name }} · {{ $r->firmado_en->format('d/m/Y') }}</span>
                                            @else
                                                @if ($r?->revisado_en)
                                                    <span class="text-xs text-indigo-700">Revisado por {{ $r->revisadoPor?->name }} · {{ $r->revisado_en->format('d/m/Y') }}</span>
                                                @else
                                                    <span class="text-xs text-gray-400">Pendiente</span>
                                                @endif

                                                @unless ($soloLectura)
                                                    @if (! $r?->revisado_en)
                                                        @can('revisar', [App\Models\Auditoria\RevisionInicial::class, $elemento, $g])
                                                            <button type="button"
                                                                    x-on:click="abrir(@js(['accion' => 'revisar', 'tipo' => $tipo, 'id' => $elemento->id, 'gerenciaId' => $g->id, 'nombre' => $elemento->nombre, 'gerencia' => $g->nombre, 'advertencias' => [], 'enCurso' => []]))"
                                                                    class="px-2 py-1 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-md">
                                                                Marcar revisado
                                                            </button>
                                                        @endcan
                                                    @endif
                                                    @can('firmar', [App\Models\Auditoria\RevisionInicial::class, $elemento, $g])
                                                        @if ($conErrores)
                                                            <span class="text-xs text-red-600" title="Hay que arreglar los errores antes de firmar">Firma bloqueada por errores</span>
                                                        @else
                                                            <button type="button"
                                                                    x-on:click="abrir(@js(['accion' => 'firmar', 'tipo' => $tipo, 'id' => $elemento->id, 'gerenciaId' => $g->id, 'nombre' => $elemento->nombre, 'gerencia' => $g->nombre, 'advertencias' => $advertencias, 'enCurso' => $enCurso]))"
                                                                    class="px-2 py-1 text-xs font-semibold text-white bg-green-600 hover:bg-green-700 rounded-md">
                                                                ✓ Firmar
                                                            </button>
                                                        @endif
                                                    @endcan
                                                @endunless
                                            @endif
                                        </div>
                                    @empty
                                        <span class="text-xs text-red-600">Sin gerencia que lo firme</span>
                                    @endforelse
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-gray-400">No hay elementos con estos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 border-t border-gray-100">
            {{ $filas->links() }}
        </div>
    </div>

    <!-- Modal de revisar / firmar -->
    <div x-show="abierto" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         x-on:keydown.escape.window="abierto = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 space-y-4" x-on:click.outside="abierto = false">
            <div>
                <h2 class="text-lg font-bold text-gray-900" x-text="accion === 'firmar' ? 'Firmar' : 'Marcar revisado'"></h2>
                <p class="text-sm text-gray-500">
                    <span class="font-semibold text-gray-700" x-text="nombre"></span>
                    · <span x-text="gerencia"></span>
                </p>
            </div>

            <template x-if="accion === 'firmar' && enCurso.length">
                <div class="rounded-lg bg-sky-50 border border-sky-200 p-3 text-sm text-sky-900">
                    <p class="font-semibold mb-1">Firmás con arreglos validados que todavía esperan al comité:</p>
                    <ul class="list-disc pl-5 space-y-0.5">
                        <template x-for="a in enCurso"><li x-text="a"></li></template>
                    </ul>
                </div>
            </template>

            <template x-if="accion === 'firmar' && advertencias.length">
                <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900">
                    <p class="font-semibold mb-1">Firmás aceptando estas advertencias:</p>
                    <ul class="list-disc pl-5 space-y-0.5">
                        <template x-for="a in advertencias"><li x-text="a"></li></template>
                    </ul>
                </div>
            </template>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">
                    Comentario
                    <span x-show="accion === 'firmar' && advertencias.length" class="text-red-600">(obligatorio)</span>
                    <span x-show="! (accion === 'firmar' && advertencias.length)" class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <textarea x-model="comentario" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                @error('comentario')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" x-on:click="abierto = false"
                        class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg">Cancelar</button>
                <button type="button" x-on:click="confirmar()"
                        :class="accion === 'firmar' ? 'bg-green-600 hover:bg-green-700' : 'bg-indigo-600 hover:bg-indigo-700'"
                        class="px-3 py-1.5 text-sm font-bold text-white rounded-lg"
                        x-text="accion === 'firmar' ? 'Firmar' : 'Marcar revisado'"></button>
            </div>
        </div>
    </div>
</div>
