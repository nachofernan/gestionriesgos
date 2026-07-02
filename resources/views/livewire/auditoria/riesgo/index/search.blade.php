<div class="space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Evaluación de Riesgos</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $riesgos->count() }} riesgo(s) registrado(s)</p>
        </div>
        <a href="{{ route('auditoria.riesgos.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 shadow-sm transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nuevo Riesgo
        </a>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Búsqueda -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-2">Buscar</label>
                <input type="text" wire:model.live="search" placeholder="Nombre..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <!-- Tipo de Riesgo -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-2">Tipo</label>
                <select wire:model.live="filtroTipo"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($tiposRiesgo as $tipo)
                        <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                    @endforeach
                </select>
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

            <!-- Criticidad -->
            <div class="flex items-end">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model.live="soloAlta"
                           class="w-4 h-4 border-gray-300 rounded focus:ring-2 focus:ring-indigo-500">
                    <span class="text-sm font-medium text-gray-700">Solo mayor criticidad</span>
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
                                Nombre / Tipo
                                @if($ordenarPor === 'nombre')
                                    <span class="text-indigo-600">{{ $direccion === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-4 text-left cursor-pointer hover:bg-gray-100 transition-colors" wire:click="ordenar('estado')">
                            <div class="flex items-center gap-1">
                                Estado
                                @if($ordenarPor === 'estado')
                                    <span class="text-indigo-600">{{ $direccion === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-4 text-center">Impacto / Probabilidad</th>
                        <th class="px-4 py-4 text-center cursor-pointer hover:bg-gray-100 transition-colors" wire:click="ordenar('valor_total')">
                            <div class="flex items-center justify-center gap-1">
                                Total
                                @if($ordenarPor === 'valor_total')
                                    <span class="text-indigo-600">{{ $direccion === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-4 text-center cursor-pointer hover:bg-gray-100 transition-colors" wire:click="ordenar('valor_residual')">
                            <div class="flex items-center justify-center gap-1">
                                Residual
                                @if($ordenarPor === 'valor_residual')
                                    <span class="text-indigo-600">{{ $direccion === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-4 text-left">Objetivos</th>
                        <th class="px-6 py-4 text-left">Usuario / Área</th>
                        <th class="px-6 py-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($riesgos as $riesgo)
                        @php
                            $colorMap = [
                                'gray'   => 'bg-gray-100 text-gray-700',
                                'blue'   => 'bg-blue-100 text-blue-700',
                                'green'  => 'bg-green-100 text-green-700',
                                'purple' => 'bg-purple-100 text-purple-700',
                                'red'    => 'bg-red-100 text-red-700',
                            ];
                            $estadoClass = $colorMap[$riesgo->estado->color ?? ''] ?? 'bg-gray-100 text-gray-700';
                            $badgeMap = [
                                'verde'    => 'bg-green-100 text-green-800',
                                'amarillo' => 'bg-yellow-100 text-yellow-800',
                                'rojo'     => 'bg-red-100 text-red-800',
                            ];
                        @endphp
                        <tr class="hover:bg-indigo-50/30 transition-colors {{ $riesgo->mayor_criticidad ? 'border-l-4 border-l-red-400' : '' }}">
                            <td class="px-6 py-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('auditoria.riesgos.show', $riesgo) }}"
                                           class="font-semibold text-gray-900 hover:text-indigo-600 transition-colors">
                                            {{ $riesgo->nombre }}
                                        </a>
                                        @if($riesgo->mayor_criticidad)
                                            <span class="inline-flex items-center px-1.5 rounded text-[9px] font-extrabold uppercase bg-red-100 text-red-700 tracking-wider">
                                                Mayor criticidad
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">{{ $riesgo->tipoRiesgo->nombre ?? '—' }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if($riesgo->estado)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize {{ $estadoClass }}">
                                        {{ $riesgo->estado->nombre }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center text-gray-600">
                                <div class="font-medium">{{ $riesgo->impacto }} <span class="text-gray-400 mx-1">/</span> {{ $riesgo->probabilidad }}</div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $badgeMap[$riesgo->clasificacion_total['color']] }}">
                                    {{ $riesgo->valor_total }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $badgeMap[$riesgo->clasificacion_residual['color']] }}">
                                    {{ $riesgo->valor_residual }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-600">
                                @forelse($riesgo->objetivos as $obj)
                                    {{ $obj->nombre }}@if(!$loop->last)<br>@endif
                                @empty
                                    <span class="text-gray-400">—</span>
                                @endforelse
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <div class="space-y-1">
                                    <div class="text-gray-600">{{ $riesgo->user->name ?? '—' }}</div>
                                    <div class="text-gray-500">{{ $riesgo->area->nombre ?? '—' }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('auditoria.riesgos.show', $riesgo) }}"
                                   class="text-indigo-600 hover:text-indigo-900 font-medium text-xs bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition-colors">
                                    Gestionar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-400 italic">
                                No hay riesgos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($riesgos->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $riesgos->links() }}
            </div>
        @endif
    </div>

</div>
