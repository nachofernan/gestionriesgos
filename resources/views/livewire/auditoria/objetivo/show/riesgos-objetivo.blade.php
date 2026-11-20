<div>
    <x-auditoria.bloque-riesgo ancla="riesgos" titulo="Riesgos asociados" :contador="$riesgos->count()" sujeto="objetivo"
        :subtitulo="$objetivo->es_peis ? 'Sólo riesgos de Corrupción. Cada cambio queda en el riesgo.' : 'Cada alta o baja es un cambio del riesgo: si no está en borrador, la valida su gerencia.'"
        :hayPropuestas="$propuestas->isNotEmpty()">

        <x-slot:accion>
            @if($editable)
                <button type="button" wire:click="abrirModal"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-indigo-700 bg-white border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Asociar riesgo
                </button>
            @endif
        </x-slot:accion>

        @forelse ($riesgos as $riesgo)
            <div wire:key="riesgo-{{ $riesgo->id }}"
                 class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-gray-100 bg-gray-50/60 cursor-pointer hover:border-indigo-200 hover:bg-white transition-colors"
                 onclick="Livewire.dispatch('ver-riesgo', {id: {{ $riesgo->id }}})">
                <x-auditoria.estado-punto :estado="$riesgo->estado" soloPunto />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $riesgo->nombre }}</p>
                    <p class="text-[11px] text-gray-500 truncate">
                        {{ collect([$riesgo->tipoRiesgo?->nombre, $riesgo->area?->nombre])->filter()->join(' · ') ?: '—' }}
                    </p>
                </div>
                <span class="shrink-0 w-20 text-right text-xs tabular-nums text-gray-500" title="Valor total → residual">
                    {{ $riesgo->valor_total }} → <strong class="text-gray-900">{{ $riesgo->valor_residual }}</strong>
                </span>
                @if($puedeQuitar[$riesgo->id] ?? false)
                    <button type="button" wire:click.stop="quitar({{ $riesgo->id }})" wire:confirm="¿Quitar este objetivo del riesgo «{{ $riesgo->nombre }}»?"
                        title="Quitar" class="text-gray-300 hover:text-red-500 transition-colors shrink-0">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-400 italic py-2">Este objetivo no está asociado a ningún riesgo todavía.</p>
        @endforelse

        @if($error)
            <p class="text-xs text-red-600 font-medium">{{ $error }}</p>
        @endif

        <x-slot:propuestas>
            @foreach($propuestas as $propuesta)
                @php
                    $diff = $propuesta->data['diff']['relaciones']['objetivos'] ?? [];
                    $agrega = collect($diff['agrega'] ?? [])->contains(fn ($i) => (int) $i['id'] === $objetivo->id);
                @endphp
                <div wire:key="propuesta-{{ $propuesta->id }}" class="flex items-center gap-3 px-3 py-2 rounded-xl border border-dashed border-amber-300 bg-amber-50/40">
                    <span class="text-[10px] font-bold uppercase tracking-wider {{ $agrega ? 'text-emerald-700' : 'text-rose-700' }}">{{ $agrega ? 'Agregar' : 'Quitar' }}</span>
                    <a href="{{ route('auditoria.riesgos.show', $propuesta->actualizable_id) }}#objetivos"
                       class="flex-1 min-w-0 text-sm font-semibold text-gray-800 truncate hover:text-indigo-700 hover:underline">
                        {{ $propuesta->actualizable->nombre }}
                    </a>
                    <span class="shrink-0 text-[11px] text-gray-500">
                        {{ $propuesta->user?->name }} · {{ $propuesta->estado?->nombre === 'validado' ? 'espera al comité' : 'espera validación' }}
                    </span>
                </div>
            @endforeach
        </x-slot:propuestas>
    </x-auditoria.bloque-riesgo>

    @if($modalAbierto)
        <x-auditoria.modal-buscar titulo="Asociar riesgo" placeholder="Nombre del riesgo…" :areas="$areasModal" :estados="$estadosModal">
            @forelse ($resultados as $riesgo)
                <button type="button" wire:click="agregar({{ $riesgo->id }})"
                    class="w-full text-left px-4 py-3 rounded-xl hover:bg-indigo-50 transition-colors border border-gray-100 hover:border-indigo-200">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-sm text-gray-800 truncate">{{ $riesgo->nombre }}</span>
                        <x-auditoria.estado-badge :estado="$riesgo->estado" size="xs" />
                    </div>
                    <p class="mt-0.5 text-[11px] text-gray-500 truncate">
                        {{ collect([$riesgo->tipoRiesgo?->nombre, $riesgo->area?->nombre])->filter()->join(' · ') }}
                    </p>
                </button>
            @empty
                <p class="text-sm text-gray-400 italic px-4 py-3">Sin resultados entre los riesgos que gestionás.</p>
            @endforelse
        </x-auditoria.modal-buscar>
    @endif

    @include('auditoria.partials.elegir-rol-modal')
</div>
