<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Información</h2>

    @if($tarea->descripcion)
        <p class="text-sm text-gray-700 mb-4">{{ $tarea->descripcion }}</p>
    @endif

    <div class="mb-4">
        <div class="flex justify-between items-center mb-2">
            <span class="text-xs text-gray-400 font-medium">Avance</span>
            <span class="text-lg font-extrabold {{ $tarea->porcentaje_avance === 100 ? 'text-green-600' : 'text-amber-600' }}">
                {{ $tarea->porcentaje_avance }}%
            </span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden border border-gray-200 p-px">
            <div class="h-full rounded-full transition-all
                {{ $tarea->porcentaje_avance === 100 ? 'bg-green-500' : ($tareaVencida ? 'bg-red-400' : 'bg-amber-500') }}"
                 style="width: {{ $tarea->porcentaje_avance }}%"></div>
        </div>
        <p class="text-center text-[10px] font-extrabold uppercase mt-1
            {{ $tarea->porcentaje_avance === 100 ? 'text-green-600' : ($tareaVencida ? 'text-red-600' : 'text-amber-600') }}">
            @if($tarea->porcentaje_avance === 100) Completada
            @elseif($tareaVencida) Vencida
            @else En Progreso
            @endif
        </p>
    </div>

    <dl class="space-y-3 text-sm">
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Estado</dt>
            <dd><x-auditoria.estado-punto :estado="$tarea->estado" /></dd>
        </div>
        @if($tarea->area)
            <div class="flex justify-between">
                <dt class="text-gray-400 font-medium">Área</dt>
                <dd class="text-gray-700">{{ $tarea->area->nombre }}</dd>
            </div>
        @endif
        @if($tarea->fecha)
            <div class="flex justify-between">
                <dt class="text-gray-400 font-medium">Fecha límite</dt>
                <dd class="font-semibold {{ $tareaVencida ? 'text-red-700' : 'text-gray-800' }}">
                    {{ \Carbon\Carbon::parse($tarea->fecha)->format('d/m/Y') }}
                    @if($tareaVencida)
                        <span class="ml-1 text-[10px] font-bold bg-red-100 text-red-700 px-1.5 py-0.5 rounded">Vencida</span>
                    @endif
                </dd>
            </div>
        @endif
        @if($tarea->user)
            <div class="flex justify-between">
                <dt class="text-gray-400 font-medium">Asignado a</dt>
                <dd class="text-gray-700">{{ $tarea->user->name }}</dd>
            </div>
        @endif
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Fecha de creación</dt>
            <dd class="text-gray-700">{{ $tarea->created_at->format('d/m/Y') }}</dd>
        </div>
    </dl>
</div>
