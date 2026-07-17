{{--
    Grupo de tareas de un mismo tramo de vencimiento, ya ordenadas por fecha.
    Variables esperadas: $items, $titulo, $color, $leyenda, $hoy.
--}}
@php
    // Clases literales (no interpoladas): Tailwind purga lo que no puede leer estático.
    $tituloClase = match($color) {
        'red'    => 'text-red-700',
        'amber'  => 'text-amber-700',
        'green'  => 'text-green-700',
        default  => 'text-gray-500',
    };
    $fechaClase = match($color) {
        'red'    => 'text-red-700',
        'amber'  => 'text-amber-700',
        'green'  => 'text-green-700',
        default  => 'text-gray-400',
    };
    $barraClase = match($color) {
        'red'    => 'bg-red-500',
        'amber'  => 'bg-amber-500',
        'green'  => 'bg-green-500',
        default  => 'bg-gray-400',
    };
@endphp
<section>
    <h2 class="text-sm font-bold uppercase tracking-wide {{ $tituloClase }}">{{ $titulo }} ({{ $items->count() }})</h2>
    <p class="text-xs text-gray-400 mt-0.5 mb-2">{{ $leyenda }}</p>

    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100 overflow-hidden">
        @foreach($items as $tarea)
            <div class="px-4 py-3 flex items-center justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <a href="{{ route('auditoria.tareas.show', $tarea) }}"
                       class="text-sm font-semibold text-gray-800 hover:text-indigo-600 truncate block">
                        {{ $tarea->nombre }}
                    </a>
                    <p class="text-xs text-gray-400 mt-0.5 truncate">
                        @if($tarea->planesAccion->isNotEmpty())
                            {{ $tarea->planesAccion->pluck('nombre')->join(' · ') }}
                        @else
                            Sin plan asociado
                        @endif
                        &middot; {{ $tarea->area?->nombre ?? 'Sin área' }}
                        @if($tarea->user)
                            &middot; {{ $tarea->user->name }}
                        @endif
                    </p>
                </div>

                <div class="shrink-0 flex items-center gap-4">
                    <div class="w-24 hidden sm:block">
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full {{ $barraClase }}" style="width: {{ $tarea->porcentaje_avance }}%"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1 text-right">{{ $tarea->porcentaje_avance }}% avance</p>
                    </div>

                    <div class="w-28 text-right">
                        @if($tarea->fecha)
                            <p class="text-sm font-bold {{ $fechaClase }}">{{ $tarea->fecha->format('d/m/Y') }}</p>
                            <p class="text-xs text-gray-400">
                                @if($tarea->fecha->lt($hoy))
                                    {{ $tarea->fecha->diffInDays($hoy) }} día(s) de atraso
                                @elseif($tarea->fecha->eq($hoy))
                                    Vence hoy
                                @else
                                    En {{ $hoy->diffInDays($tarea->fecha) }} día(s)
                                @endif
                            </p>
                        @else
                            <p class="text-sm font-bold text-gray-400">Sin fecha</p>
                        @endif
                    </div>

                    <x-auditoria.estado-badge :estado="$tarea->estado" size="sm" />
                </div>
            </div>
        @endforeach
    </div>
</section>
