@php
    $avance = (int) $tarea->porcentaje_avance;
    $completa = $avance === 100;
    [$tono, $leyenda] = match (true) {
        $completa => ['verde', 'Completada'],
        $tareaVencida => ['rojo', 'Vencida'],
        default => ['ambar', 'En progreso'],
    };
    $paleta = [
        'verde' => ['bg' => 'bg-green-50', 'txt' => 'text-green-700', 'bar' => 'bg-green-500'],
        'rojo' => ['bg' => 'bg-red-50', 'txt' => 'text-red-700', 'bar' => 'bg-red-500'],
        'ambar' => ['bg' => 'bg-amber-50', 'txt' => 'text-amber-800', 'bar' => 'bg-amber-500'],
    ][$tono];
    $plazo = match (true) {
        $dias === null => 'Sin fecha límite',
        $completa => null,
        $dias < 0 => 'Venció hace '.abs($dias).' '.(abs($dias) === 1 ? 'día' : 'días'),
        $dias === 0 => 'Vence hoy',
        default => 'Faltan '.$dias.' '.($dias === 1 ? 'día' : 'días'),
    };
@endphp
<section id="avance" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-4 pt-4 pb-3">
        <h2 class="text-[13px] font-extrabold text-gray-900 tracking-tight">Avance</h2>
    </div>

    {{-- Avance, protagonista --}}
    <div class="mx-4 rounded-xl px-4 py-3 {{ $paleta['bg'] }}">
        <div class="flex items-end justify-between">
            <p class="text-4xl font-black tabular-nums leading-none {{ $paleta['txt'] }}">{{ $avance }}<span class="text-lg font-bold opacity-60">%</span></p>
            <span class="text-[10px] font-extrabold uppercase tracking-wider {{ $paleta['txt'] }}">{{ $leyenda }}</span>
        </div>
        <div class="mt-3 h-2 rounded-full bg-white/80 overflow-hidden">
            <div class="h-full rounded-full {{ $paleta['bar'] }}" style="width: {{ $avance }}%"></div>
        </div>
    </div>
    <p class="mx-4 mt-2 text-[11px] text-gray-400">Cuenta para el avance de sus planes sólo con la tarea aprobada. Un plan aprobado descuenta del residual al llegar al 100%.</p>

    <dl class="mx-4 mt-3 mb-4 text-sm divide-y divide-gray-100">
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Fecha límite</dt>
            <dd class="text-right">
                <span class="font-semibold tabular-nums {{ $tareaVencida ? 'text-red-700' : 'text-gray-800' }}">{{ $tarea->fecha?->format('d/m/Y') ?? '—' }}</span>
                @if($plazo)
                    <span class="block text-[11px] {{ $tareaVencida ? 'text-red-600 font-bold' : 'text-gray-400' }}">{{ $plazo }}</span>
                @endif
            </dd>
        </div>
        <div class="flex items-center justify-between py-1.5">
            <dt class="text-gray-500">Estado</dt>
            <dd><x-auditoria.estado-punto :estado="$tarea->estado" /></dd>
        </div>
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Asignada a</dt>
            <dd class="text-gray-800 truncate">{{ $tarea->user->name ?? '—' }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Área</dt>
            <dd class="text-gray-800 truncate">{{ $tarea->area->nombre ?? '—' }}</dd>
        </div>
        <div class="flex items-center justify-between py-1.5">
            <dt class="text-gray-500">Creada</dt>
            <dd class="text-gray-800 tabular-nums">{{ $tarea->created_at->format('d/m/Y') }}</dd>
        </div>
    </dl>
</section>
