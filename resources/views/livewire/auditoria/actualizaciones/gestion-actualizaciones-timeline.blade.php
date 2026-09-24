{{--
    Variante 'timeline' del historial, para la columna lateral de riesgo/show.
    El lateral es un resumen mínimo (conteos + últimas entradas en una línea);
    todo el detalle vive en el modal de actividad (partials/actividad-modal), que
    se abre desde "Ver todo" o tocando una entrada.
--}}
@php
    $etiquetasCampos = [
        'nombre' => 'Nombre', 'descripcion' => 'Descripción', 'respuesta' => 'Respuesta', 'fundamento' => 'Fundamento',
        'tipo_riesgo_id' => 'Tipo de riesgo', 'impacto' => 'Impacto', 'probabilidad' => 'Probabilidad',
        'mayor_criticidad' => 'Mayor criticidad', 'codigo' => 'Código',
    ];
    $nombresPartes = ['campos' => 'Datos', 'areas' => 'Gerencias', 'objetivos' => 'Objetivos', 'controles' => 'Controles', 'planesAccion' => 'Planes'];
    $anclas = ['campos' => 'ficha', 'areas' => 'gerencias', 'objetivos' => 'objetivos', 'controles' => 'controles', 'planesAccion' => 'planes'];

    // Clasifica una entrada: qué es, cómo terminó y qué partes tocó. Lo usan el lateral y el modal.
    $clasificar = function ($a) use ($pendientesIds) {
        $data = $a->data ?? [];
        $tipo = $data['tipo'] ?? ($data ? 'legacy' : 'mensaje');
        $estado = $a->estado?->nombre;
        $pendiente = $pendientesIds->contains($a->id);
        $esNota = $a->estado_id === null && empty($data);
        $diff = $data['diff'] ?? [];
        $partes = array_merge(!empty($diff['campos']) ? ['campos'] : [], array_keys($diff['relaciones'] ?? []));

        [$grupo, $punto, $resultado, $clase] = match (true) {
            $esNota => ['nota', 'bg-gray-300', 'Nota', 'text-gray-500 bg-gray-100'],
            $pendiente => ['pendiente', 'bg-amber-400', 'Pendiente', 'text-amber-800 bg-amber-100'],
            $estado === 'borrado' => ['rechazada', 'bg-red-400', $a->rechazado_por_id ? 'Rechazada' : 'Retirada', 'text-red-700 bg-red-100'],
            $tipo === 'creacion' => ['alta', 'bg-gray-500', 'Alta', 'text-gray-700 bg-gray-100'],
            in_array($tipo, ['validacion', 'activacion']) => ['estado', 'bg-blue-500', 'Cambio de estado', 'text-blue-700 bg-blue-100'],
            default => ['aplicada', 'bg-emerald-500', 'Aplicada', 'text-emerald-700 bg-emerald-100'],
        };

        return compact('data', 'tipo', 'estado', 'pendiente', 'esNota', 'diff', 'partes', 'grupo', 'punto', 'resultado', 'clase');
    };

    $clasificadas = $actualizaciones->map(fn ($a) => ['a' => $a] + $clasificar($a));
    $conteo = $clasificadas->countBy('grupo');
    $visibles = 4;
@endphp
{{-- Sin Alpine: un x-data dejaría sin enganchar los wire:click de adentro (ver conversacion-riesgo.blade.php). --}}
<section id="actividad" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

    <header class="px-4 pt-4 pb-2 flex items-center justify-between">
        <button type="button" wire:click="abrirActividad" class="group flex items-center gap-2">
            <h2 class="text-[13px] font-extrabold text-gray-900 tracking-tight group-hover:text-indigo-700">Actividad</h2>
            <span class="text-[11px] font-bold text-gray-500 bg-gray-100 rounded-full px-2 py-0.5 tabular-nums">{{ $actualizaciones->count() }}</span>
        </button>
        @if($actualizaciones->isNotEmpty())
            <button type="button" wire:click="abrirActividad" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800">
                Ver todo →
            </button>
        @endif
    </header>

    @if($actualizaciones->isNotEmpty())
        {{-- Tira de conteos: de un vistazo, cómo está el historial. --}}
        <button type="button" wire:click="abrirActividad" class="mx-4 mb-2 flex w-[calc(100%-2rem)] h-1.5 rounded-full overflow-hidden bg-gray-100" title="Ver detalle">
            @foreach(['aplicada' => 'bg-emerald-500', 'estado' => 'bg-blue-500', 'alta' => 'bg-gray-500', 'pendiente' => 'bg-amber-400', 'rechazada' => 'bg-red-400'] as $g => $color)
                @if($conteo->get($g))
                    <span class="{{ $color }}" style="width: {{ $conteo->get($g) / $actualizaciones->count() * 100 }}%"></span>
                @endif
            @endforeach
        </button>
        <p class="px-4 mb-3 flex flex-wrap gap-x-3 gap-y-0.5 text-[10px] font-semibold text-gray-500">
            @if($conteo->get('pendiente'))<span class="text-amber-700">{{ $conteo->get('pendiente') }} pendiente{{ $conteo->get('pendiente') > 1 ? 's' : '' }}</span>@endif
            @if($conteo->get('aplicada'))<span>{{ $conteo->get('aplicada') }} aplicada{{ $conteo->get('aplicada') > 1 ? 's' : '' }}</span>@endif
            @if($conteo->get('rechazada'))<span>{{ $conteo->get('rechazada') }} rechazada{{ $conteo->get('rechazada') > 1 ? 's' : '' }}/retirada{{ $conteo->get('rechazada') > 1 ? 's' : '' }}</span>@endif
        </p>
    @endif

    <ol class="px-2 pb-2">
        @forelse ($clasificadas->take($visibles) as $x)
            @php $actualizacion = $x['a']; @endphp
            <li wire:key="act-{{ $actualizacion->id }}">
                <button type="button" wire:click="abrirActividad({{ $actualizacion->id }})"
                    class="w-full text-left flex items-start gap-2.5 px-2 py-1.5 rounded-lg hover:bg-gray-50 transition-colors">
                    <span class="mt-1.5 shrink-0 w-2 h-2 rounded-full {{ $x['punto'] }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs font-semibold text-gray-800 truncate">{{ $actualizacion->mensaje }}</span>
                        <span class="block text-[10px] text-gray-400 truncate">
                            {{ $actualizacion->user?->name ?? '—' }} · {{ $actualizacion->created_at?->diffForHumans() }}
                        </span>
                    </span>
                    @if($x['pendiente'])
                        <span class="mt-0.5 shrink-0 text-[9px] font-bold uppercase tracking-wide rounded px-1.5 py-0.5 {{ $x['clase'] }}">{{ $x['resultado'] }}</span>
                    @endif
                </button>
            </li>
        @empty
            <li class="py-6 text-center text-sm text-gray-400 italic">Sin actividad todavía.</li>
        @endforelse
    </ol>

    @if($actualizaciones->count() > $visibles)
        <button type="button" wire:click="abrirActividad"
            class="w-full border-t border-gray-100 py-2 text-[11px] font-bold text-gray-500 hover:text-indigo-700 hover:bg-gray-50">
            +{{ $actualizaciones->count() - $visibles }} más
        </button>
    @endif

    @if($actividadAbierta)
        @include('livewire.auditoria.actualizaciones.partials.actividad-modal')
    @endif
</section>
