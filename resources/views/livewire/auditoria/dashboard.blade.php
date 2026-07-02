<div class="min-h-screen bg-gray-50 p-6" x-data>

    {{-- Flash --}}
    @if (session('ok'))
        <div class="mb-4 rounded bg-green-100 border border-green-300 text-green-800 px-4 py-2 text-sm shadow-sm">
            {{ session('ok') }}
        </div>
    @endif

    {{-- Header Dinámico --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Módulo de Auditoría</h1>
            <p class="text-sm text-gray-500 mt-1">Gestión integral de Riesgos, Controles y Planes de Acción</p>
        </div>
        
        @php
            $actionLabel = match($tabActiva) {
                'objetivos' => 'Nuevo Objetivo',
                'riesgos' => 'Nuevo Riesgo',
                'controles' => 'Nuevo Control',
                'planes' => 'Nuevo Plan',
                'tareas' => 'Nueva Tarea',
                default => 'Nuevo'
            };
            $actionType = match($tabActiva) {
                'objetivos' => 'crear_objetivo',
                'riesgos' => 'crear_riesgo',
                'controles' => 'crear_control',
                'planes' => 'crear_plan',
                'tareas' => 'crear_tarea',
                default => ''
            };
        @endphp

        <div class="flex items-center gap-3">
            <button wire:click="abrirModalCrear('{{ $actionType }}')" 
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ $actionLabel }}
            </button>
        </div>
    </div>

    {{-- Tabs Estilo Pill --}}
    <div class="flex overflow-x-auto pb-2 mb-8 gap-2 no-scrollbar">
        @foreach (['objetivos' => 'Objetivos', 'riesgos' => 'Riesgos', 'controles' => 'Controles', 'planes' => 'Planes de Acción', 'tareas' => 'Tareas'] as $tab => $label)
            <button
                wire:click="$set('tabActiva', '{{ $tab }}')"
                class="px-5 py-2.5 text-sm font-semibold rounded-full transition-all whitespace-nowrap
                    {{ $tabActiva === $tab
                        ? 'bg-indigo-100 text-indigo-700 shadow-sm'
                        : 'bg-white text-gray-500 hover:bg-gray-100 hover:text-gray-700' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ===================== TAB: OBJETIVOS ===================== --}}
    @if ($tabActiva === 'objetivos')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h2 class="text-lg font-bold text-gray-800">Listado de Objetivos</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 font-semibold uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-6 py-4 text-left">Nombre</th>
                            <th class="px-6 py-4 text-left">Descripción</th>
                            <th class="px-6 py-4 text-left">Fecha objetivo</th>
                            <th class="px-6 py-4 text-left">Riesgos asociados</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($objetivos as $objetivo)
                            <tr class="hover:bg-gray-50/80 transition-colors">
                                <td class="px-6 py-4 font-semibold text-gray-900">
                                    <button wire:click="abrirModalEditar('objetivo', {{ $objetivo->id }})" class="text-left hover:text-indigo-600 transition-colors">
                                        {{ $objetivo->nombre }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-gray-500 max-w-xs truncate">{{ $objetivo->descripcion ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-500">{{ $objetivo->fecha_objetivo?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    @if($objetivo->riesgos->count() > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                            {{ $objetivo->riesgos->count() }} Riesgo{{ $objetivo->riesgos->count() > 1 ? 's' : '' }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">Sin riesgos</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-12 text-center text-gray-400 italic">No hay objetivos cargados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ===================== TAB: RIESGOS ===================== --}}
    @if ($tabActiva === 'riesgos')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h2 class="text-lg font-bold text-gray-800">Evaluación de Riesgos</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 font-semibold uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-6 py-4 text-left">Nombre</th>
                            <th class="px-6 py-4 text-left">Tipo</th>
                            <th class="px-4 py-4 text-center border-l border-gray-100" title="Impacto por Probabilidad">Matriz (I x P)</th>
                            <th class="px-4 py-4 text-center" title="Valor Total del Riesgo">Total</th>
                            <th class="px-4 py-4 text-center border-r border-gray-100" title="Valor Residual tras Controles">Residual</th>
                            <th class="px-6 py-4 text-left">Asociaciones</th>
                            <th class="px-6 py-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($riesgos as $riesgo)
                            <tr wire:click="abrirModalEditar('riesgo', {{ $riesgo->id }})" class="hover:bg-indigo-50/30 transition-colors cursor-pointer group">
                                <td class="px-6 py-4 font-semibold text-gray-900">
                                    <span class="group-hover:text-indigo-600 transition-colors">
                                        {{ $riesgo->nombre }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500 text-xs">{{ $riesgo->tipoRiesgo->nombre ?? '—' }}</td>
                                <td class="px-4 py-4 text-center text-gray-500 border-l border-gray-100">
                                    {{ $riesgo->impacto }} <span class="text-gray-300 mx-1">×</span> {{ $riesgo->probabilidad }}
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-orange-100 text-orange-800">
                                        {{ $riesgo->valor_total }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-center border-r border-gray-100">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                        {{ $riesgo->valor_residual }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @if($riesgo->controles->count() > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-100" title="{{ $riesgo->controles->count() }} Controles asociados">
                                                {{ $riesgo->controles->count() }} Ctrl
                                            </span>
                                        @endif
                                        @if($riesgo->objetivos->count() > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-purple-50 text-purple-700 border border-purple-100" title="{{ $riesgo->objetivos->count() }} Objetivos asociados">
                                                {{ $riesgo->objetivos->count() }} Obj
                                            </span>
                                        @endif
                                        @php
                                            $tareasCount = $riesgo->planesAccion->flatMap->tareas->unique('id')->count();
                                        @endphp
                                        @if($tareasCount > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-teal-50 text-teal-700 border border-teal-100" title="{{ $tareasCount }} Tareas asociadas a través de Planes">
                                                {{ $tareasCount }} Tar
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right" onclick="event.stopPropagation()">
                                    <button wire:click="abrirModalEditar('riesgo', {{ $riesgo->id }})" 
                                        class="text-indigo-600 hover:text-indigo-900 font-medium text-xs bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition-colors">
                                        Gestionar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400 italic">No hay riesgos cargados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ===================== TAB: CONTROLES ===================== --}}
    @if ($tabActiva === 'controles')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h2 class="text-lg font-bold text-gray-800">Catálogo de Controles</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 font-semibold uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-6 py-4 text-left">Nombre</th>
                            <th class="px-6 py-4 text-left">Descripción</th>
                            <th class="px-6 py-4 text-center">Mitigación</th>
                            <th class="px-6 py-4 text-left">Riesgos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($controles as $control)
                            <tr class="hover:bg-gray-50/80 transition-colors">
                                <td class="px-6 py-4 font-semibold text-gray-900">
                                    <button wire:click="abrirModalEditar('control', {{ $control->id }})" class="text-left hover:text-indigo-600 transition-colors">
                                        {{ $control->nombre }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-gray-500 max-w-xs truncate">{{ $control->descripcion ?? '—' }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                        {{ $control->mitigacion_default }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($control->riesgos->count() > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                            {{ $control->riesgos->count() }} Riesgo{{ $control->riesgos->count() > 1 ? 's' : '' }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">Sin riesgos</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-12 text-center text-gray-400 italic">No hay controles cargados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ===================== TAB: PLANES DE ACCIÓN ===================== --}}
    @if ($tabActiva === 'planes')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h2 class="text-lg font-bold text-gray-800">Planes de Acción</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 font-semibold uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-6 py-4 text-left">Código</th>
                            <th class="px-6 py-4 text-left">Nombre / Riesgo</th>
                            <th class="px-6 py-4 text-left">Tareas Asociadas</th>
                            <th class="px-6 py-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($planes as $plan)
                            <tr class="hover:bg-gray-50/80 transition-colors">
                                <td class="px-6 py-4 font-mono text-xs text-gray-400 font-bold uppercase tracking-widest">{{ $plan->codigo }}</td>
                                <td class="px-6 py-4">
                                    <button wire:click="abrirModalEditar('plan', {{ $plan->id }})" class="text-left font-semibold text-gray-900 hover:text-indigo-600 transition-colors block">
                                        {{ $plan->nombre }}
                                    </button>
                                    <span class="text-[10px] text-gray-400 uppercase font-bold">{{ $plan->riesgo->nombre ?? 'Sin riesgo' }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($plan->tareas->count() > 0)
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-teal-100 text-teal-800">
                                                {{ $plan->tareas->count() }} Tarea{{ $plan->tareas->count() > 1 ? 's' : '' }}
                                            </span>
                                            @php
                                                $avgAvance = $plan->tareas->avg('porcentaje_avance');
                                            @endphp
                                            <div class="w-24 bg-gray-200 rounded-full h-1.5 overflow-hidden hidden sm:block">
                                                <div class="bg-teal-500 h-1.5 rounded-full" style="width: {{ $avgAvance }}%"></div>
                                            </div>
                                            <span class="text-[10px] text-gray-400 font-bold">{{ round($avgAvance) }}%</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">Sin tareas</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button wire:click="abrirModalAsociar('asociar_tarea_plan', {{ $plan->id }})" 
                                        class="text-teal-600 hover:text-teal-900 font-medium text-xs bg-teal-50 hover:bg-teal-100 px-3 py-1.5 rounded-lg transition-colors">
                                        + Tarea
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-12 text-center text-gray-400 italic">No hay planes cargados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ===================== TAB: TAREAS ===================== --}}
    @if ($tabActiva === 'tareas')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h2 class="text-lg font-bold text-gray-800">Seguimiento de Tareas</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 font-semibold uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-6 py-4 text-left">Nombre</th>
                            <th class="px-6 py-4 text-center">Avance</th>
                            <th class="px-6 py-4 text-left">Relaciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($tareas as $tarea)
                            <tr class="hover:bg-gray-50/80 transition-colors">
                                <td class="px-6 py-4 font-semibold text-gray-900">
                                    <button wire:click="abrirModalEditar('tarea', {{ $tarea->id }})" class="text-left hover:text-indigo-600 transition-colors">
                                        {{ $tarea->nombre }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="text-[10px] font-extrabold uppercase {{ $tarea->porcentaje_avance === 100 ? 'text-green-600' : 'text-amber-600' }}">
                                            {{ $tarea->porcentaje_avance === 100 ? 'Completado' : 'En Progreso' }}
                                        </span>
                                        <div class="w-20 bg-gray-100 rounded-full h-2 overflow-hidden border border-gray-200 p-[1px]">
                                            <div class="h-full rounded-full transition-all duration-500 {{ $tarea->porcentaje_avance === 100 ? 'bg-green-500' : 'bg-amber-500' }}" style="width: {{ $tarea->porcentaje_avance }}%"></div>
                                        </div>
                                        <span class="text-[10px] text-gray-400 font-bold">{{ $tarea->porcentaje_avance }}%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @if($tarea->riesgos->count() > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-orange-50 text-orange-700 border border-orange-100">
                                                {{ $tarea->riesgos->count() }} Riesgo{{ $tarea->riesgos->count() > 1 ? 's' : '' }}
                                            </span>
                                        @endif
                                        @if($tarea->planesAccion->count() > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                                {{ $tarea->planesAccion->count() }} Plan{{ $tarea->planesAccion->count() > 1 ? 'es' : '' }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-12 text-center text-gray-400 italic">No hay tareas cargadas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif


    {{-- ===================== MODAL ===================== --}}
    @if ($modalAbierto)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-data
            x-on:keydown.escape.window="$wire.cerrarModal()"
        >
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="cerrarModal"></div>

            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden border border-gray-100" x-on:click.stop>

                {{-- Header modal --}}
                <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <h3 class="text-lg font-extrabold text-gray-900 tracking-tight">
                        @switch($modalTipo)
                            @case('crear_riesgo')      Nuevo Riesgo @break
                            @case('editar_riesgo')     Editar Riesgo @break
                            @case('crear_control')     Nuevo Control @break
                            @case('editar_control')    Editar Control @break
                            @case('crear_objetivo')    Nuevo Objetivo @break
                            @case('editar_objetivo')   Editar Objetivo @break
                            @case('crear_plan')        Nuevo Plan de Acción @break
                            @case('editar_plan')       Editar Plan de Acción @break
                            @case('crear_tarea')       Nueva Tarea @break
                            @case('editar_tarea')      Editar Tarea @break
                            @case('asociar_control')   Asociar Controles @break
                            @case('asociar_objetivo')  Asociar Objetivos @break
                            @case('asociar_tarea_plan')   Asociar Tareas al Plan @break
                            @default Modal
                        @endswitch
                    </h3>
                    <button wire:click="cerrarModal" class="p-2 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <div class="p-0 max-h-[85vh] overflow-y-auto">
                    <div class="flex flex-col md:flex-row">
                        {{-- Columna Principal: Formulario --}}
                        <div class="flex-1 p-6 border-b md:border-b-0 md:border-r border-gray-100">
                            {{-- ---- Formulario: Riesgo ---- --}}
                            @if ($modalTipo === 'crear_riesgo' || $modalTipo === 'editar_riesgo')
                                <div class="space-y-5">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nombre del Riesgo *</label>
                                        <input type="text" wire:model="form.nombre" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all" placeholder="Ej: Fuga de datos..." />
                                        @error('form.nombre') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Descripción</label>
                                        <textarea wire:model="form.descripcion" rows="4" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all" placeholder="Detalles adicionales..."></textarea>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Impacto (0-10) *</label>
                                            <input type="number" min="0" max="10" wire:model="form.impacto" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all" />
                                            @error('form.impacto') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Probabilidad (0-10) *</label>
                                            <input type="number" min="0" max="10" wire:model="form.probabilidad" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all" />
                                            @error('form.probabilidad') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Tipo de Riesgo *</label>
                                        <select wire:model="form.tipo_riesgo_id" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all">
                                            <option value="">— Seleccionar Categoría —</option>
                                            @foreach ($tiposRiesgo as $tipo)
                                                <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                                            @endforeach
                                        </select>
                                        @error('form.tipo_riesgo_id') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            @endif

                            {{-- ---- Formulario: Control ---- --}}
                            @if ($modalTipo === 'crear_control' || $modalTipo === 'editar_control')
                                <div class="space-y-5">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nombre del Control *</label>
                                        <input type="text" wire:model="form.nombre" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all" />
                                        @error('form.nombre') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Descripción</label>
                                        <textarea wire:model="form.descripcion" rows="4" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Mitigación default (1-10) *</label>
                                        <input type="number" min="1" max="10" wire:model="form.mitigacion_default" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all" />
                                        @error('form.mitigacion_default') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            @endif

                            {{-- ---- Formulario: Objetivo ---- --}}
                            @if ($modalTipo === 'crear_objetivo' || $modalTipo === 'editar_objetivo')
                                <div class="space-y-5">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nombre del Objetivo *</label>
                                        <input type="text" wire:model="form.nombre" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all" />
                                        @error('form.nombre') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Descripción</label>
                                        <textarea wire:model="form.descripcion" rows="4" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Fecha objetivo</label>
                                        <input type="date" wire:model="form.fecha_objetivo" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all" />
                                    </div>
                                </div>
                            @endif

                            {{-- ---- Formulario: Plan de Acción ---- --}}
                            @if ($modalTipo === 'crear_plan' || $modalTipo === 'editar_plan')
                                <div class="space-y-5">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Riesgo Asociado *</label>
                                        <select wire:model="form.riesgo_id" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all">
                                            <option value="">— Seleccionar Riesgo —</option>
                                            @foreach ($todosRiesgos as $r)
                                                <option value="{{ $r->id }}">{{ $r->nombre }}</option>
                                            @endforeach
                                        </select>
                                        @error('form.riesgo_id') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Código Identificador *</label>
                                        <div class="flex gap-2">
                                            <input type="text" wire:model="form.codigo" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all flex-1" placeholder="PA-0001" />
                                            <button wire:click="sugerirCodigo" type="button"
                                                class="px-4 py-3 text-xs bg-gray-100 hover:bg-gray-200 rounded-xl border border-gray-200 text-gray-600 font-bold uppercase tracking-wider transition-colors">
                                                Sugerir
                                            </button>
                                        </div>
                                        @error('form.codigo') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nombre del Plan *</label>
                                        <input type="text" wire:model="form.nombre" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all" />
                                        @error('form.nombre') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Descripción</label>
                                        <textarea wire:model="form.descripcion" rows="4" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all"></textarea>
                                    </div>
                                </div>
                            @endif

                            {{-- ---- Formulario: Tarea ---- --}}
                            @if ($modalTipo === 'crear_tarea' || $modalTipo === 'editar_tarea')
                                <div class="space-y-5">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nombre de la Tarea *</label>
                                        <input type="text" wire:model="form.nombre" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all" />
                                        @error('form.nombre') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Descripción</label>
                                        <textarea wire:model="form.descripcion" rows="4" class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Porcentaje de Avance ({{ $form['porcentaje_avance'] ?? 0 }}%) *</label>
                                        <input type="range" min="0" max="100" step="5" wire:model="form.porcentaje_avance" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-indigo-600 mb-2" />
                                        <div class="flex justify-between text-[10px] text-gray-400 font-bold uppercase">
                                            <span>Inicio</span>
                                            <span>Completo</span>
                                        </div>
                                        @error('form.porcentaje_avance') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            @endif

                            {{-- ---- Selección Múltiple (Asociaciones) ---- --}}
                            @if (in_array($modalTipo, ['asociar_control', 'asociar_objetivo', 'asociar_tarea_plan']))
                                <div class="space-y-2">
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Selecciona los elementos a vincular:</p>
                                    <div class="space-y-2 max-h-96 overflow-y-auto pr-2 custom-scrollbar">
                                        @php
                                            $items = match($modalTipo) {
                                                'asociar_control' => $todosControles,
                                                'asociar_objetivo' => $todosObjetivos,
                                                default => $todasTareas
                                            };
                                        @endphp
                                        @forelse ($items as $item)
                                            <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50/30 cursor-pointer transition-all group">
                                                <div class="relative flex items-center">
                                                    <input type="checkbox" wire:model="idsParaAsociar" value="{{ $item->id }}" class="w-5 h-5 rounded-lg border-gray-300 text-indigo-600 focus:ring-indigo-500 transition-all" />
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="text-sm font-semibold text-gray-700 group-hover:text-indigo-700 transition-colors">{{ $item->nombre }}</span>
                                                    @if($modalTipo === 'asociar_control')
                                                        <span class="text-[10px] text-gray-400 font-bold uppercase">Mitigación: {{ $item->mitigacion_default }}</span>
                                                    @elseif(str_contains($modalTipo, 'tarea'))
                                                        <span class="text-[10px] text-gray-400 font-bold uppercase">Avance: {{ $item->porcentaje_avance }}%</span>
                                                    @endif
                                                </div>
                                            </label>
                                        @empty
                                            <div class="py-8 text-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-200 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                                </svg>
                                                <p class="text-sm text-gray-400 font-medium italic">No hay elementos disponibles para asociar.</p>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Columna Lateral: Información de Relaciones (Solo en edición) --}}
                        @if (str_starts_with($modalTipo, 'editar_'))
                            <div class="w-full md:w-80 bg-gray-50/50 p-6 flex flex-col gap-6 border-t md:border-t-0 border-gray-100">
                                @if (isset($entidadActual))
                                    {{-- Riesgo: Muestra Controles, Objetivos y Tareas --}}
                                    @if ($modalTipo === 'editar_riesgo')
                                        <div class="space-y-4">
                                            <div class="flex justify-between items-end">
                                                <h4 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Controles</h4>
                                                <button wire:click="abrirModalAsociar('asociar_control', {{ $entidadEditandoId }})" class="text-[10px] font-bold text-indigo-600 hover:underline">Gestionar</button>
                                            </div>
                                            <div class="space-y-2">
                                                @forelse ($entidadActual->controles as $c)
                                                    <div class="p-3 bg-white rounded-xl border border-gray-100 shadow-sm flex justify-between items-center group/item hover:border-indigo-100 transition-colors">
                                                        <div class="text-xs font-semibold text-gray-700">{{ $c->nombre }}</div>
                                                        <div class="text-[10px] bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full font-bold border border-blue-100" title="Valor de Mitigación">
                                                            {{ $c->pivot->mitigacion ?? $c->mitigacion_default }}
                                                        </div>
                                                    </div>
                                                @empty
                                                    <p class="text-xs text-gray-400 italic">Sin controles.</p>
                                                @endforelse
                                            </div>
                                        </div>

                                        <div class="space-y-4 pt-4 border-t border-gray-200/50">
                                            <div class="flex justify-between items-end">
                                                <h4 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Objetivos</h4>
                                                <button wire:click="abrirModalAsociar('asociar_objetivo', {{ $entidadEditandoId }})" class="text-[10px] font-bold text-indigo-600 hover:underline">Gestionar</button>
                                            </div>
                                            <div class="space-y-2">
                                                @forelse ($entidadActual->objetivos as $o)
                                                    <div class="p-2 bg-white rounded-lg border border-gray-100 shadow-sm text-xs font-semibold text-gray-700">
                                                        {{ $o->nombre }}
                                                    </div>
                                                @empty
                                                    <p class="text-xs text-gray-400 italic">Sin objetivos.</p>
                                                @endforelse
                                            </div>
                                        </div>

                                        <div class="space-y-4 pt-4 border-t border-gray-200/50">
                                            <div class="flex justify-between items-end">
                                                <h4 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Tareas (vía Planes)</h4>
                                            </div>
                                            <div class="space-y-2">
                                                @php
                                                    $tareasRiesgo = $entidadActual->planesAccion->flatMap->tareas->unique('id');
                                                @endphp
                                                @forelse ($tareasRiesgo as $t)
                                                    <div class="p-2 bg-white rounded-lg border border-gray-100 shadow-sm text-xs">
                                                        <div class="font-semibold text-gray-700">{{ $t->nombre }}</div>
                                                        <div class="text-[10px] text-gray-400">{{ $t->porcentaje_avance }}% avance</div>
                                                    </div>
                                                @empty
                                                    <p class="text-xs text-gray-400 italic">Sin tareas vinculadas.</p>
                                                @endforelse
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Plan: Muestra Tareas --}}
                                    @if ($modalTipo === 'editar_plan')
                                        <div class="space-y-4">
                                            <div class="flex justify-between items-end">
                                                <h4 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Tareas del Plan</h4>
                                                <button wire:click="abrirModalAsociar('asociar_tarea_plan', {{ $entidadEditandoId }})" class="text-[10px] font-bold text-indigo-600 hover:underline">Gestionar</button>
                                            </div>
                                            <div class="space-y-2">
                                                @forelse ($entidadActual->tareas as $t)
                                                    <div class="p-2 bg-white rounded-lg border border-gray-100 shadow-sm text-xs">
                                                        <div class="font-semibold text-gray-700">{{ $t->nombre }}</div>
                                                        <div class="text-[10px] text-gray-400">{{ $t->porcentaje_avance }}% avance</div>
                                                    </div>
                                                @empty
                                                    <p class="text-xs text-gray-400 italic">Sin tareas.</p>
                                                @endforelse
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Tarea: Muestra Riesgos y Planes --}}
                                    @if ($modalTipo === 'editar_tarea')
                                        <div class="space-y-4">
                                            <h4 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Riesgos Asociados (vía Planes)</h4>
                                            <div class="space-y-2">
                                                @php
                                                    $riesgosTarea = $entidadActual->planesAccion->map->riesgo->unique('id');
                                                @endphp
                                                @forelse ($riesgosTarea as $r)
                                                    @if($r)
                                                        <div class="p-2 bg-white rounded-lg border border-gray-100 shadow-sm text-xs font-semibold text-gray-700">
                                                            {{ $r->nombre }}
                                                        </div>
                                                    @endif
                                                @empty
                                                    <p class="text-xs text-gray-400 italic">Sin riesgos vinculados.</p>
                                                @endforelse
                                            </div>
                                        </div>
                                        <div class="space-y-4 pt-4 border-t border-gray-200/50">
                                            <h4 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Planes de Acción</h4>
                                            <div class="space-y-2">
                                                @forelse ($entidadActual->planesAccion as $p)
                                                    <div class="p-2 bg-white rounded-lg border border-gray-100 shadow-sm text-xs">
                                                        <div class="font-semibold text-gray-700">{{ $p->nombre }}</div>
                                                        <div class="font-mono text-[9px] text-gray-400">{{ $p->codigo }}</div>
                                                    </div>
                                                @empty
                                                    <p class="text-xs text-gray-400 italic">Sin planes.</p>
                                                @endforelse
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Control/Objetivo: Muestra Riesgos --}}
                                    @if ($modalTipo === 'editar_control' || $modalTipo === 'editar_objetivo')
                                        <div class="space-y-4">
                                            <h4 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-widest">Riesgos Mitigados</h4>
                                            <div class="space-y-2">
                                                @forelse ($entidadActual->riesgos as $r)
                                                    <div class="p-2 bg-white rounded-lg border border-gray-100 shadow-sm text-xs font-semibold text-gray-700">
                                                        {{ $r->nombre }}
                                                    </div>
                                                @empty
                                                    <p class="text-xs text-gray-400 italic">Sin riesgos.</p>
                                                @endforelse
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Footer modal --}}
                <div class="px-6 py-5 border-t border-gray-100 flex justify-end gap-3 bg-gray-50/50">
                    <button wire:click="cerrarModal" class="px-5 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors">Cerrar</button>
                    @if (str_starts_with($modalTipo, 'crear_') || str_starts_with($modalTipo, 'editar_'))
                        <button wire:click="guardar" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-extrabold rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all active:scale-95">
                            {{ str_starts_with($modalTipo, 'crear_') ? 'Crear Elemento' : 'Guardar Cambios' }}
                        </button>
                    @else
                        <button wire:click="asociar" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-extrabold rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all active:scale-95">
                            Guardar Asociaciones
                        </button>
                    @endif
                </div>

            </div>
        </div>
    @endif

</div>
