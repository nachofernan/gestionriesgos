<div>
    @if($total > 0)
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-3">
            <div class="flex items-center gap-2.5">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-60 {{ $resolvibles ? 'animate-ping' : '' }}"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                </span>
                <p class="text-sm text-amber-900">
                    <strong>{{ $total }} {{ $total === 1 ? 'propuesta de cambio pendiente' : 'propuestas de cambio pendientes' }}</strong>
                    @if($resolvibles)
                        · <span class="font-semibold">{{ $resolvibles === 1 ? 'una espera' : $resolvibles.' esperan' }} tu decisión</span>
                    @endif
                </p>
            </div>
            <nav class="flex flex-wrap items-center gap-1.5 sm:ml-auto">
                @foreach($porBloque as $ancla => $info)
                    <a href="#{{ $ancla }}"
                       class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-800 bg-white/80 border border-amber-200 rounded-full px-2.5 py-1 hover:bg-white transition-colors">
                        {{ $info['etiqueta'] }}
                        <span class="tabular-nums text-amber-600">{{ $info['cantidad'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
    @endif
</div>
