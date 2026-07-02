<div class="space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Planes de Acción</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $planes->count() }} plan(es) registrado(s)</p>
        </div>
        <a href="{{ route('auditoria.planes.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 shadow-sm transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nuevo Plan
        </a>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Búsqueda -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-2">Buscar</label>
                <input type="text" wire:model.live="search" placeholder="Nombre o código..."
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
                                Código / Nombre
                                @if($ordenarPor === 'nombre')
                                    <span class="text-indigo-600">{{ $direccion === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-4 text-left">Estado</th>
                        <th class="px-6 py-4 text-left">Descripción</th>
                        <th class="px-6 py-4 text-left">Riesgos</th>
                        <th class="px-4 py-4 text-center">Avance</th>
                        <th class="px-4 py-4 text-center">Vencimiento</th>
                        <th class="px-6 py-4 text-left">Usuario / Área</th>
                        <th class="px-6 py-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($planes as $plan)
                        @php
                            $colorMap = ['gray'=>'bg-gray-100 text-gray-700','blue'=>'bg-blue-100 text-blue-700','green'=>'bg-green-100 text-green-700','purple'=>'bg-purple-100 text-purple-700','red'=>'bg-red-100 text-red-700'];
                            $estadoClass = $colorMap[$plan->estado->color ?? ''] ?? 'bg-gray-100 text-gray-700';
                        @endphp
                        <tr class="hover:bg-indigo-50/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="space-y-1">
                                    <div class="font-semibold text-gray-900">
                                        <a href="{{ route('auditoria.planes.show', $plan) }}"
                                           class="hover:text-indigo-600 transition-colors">
                                            {{ $plan->nombre }}
                                        </a>
                                    </div>
                                    <div class="text-xs text-gray-500 font-mono">{{ $plan->codigo }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if($plan->estado)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize {{ $estadoClass }}">
                                        {{ $plan->estado->nombre }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-600 text-xs">
                                {{ Str::limit($plan->descripcion, 50) ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-600">
                                @forelse($plan->riesgos as $riesgo)
                                    {{ $riesgo->nombre }}@if(!$loop->last)<br>@endif
                                @empty
                                    <span class="text-gray-400">—</span>
                                @endforelse
                            </td>
                            @php
                                $avancePlan = $plan->tareas->count() ? round($plan->tareas->avg('porcentaje_avance')) : null;
                                $vencPlan   = $plan->tareas->whereNotNull('fecha')->max('fecha');
                                $hoy        = now()->startOfDay();
                                $planVencido = $vencPlan && \Carbon\Carbon::parse($vencPlan)->lt($hoy) && ($avancePlan ?? 0) < 100;
                            @endphp
                            <td class="px-4 py-4 text-center">
                                @if($avancePlan !== null)
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-16 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                            <div class="h-1.5 rounded-full {{ $avancePlan === 100 ? 'bg-green-500' : ($planVencido ? 'bg-red-400' : 'bg-amber-500') }}"
                                                 style="width: {{ $avancePlan }}%"></div>
                                        </div>
                                        <span class="text-[10px] font-bold {{ $avancePlan === 100 ? 'text-green-600' : ($planVencido ? 'text-red-600' : 'text-amber-600') }}">
                                            {{ $avancePlan }}%
                                        </span>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-center">
                                @if($vencPlan)
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span class="text-xs {{ $planVencido ? 'text-red-700 font-bold' : 'text-gray-600' }}">
                                            {{ \Carbon\Carbon::parse($vencPlan)->format('d/m/Y') }}
                                        </span>
                                        @if($planVencido)
                                            <span class="text-[9px] font-bold bg-red-100 text-red-700 px-1.5 py-0.5 rounded uppercase">Vencido</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <div class="space-y-1">
                                    <div class="text-gray-600">{{ $plan->user->name ?? '—' }}</div>
                                    <div class="text-gray-500">{{ $plan->area->nombre ?? '—' }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('auditoria.planes.show', $plan) }}"
                                   class="text-indigo-600 hover:text-indigo-900 font-medium text-xs bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition-colors">
                                    Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-400 italic">
                                No hay planes de acción registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($planes->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $planes->links() }}
            </div>
        @endif
    </div>

</div>
