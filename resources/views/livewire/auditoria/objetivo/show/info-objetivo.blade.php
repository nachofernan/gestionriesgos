<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Información</h2>

    @if($objetivo->descripcion)
        <p class="text-sm text-gray-700 mb-4">{{ $objetivo->descripcion }}</p>
    @endif

    <dl class="space-y-3 text-sm">
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Estado</dt>
            <dd><x-auditoria.estado-punto :estado="$objetivo->estado" /></dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Fecha objetivo</dt>
            <dd class="font-semibold text-gray-800">
                {{ $objetivo->fecha_objetivo?->format('d/m/Y') ?? 'No aplica' }}
            </dd>
        </div>
        @if($objetivo->area)
            <div class="flex justify-between">
                <dt class="text-gray-400 font-medium">Área</dt>
                <dd class="text-gray-700">{{ $objetivo->area->nombre }}</dd>
            </div>
        @endif
        @if($objetivo->user)
            <div class="flex justify-between">
                <dt class="text-gray-400 font-medium">Registrado por</dt>
                <dd class="text-gray-700">{{ $objetivo->user->name }}</dd>
            </div>
        @endif
        @if($objetivo->estrategico || $objetivo->peis)
            <div class="flex justify-between items-start">
                <dt class="text-gray-400 font-medium">Clasificación</dt>
                <dd class="flex gap-1.5 flex-wrap justify-end">
                    @if($objetivo->estrategico)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700">Estratégico</span>
                    @endif
                    @if($objetivo->peis)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">PEIS</span>
                    @endif
                </dd>
            </div>
        @endif
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Fecha de creación</dt>
            <dd class="text-gray-700">{{ $objetivo->created_at->format('d/m/Y') }}</dd>
        </div>
    </dl>

    @if($objetivo->peis && $objetivo->peisItems->isNotEmpty())
        <div class="mt-4 pt-4 border-t border-gray-100">
            <p class="text-xs font-bold text-amber-700 uppercase tracking-wider mb-2">Contribuye al PEIS en los siguientes puntos</p>
            <ul class="space-y-2">
                @foreach($objetivo->peisItems as $item)
                    <li class="flex items-start gap-2 text-sm text-gray-700">
                        <svg class="h-4 w-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ $item->descripcion }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
