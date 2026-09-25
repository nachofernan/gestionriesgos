<section id="mitigacion" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-4 pt-4 pb-3">
        <h2 class="text-[13px] font-extrabold text-gray-900 tracking-tight">Mitigación</h2>
    </div>

    {{-- Mitigación por defecto, protagonista --}}
    <div class="mx-4 rounded-xl px-4 py-3 flex items-end justify-between {{ $mitiga ? 'bg-indigo-50' : 'bg-gray-50' }}">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-widest opacity-80 {{ $mitiga ? 'text-indigo-700' : 'text-gray-500' }}">Por defecto</p>
            <p class="text-4xl font-black tabular-nums leading-none {{ $mitiga ? 'text-indigo-700' : 'text-gray-500' }}">
                {{ $control->mitigacion_default }}<span class="text-sm font-bold opacity-50">/10</span>
            </p>
        </div>
        <p class="text-right text-[11px] leading-tight {{ $mitiga ? 'text-indigo-800' : 'text-gray-500' }}">
            @if($control->riesgos_count === 0)
                Sin riesgos asociados
            @elseif($mitiga)
                Descuenta en <a href="#riesgos" class="font-extrabold hover:underline">{{ $control->riesgos_count }} {{ $control->riesgos_count === 1 ? 'riesgo' : 'riesgos' }}</a>
            @else
                No descuenta hasta<br>que se apruebe
            @endif
        </p>
    </div>
    <p class="mx-4 mt-2 text-[11px] text-gray-400">Es el valor con el que se asocia a un riesgo; cada riesgo puede ajustarlo.</p>

    <dl class="mx-4 mt-3 mb-4 text-sm divide-y divide-gray-100">
        <div class="flex items-center justify-between py-1.5">
            <dt class="text-gray-500">Estado</dt>
            <dd><x-auditoria.estado-punto :estado="$control->estado" /></dd>
        </div>
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Área</dt>
            <dd class="text-gray-800 truncate">{{ $control->area->nombre ?? '—' }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Registrado por</dt>
            <dd class="text-gray-800 truncate">{{ $control->user->name ?? '—' }}</dd>
        </div>
        <div class="flex items-center justify-between py-1.5">
            <dt class="text-gray-500">Creado</dt>
            <dd class="text-gray-800 tabular-nums">{{ $control->created_at->format('d/m/Y') }}</dd>
        </div>
    </dl>
</section>
