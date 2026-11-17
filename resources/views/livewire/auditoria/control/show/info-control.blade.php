<section id="datos" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-4 pt-4 pb-1">
        <h2 class="text-[13px] font-extrabold text-gray-900 tracking-tight">Datos</h2>
    </div>

    <dl class="mx-4 mt-3 mb-4 text-sm divide-y divide-gray-100">
        <div class="flex items-center justify-between py-1.5">
            <dt class="text-gray-500">Estado</dt>
            <dd class="flex items-center gap-2">
                <x-auditoria.estado-punto :estado="$control->estado" />
            </dd>
        </div>
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Riesgos</dt>
            <dd class="text-gray-800 text-right text-[12px]">
                @if($control->riesgos_count === 0)
                    Sin riesgos asociados
                @else
                    <a href="#riesgos" class="font-bold hover:underline">{{ $control->riesgos_count }} {{ $control->riesgos_count === 1 ? 'riesgo' : 'riesgos' }}</a>
                    · {{ $mitiga ? 'descuenta' : 'no descuenta hasta aprobarse' }}
                @endif
            </dd>
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
