<div class="space-y-6">

    {{-- Encabezado --}}
    <div class="flex items-end justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Panel de Riesgos</h1>
            <p class="text-sm text-gray-500 mt-1">Situación y reportes de la matriz que podés ver.</p>
        </div>
        @if($urlExcel)
            <a href="{{ $urlExcel }}"
               class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                Descargar Excel
            </a>
        @endif
    </div>

    {{-- Pestañas --}}
    <div class="border-b border-gray-200 overflow-x-auto">
        <nav class="flex gap-1 -mb-px min-w-max">
            @foreach($pestanas as $clave => $etiqueta)
                <button type="button" wire:click="$set('pestana', '{{ $clave }}')"
                        class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap
                               {{ $pestana === $clave ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    {{ $etiqueta }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Filtros (valen para todas las pestañas y para el Excel) --}}
    <div class="flex items-center gap-x-5 gap-y-3 flex-wrap">
        <label class="flex items-center gap-2 cursor-pointer select-none" title="Encendido: sólo riesgos aprobados. Apagado: también los validados. Nunca borradores.">
            <button type="button" wire:click="$toggle('soloAprobados')"
                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors {{ $soloAprobados ? 'bg-indigo-600' : 'bg-gray-300' }}">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $soloAprobados ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
            </button>
            <span class="text-xs font-medium text-gray-600">Solo aprobados</span>
        </label>

        @if($pestana !== 'planes')
            <select wire:model.live="filtroTipo"
                    class="text-xs border-gray-300 rounded-lg py-1.5 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Todos los tipos</option>
                @foreach($tipos as $tipo)
                    <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                @endforeach
            </select>
            <select wire:model.live="filtroRespuesta"
                    class="text-xs border-gray-300 rounded-lg py-1.5 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Todas las respuestas</option>
                @foreach($respuestas as $respuesta)
                    <option value="{{ $respuesta->value }}">{{ $respuesta->label() }}</option>
                @endforeach
            </select>
            @if($filtroTipo !== '' || $filtroRespuesta !== '')
                <button type="button" wire:click="limpiarFiltros" class="text-xs text-indigo-600 hover:underline">Limpiar filtros</button>
            @endif
        @endif

        @if($pestana === 'evolucion')
            <div class="flex items-center gap-2 text-xs text-gray-600">
                <span class="font-medium">Desde</span>
                <input type="date" wire:model.live="desde" class="text-xs border-gray-300 rounded-lg py-1.5 focus:ring-indigo-500 focus:border-indigo-500">
                <span class="font-medium">hasta</span>
                <input type="date" wire:model.live="hasta" class="text-xs border-gray-300 rounded-lg py-1.5 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        @endif
    </div>

    <div wire:loading.class="opacity-50" class="transition-opacity">
        @include('livewire.auditoria.panel.'.$pestana)
    </div>

</div>
