<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Información</h2>

    @if($planAccion->descripcion)
        <p class="text-sm text-gray-700 mb-4">{{ $planAccion->descripcion }}</p>
    @endif

    @php
        // Avance sólo sobre tareas aprobadas (ver PlanAccion::getAvanceAttribute).
        $avg = $planAccion->avance;
    @endphp
    @if($avg !== null)
        <div class="mb-4">
            <div class="flex justify-between items-center mb-1">
                <span class="text-xs text-gray-400 font-medium">Avance General</span>
                <span class="text-sm font-extrabold {{ $avg === 100 ? 'text-green-600' : 'text-amber-600' }}">{{ $avg }}%</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                <div class="h-2.5 rounded-full transition-all {{ $avg === 100 ? 'bg-green-500' : 'bg-amber-500' }}" style="width: {{ $avg }}%"></div>
            </div>
        </div>
    @endif

    <dl class="space-y-3 text-sm">
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Estado</dt>
            <dd><x-auditoria.estado-punto :estado="$planAccion->estado" /></dd>
        </div>
        @if($planAccion->codigo)
            <div class="flex justify-between">
                <dt class="text-gray-400 font-medium">Código</dt>
                <dd class="font-mono text-xs font-bold text-gray-600 uppercase">{{ $planAccion->codigo }}</dd>
            </div>
        @endif
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Vencimiento</dt>
            <dd>
                @if($planAccion->vencimiento)
                    <span class="{{ $planAccion->esta_vencido ? 'text-red-700 font-bold' : 'text-gray-700' }}">
                        {{ $planAccion->vencimiento->format('d/m/Y') }}
                    </span>
                    @if($planAccion->esta_vencido)
                        <span class="ml-1.5 text-[10px] font-bold bg-red-100 text-red-700 px-1.5 py-0.5 rounded">Vencido</span>
                    @endif
                @else
                    <span class="text-gray-400">Sin fecha definida</span>
                @endif
            </dd>
        </div>
        @if($planAccion->area)
            <div class="flex justify-between">
                <dt class="text-gray-400 font-medium">Área</dt>
                <dd class="text-gray-700">{{ $planAccion->area->nombre }}</dd>
            </div>
        @endif
        @if($planAccion->user)
            <div class="flex justify-between">
                <dt class="text-gray-400 font-medium">Responsable</dt>
                <dd class="text-gray-700">{{ $planAccion->user->name }}</dd>
            </div>
        @endif
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Fecha de creación</dt>
            <dd class="text-gray-700">{{ $planAccion->created_at->format('d/m/Y') }}</dd>
        </div>
    </dl>
</div>
