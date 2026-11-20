{{--
    Tarjeta de un testeo de evidencia (D-040) dentro del modal de actividad (D-056).
    No es una Actualizacion: no tiene ciclo ni acciones, y sus adjuntos van por la
    ruta de testeos. Recibe $x (de $clasificarTesteo) y $t (el Testeo).
--}}
@php
    $enFoco = $focoActividad === $x['clave'];
    $contraparte = $modelType === 'control' ? $t->riesgo : $t->control;
    $rutaContraparte = $modelType === 'control'
        ? route('auditoria.riesgos.show', $t->riesgo_id)
        : route('auditoria.controles.show', $t->control_id);
    $adjuntos = $t->getMedia('adjuntos');
@endphp
<li id="act-det-{{ $x['clave'] }}" wire:key="act-det-{{ $x['clave'] }}" class="relative pl-7 scroll-mt-10">
    <span class="absolute left-0 top-4 w-[15px] h-[15px] rounded-full ring-4 ring-gray-50 {{ $x['punto'] }}"></span>

    <article @class(['bg-white rounded-xl border shadow-sm transition-shadow',
                     'border-indigo-300 ring-2 ring-indigo-200' => $enFoco,
                     'border-gray-200' => !$enFoco])>
        <div class="px-4 pt-3 pb-2 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-bold text-gray-900 leading-snug">{{ $x['mensaje'] }}</p>
                <p class="mt-0.5 text-[11px] text-gray-500">
                    <span class="font-semibold text-gray-700">{{ $t->user?->name ?? '—' }}</span>
                    @if($t->user?->area) <span class="text-gray-400">({{ $t->user->area->nombre }})</span> @endif
                    · <time title="Registrado {{ $t->created_at?->format('d/m/Y H:i:s') }}">testeado el {{ $t->fecha?->format('d/m/Y') }}</time>
                </p>
            </div>
            <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide rounded-md px-2 py-0.5 {{ $x['clase'] }}">{{ $x['resultado'] }}</span>
        </div>

        <div class="px-4 pb-3 space-y-3">
            <div class="rounded-lg border border-gray-200 px-3 py-2 text-xs">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Evidencia de cumplimiento</p>
                @if($t->efectividad_antes === $t->efectividad_despues)
                    <p class="text-gray-700">Confirma <b>{{ $t->efectividad_despues }}%</b></p>
                @else
                    <p class="text-gray-700">
                        <span class="text-red-700/80 line-through decoration-red-300">{{ $t->efectividad_antes }}%</span>
                        → <b class="text-emerald-800">{{ $t->efectividad_despues }}%</b>
                    </p>
                @endif
                <a href="{{ $rutaContraparte }}" class="mt-1 inline-block font-semibold text-indigo-700 hover:text-indigo-900">
                    {{ $modelType === 'control' ? 'Riesgo' : 'Control' }} «{{ $contraparte?->nombre ?? '—' }}» →
                </a>
            </div>

            @if($t->comentario)
                <p class="text-xs text-gray-700 whitespace-pre-line">{{ $t->comentario }}</p>
            @endif

            @if($adjuntos->isNotEmpty())
                <div class="flex flex-wrap gap-1.5">
                    @foreach($adjuntos as $media)
                        <a href="{{ route('auditoria.testeos.adjuntos.download', [$t, $media]) }}"
                           class="inline-flex items-center gap-1.5 text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-2 py-1 hover:bg-gray-100 max-w-full">
                            <svg class="h-3.5 w-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                            <span class="truncate">{{ $media->file_name }}</span>
                            <span class="text-gray-400 shrink-0">{{ $media->human_readable_size }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </article>
</li>
