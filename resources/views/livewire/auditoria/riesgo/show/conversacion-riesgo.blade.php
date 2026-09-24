{{-- Sin x-data en ningún lado: la app carga dos Alpine (el de app.js y el de Livewire) y
     cualquier wire:* que quede dentro de un x-data no se engancha (el form se manda
     como un GET nativo). Todo lo interactivo acá es wire:*. --}}
<section id="conversacion" class="scroll-mt-24 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Encabezado con pestañas --}}
    <header class="px-5 pt-4 flex items-end justify-between gap-3 border-b border-gray-100">
        <div class="pb-3">
            <h2 class="text-[13px] font-extrabold text-gray-900 tracking-tight">Notas y documentos</h2>
            <p class="text-xs text-gray-500 mt-0.5">Registro de observaciones y documentación de respaldo del riesgo.</p>
        </div>
        <nav class="flex items-center gap-4 text-xs font-bold">
            <button type="button" wire:click="$set('pestana', 'mensajes')"
                @class(['pb-3 -mb-px border-b-2 transition-colors', 'border-indigo-600 text-indigo-700' => $pestana === 'mensajes', 'border-transparent text-gray-400 hover:text-gray-700' => $pestana !== 'mensajes'])>
                Notas <span class="ml-0.5 tabular-nums font-semibold">{{ $notas->count() }}</span>
            </button>
            <button type="button" wire:click="$set('pestana', 'archivos')"
                @class(['pb-3 -mb-px border-b-2 transition-colors', 'border-indigo-600 text-indigo-700' => $pestana === 'archivos', 'border-transparent text-gray-400 hover:text-gray-700' => $pestana !== 'archivos'])>
                Documentos <span class="ml-0.5 tabular-nums font-semibold">{{ $archivosRiesgo->count() }}</span>
            </button>
        </nav>
    </header>

    @if($pestana === 'mensajes')
        {{-- Registrar nota --}}
        @if($puedeEscribir)
            <form wire:submit="enviar" class="px-5 py-4 border-b border-gray-100 bg-gray-50/60 space-y-2">
                <label for="nota-texto" class="block text-[10px] font-bold uppercase tracking-wider text-gray-400">Nueva nota</label>
                {{-- La wire:key cambia con cada nota registrada: el textarea se recrea vacío
                     (Livewire no pisa el valor del campo que tiene el foco). --}}
                <textarea id="nota-texto" wire:key="nota-texto-{{ $enviadas }}" wire:model="mensaje" rows="3"
                    placeholder="Observación, avance, constancia o pedido de información…"
                    wire:keydown.ctrl.enter.prevent="enviar"
                    class="w-full resize-y min-h-[4.5rem] border-gray-200 rounded-lg text-sm leading-relaxed focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                @error('mensaje') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                {{-- Adjuntos elegidos --}}
                @if(!empty($archivos))
                    <ul class="space-y-1">
                        @foreach($archivos as $i => $archivo)
                            <li wire:key="adj-{{ $i }}" class="flex items-center gap-2 text-xs text-gray-700 bg-white border border-gray-200 rounded-lg px-2.5 py-1.5">
                                <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span class="flex-1 truncate">{{ $archivo->getClientOriginalName() }}</span>
                                <button type="button" wire:click="quitarArchivo({{ $i }})" title="Quitar" class="text-gray-400 hover:text-red-500">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Subida en curso --}}
                <div wire:loading.flex wire:target="archivos" class="items-center gap-2 text-[11px] font-semibold text-indigo-700">
                    <x-auditoria.spinner class="h-3.5 w-3.5" />
                    Subiendo documento… esperá a que termine para registrar la nota.
                </div>
                @error('archivos') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                @error('archivos.*') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                <div class="flex items-center justify-between gap-2">
                    <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 hover:text-indigo-700 cursor-pointer"
                           wire:loading.class="pointer-events-none opacity-50" wire:target="archivos,enviar">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                        Adjuntar documento
                        <input type="file" wire:model="archivos" multiple class="hidden">
                    </label>
                    <div class="flex items-center gap-3">
                        <span class="text-[10px] text-gray-400">PDF, Word, Excel o imagen · hasta 10 MB</span>
                        <button type="submit" wire:loading.attr="disabled" wire:target="enviar,archivos"
                            class="inline-flex items-center gap-1.5 h-8 px-4 rounded-lg bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <x-auditoria.spinner class="h-3.5 w-3.5" wire:loading wire:target="enviar" />
                            Registrar nota
                        </button>
                    </div>
                </div>
            </form>
        @endif

        {{-- Registro, la más reciente primero --}}
        @if($notas->isEmpty())
            <div class="py-10 text-center">
                <p class="text-sm text-gray-500">Todavía no hay notas registradas.</p>
            </div>
        @else
            <ol class="divide-y divide-gray-100">
                @foreach($notas as $nota)
                    @php
                        $autor = $nota->user;
                        $rol = match (true) {
                            (bool) $autor?->esComite() => 'Comité',
                            (bool) $autor?->esGerente() => 'Gerente',
                            default => null,
                        };
                    @endphp
                    <li wire:key="nota-{{ $nota->id }}" class="px-5 py-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-sm">
                                <span class="font-bold text-gray-900">{{ $autor?->name ?? '—' }}</span>
                                @if($rol || $autor?->area)
                                    <span class="text-xs text-gray-500">— {{ collect([$rol, $autor?->area?->nombre])->filter()->join(', ') }}</span>
                                @endif
                            </p>
                            <time class="shrink-0 text-[11px] tabular-nums text-gray-400" datetime="{{ $nota->created_at?->toIso8601String() }}">
                                {{ $nota->created_at?->format('d/m/Y · H:i') }}
                            </time>
                        </div>
                        <p class="mt-1.5 text-sm leading-relaxed text-gray-800 whitespace-pre-line">{{ $nota->mensaje }}</p>
                        @if($nota->getMedia('adjuntos')->isNotEmpty())
                            <ul class="mt-2 space-y-1">
                                @foreach($nota->getMedia('adjuntos') as $media)
                                    <li>
                                        <a href="{{ route('auditoria.actualizaciones.adjuntos.download', [$nota, $media]) }}"
                                           class="inline-flex items-center gap-1.5 text-xs text-indigo-700 hover:text-indigo-900 hover:underline">
                                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            {{ $media->file_name }}
                                            <span class="text-gray-400">({{ $media->human_readable_size }})</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    @endif

    @if($pestana === 'archivos')
        @if($archivosRiesgo->isEmpty())
            <div class="py-10 text-center">
                <p class="text-sm text-gray-500">No hay documentos todavía.</p>
                <p class="text-xs text-gray-400 mt-1">Se adjuntan al registrar una nota y quedan todos reunidos acá.</p>
            </div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($archivosRiesgo as $x)
                    @php
                        $media = $x['media'];
                        $ext = strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION));
                        $colorExt = match ($ext) {
                            'pdf' => 'bg-red-50 text-red-700',
                            'doc', 'docx' => 'bg-blue-50 text-blue-700',
                            'xls', 'xlsx' => 'bg-green-50 text-green-700',
                            'jpg', 'jpeg', 'png' => 'bg-violet-50 text-violet-700',
                            default => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <li wire:key="archivo-{{ $media->id }}" class="flex items-center gap-3 px-5 py-2.5 hover:bg-gray-50">
                        <span class="shrink-0 w-10 h-10 rounded-lg inline-flex items-center justify-center text-[10px] font-extrabold uppercase {{ $colorExt }}">{{ $ext ?: '—' }}</span>
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('auditoria.actualizaciones.adjuntos.download', [$x['actualizacion'], $media]) }}"
                               class="block text-sm font-semibold text-gray-800 hover:text-indigo-700 truncate">{{ $media->file_name }}</a>
                            <p class="text-[11px] text-gray-500 truncate">
                                {{ $x['actualizacion']->user?->name ?? '—' }} · {{ $media->created_at?->format('d/m/Y H:i') }} · {{ $media->human_readable_size }}
                                <span class="text-gray-400">· en «{{ \Illuminate\Support\Str::limit($x['actualizacion']->mensaje, 40) }}»</span>
                            </p>
                        </div>
                        <a href="{{ route('auditoria.actualizaciones.adjuntos.download', [$x['actualizacion'], $media]) }}" title="Descargar"
                           class="shrink-0 text-gray-400 hover:text-indigo-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</section>
