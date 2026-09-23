<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Información</h2>

    @if($control->descripcion)
        <p class="text-sm text-gray-700 mb-4">{{ $control->descripcion }}</p>
    @endif

    <div class="bg-blue-50 rounded-xl p-4 text-center mb-4">
        <div class="text-4xl font-extrabold text-blue-700">{{ $control->mitigacion_default }}</div>
        <div class="text-[10px] text-blue-500 uppercase font-bold tracking-wider mt-0.5">Mitigación por defecto</div>
    </div>

    <dl class="space-y-3 text-sm">
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Estado</dt>
            <dd><x-auditoria.estado-punto :estado="$control->estado" /></dd>
        </div>
        @if($control->area)
            <div class="flex justify-between">
                <dt class="text-gray-400 font-medium">Área</dt>
                <dd class="text-gray-700">{{ $control->area->nombre }}</dd>
            </div>
        @endif
        @if($control->user)
            <div class="flex justify-between">
                <dt class="text-gray-400 font-medium">Registrado por</dt>
                <dd class="text-gray-700">{{ $control->user->name }}</dd>
            </div>
        @endif
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Fecha de creación</dt>
            <dd class="text-gray-700">{{ $control->created_at->format('d/m/Y') }}</dd>
        </div>
    </dl>
</div>
