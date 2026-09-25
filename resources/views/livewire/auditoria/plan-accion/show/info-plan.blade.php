@php
    // Avance sólo sobre tareas aprobadas (ver PlanAccion::getAvanceAttribute).
    $avance = $planAccion->avance;
    $aprobado = $planAccion->estado?->nombre === 'aprobado';
    $completo = $planAccion->estaCompleto();
    $vigentes = $planAccion->tareas->reject(fn ($t) => $t->estado?->nombre === 'borrado');
    $aprobadas = $vigentes->filter(fn ($t) => $t->estado?->nombre === 'aprobado')->count();
    [$bg, $txt, $bar] = match (true) {
        $avance === null => ['bg-gray-50', 'text-gray-500', 'bg-gray-300'],
        $completo => ['bg-green-50', 'text-green-700', 'bg-green-500'],
        $planAccion->esta_vencido => ['bg-red-50', 'text-red-700', 'bg-red-500'],
        default => ['bg-amber-50', 'text-amber-800', 'bg-amber-500'],
    };
    $mitigacion = match (true) {
        $planAccion->riesgos_count === 0 => 'Sin riesgos asociados',
        $aprobado && $completo => 'Descuenta del residual en '.$planAccion->riesgos_count.' '.($planAccion->riesgos_count === 1 ? 'riesgo' : 'riesgos'),
        $completo => 'Completo: descuenta cuando se apruebe el plan',
        default => 'Descuenta del residual al llegar al 100%',
    };
@endphp
<section id="avance" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-4 pt-4 pb-3">
        <h2 class="text-[13px] font-extrabold text-gray-900 tracking-tight">Avance del plan</h2>
    </div>

    {{-- Avance, protagonista --}}
    <div class="mx-4 rounded-xl px-4 py-3 {{ $bg }}">
        <div class="flex items-end justify-between gap-3">
            <p class="text-4xl font-black tabular-nums leading-none {{ $txt }}">
                @if($avance === null) — @else {{ $avance }}<span class="text-lg font-bold opacity-60">%</span> @endif
            </p>
            <p class="text-right text-[11px] leading-tight {{ $txt }}">
                {{ $aprobadas }} de {{ $vigentes->count() }} {{ $vigentes->count() === 1 ? 'tarea aprobada' : 'tareas aprobadas' }}
            </p>
        </div>
        <div class="mt-3 h-2 rounded-full bg-white/80 overflow-hidden">
            <div class="h-full rounded-full {{ $bar }}" style="width: {{ $avance ?? 0 }}%"></div>
        </div>
    </div>
    <a href="#riesgos" class="block mx-4 mt-2 text-[11px] {{ $aprobado && $completo ? 'font-bold text-indigo-700' : 'text-gray-400' }} hover:text-indigo-700">{{ $mitigacion }}</a>

    <dl class="mx-4 mt-3 mb-4 text-sm divide-y divide-gray-100">
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Vencimiento</dt>
            <dd class="text-right">
                @if($planAccion->vencimiento)
                    <span class="font-semibold tabular-nums {{ $planAccion->esta_vencido ? 'text-red-700' : 'text-gray-800' }}">{{ $planAccion->vencimiento->format('d/m/Y') }}</span>
                    <span class="block text-[11px] {{ $planAccion->esta_vencido ? 'font-bold text-red-600' : 'text-gray-400' }}">
                        {{ $planAccion->esta_vencido ? 'Vencido' : 'Tarea pendiente más próxima' }}
                    </span>
                @else
                    <span class="text-gray-400">Sin fecha</span>
                @endif
            </dd>
        </div>
        <div class="flex items-center justify-between py-1.5">
            <dt class="text-gray-500">Estado</dt>
            <dd><x-auditoria.estado-punto :estado="$planAccion->estado" /></dd>
        </div>
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Responsable</dt>
            <dd class="text-gray-800 truncate">{{ $planAccion->user->name ?? '—' }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Área</dt>
            <dd class="text-gray-800 truncate">{{ $planAccion->area->nombre ?? '—' }}</dd>
        </div>
        <div class="flex items-center justify-between py-1.5">
            <dt class="text-gray-500">Creado</dt>
            <dd class="text-gray-800 tabular-nums">{{ $planAccion->created_at->format('d/m/Y') }}</dd>
        </div>
    </dl>
</section>
