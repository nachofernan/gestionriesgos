<div class="space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Controles</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $controles->count() }} control(es) registrado(s)</p>
        </div>
        <a href="{{ route('auditoria.controles.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 shadow-sm transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nuevo Control
        </a>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Búsqueda -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-2">Buscar</label>
                <input type="text" wire:model.live="search" placeholder="Nombre del control..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <!-- Estado -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-2">Estado</label>
                <select wire:model.live="filtroEstado"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($estados as $estado)
                        <option value="{{ $estado->id }}">{{ $estado->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Área -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-2">Área</label>
                <select wire:model.live="filtroArea"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todas</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}">{{ $area->nombre }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-1.5 mt-2 cursor-pointer">
                    <input type="checkbox" wire:model.live="mostrarHijos"
                           class="w-3.5 h-3.5 border-gray-300 rounded focus:ring-2 focus:ring-indigo-500">
                    <span class="text-xs text-gray-500">Incluir sub-áreas</span>
                </label>
            </div>
        </div>

        <div class="flex justify-end pt-2 border-t border-gray-200">
            <button wire:click="limpiarFiltros"
                   class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                Limpiar filtros
            </button>
        </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 font-semibold uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-6 py-4 text-left cursor-pointer hover:bg-gray-100 transition-colors" wire:click="ordenar('nombre')">
                            <div class="flex items-center gap-1">
                                Nombre
                                @if($ordenarPor === 'nombre')
                                    <span class="text-indigo-600">{{ $direccion === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-4 text-left">Estado</th>
                        <th class="px-6 py-4 text-left">Descripción</th>
                        <th class="px-4 py-4 text-center cursor-pointer hover:bg-gray-100 transition-colors" wire:click="ordenar('mitigacion_default')">
                            <div class="flex items-center justify-center gap-1">
                                Mitigación
                                @if($ordenarPor === 'mitigacion_default')
                                    <span class="text-indigo-600">{{ $direccion === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-4 text-left">Riesgos</th>
                        <th class="px-6 py-4 text-left">Usuario / Área</th>
                        <th class="px-6 py-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($controles as $control)
                        <tr class="hover:bg-indigo-50/30 transition-colors">
                            <td class="px-6 py-4 font-semibold text-gray-900">
                                <a href="{{ route('auditoria.controles.show', $control) }}"
                                   class="hover:text-indigo-600 transition-colors">
                                    {{ $control->nombre }}
                                </a>
                            </td>
                            <td class="px-6 py-4">
                                <x-auditoria.estado-badge :estado="$control->estado" />
                            </td>
                            <td class="px-6 py-4 text-gray-600 text-xs">
                                {{ Str::limit($control->descripcion, 50) ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                    {{ $control->mitigacion_default }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-600">
                                @forelse($control->riesgos as $riesgo)
                                    {{ $riesgo->nombre }}@if(!$loop->last)<br>@endif
                                @empty
                                    <span class="text-gray-400">—</span>
                                @endforelse
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <div class="space-y-1">
                                    <div class="text-gray-600">{{ $control->user->name ?? '—' }}</div>
                                    <div class="text-gray-500">{{ $control->area->nombre ?? '—' }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('auditoria.controles.show', $control) }}"
                                   class="text-indigo-600 hover:text-indigo-900 font-medium text-xs bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition-colors">
                                    Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400 italic">
                                No hay controles registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($controles->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $controles->links() }}
            </div>
        @endif
    </div>

</div>
