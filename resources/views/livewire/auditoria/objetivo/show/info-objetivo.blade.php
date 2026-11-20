@php
    [$tono, $leyenda] = match (true) {
        $dias === null => ['gris', 'Sin fecha comprometida'],
        $dias < 0 => ['rojo', 'Venció hace '.abs($dias).' '.(abs($dias) === 1 ? 'día' : 'días')],
        $dias === 0 => ['ambar', 'Vence hoy'],
        $dias <= 30 => ['ambar', 'Faltan '.$dias.' '.($dias === 1 ? 'día' : 'días')],
        default => ['indigo', 'Faltan '.$dias.' días'],
    };
    $paleta = [
        'gris' => 'bg-gray-50 text-gray-500',
        'rojo' => 'bg-red-50 text-red-700',
        'ambar' => 'bg-amber-50 text-amber-800',
        'indigo' => 'bg-indigo-50 text-indigo-700',
    ][$tono];
@endphp
<section id="fecha" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-4 pt-4 pb-3">
        <h2 class="text-[13px] font-extrabold text-gray-900 tracking-tight">Fecha objetivo</h2>
    </div>

    {{-- Fecha, protagonista --}}
    <div class="mx-4 rounded-xl px-4 py-3 flex items-end justify-between gap-3 {{ $paleta }}">
        <p class="text-3xl font-black tabular-nums leading-none">
            {{ $objetivo->fecha_objetivo?->format('d/m/Y') ?? 'No aplica' }}
        </p>
        <p class="text-right text-[11px] font-bold leading-tight">{{ $leyenda }}</p>
    </div>

    <dl class="mx-4 mt-3 mb-4 text-sm divide-y divide-gray-100">
        <div class="flex items-center justify-between py-1.5">
            <dt class="text-gray-500">Estado</dt>
            <dd><x-auditoria.estado-punto :estado="$objetivo->estado" /></dd>
        </div>
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Clasificación</dt>
            <dd class="flex gap-1.5 flex-wrap justify-end">
                @if($objetivo->estrategico)
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700">Estratégico</span>
                @endif
                @if($objetivo->es_peis)
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">PEIS</span>
                @elseif($objetivo->peis->isNotEmpty())
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 ring-1 ring-amber-200">Contribuye al PEIS</span>
                @endif
                @if(!$objetivo->estrategico && !$objetivo->es_peis && $objetivo->peis->isEmpty())
                    <span class="text-gray-400">—</span>
                @endif
            </dd>
        </div>
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Área</dt>
            <dd class="text-gray-800 truncate">{{ $objetivo->area->nombre ?? '—' }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Registrado por</dt>
            <dd class="text-gray-800 truncate">{{ $objetivo->user->name ?? '—' }}</dd>
        </div>
        <div class="flex items-center justify-between py-1.5">
            <dt class="text-gray-500">Creado</dt>
            <dd class="text-gray-800 tabular-nums">{{ $objetivo->created_at->format('d/m/Y') }}</dd>
        </div>
    </dl>

    @php
        [$tituloPeis, $listaPeis] = $objetivo->es_peis
            ? ['Le contribuyen', $objetivo->contribuyentes]
            : ['Contribuye al PEIS en', $objetivo->peis];
    @endphp
    @if($listaPeis->isNotEmpty())
        <div class="mx-4 mb-4 rounded-xl bg-amber-50/70 px-3 py-2.5">
            <p class="text-[10px] font-bold uppercase tracking-widest text-amber-700 mb-1.5">{{ $tituloPeis }}</p>
            <ul class="space-y-1.5">
                @foreach($listaPeis as $relacionado)
                    <li class="flex items-start gap-2 text-xs leading-5 text-gray-700">
                        <svg class="h-3.5 w-3.5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <a href="{{ route('auditoria.objetivos.show', $relacionado) }}" class="hover:text-amber-800 hover:underline">
                            {{ $relacionado->nombre }}
                            @if($objetivo->es_peis && $relacionado->area)
                                <span class="text-gray-400">· {{ $relacionado->area->nombre }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
