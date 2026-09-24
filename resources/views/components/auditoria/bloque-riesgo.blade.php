{{--
    Contenedor común de los bloques de riesgo/show (Ficha, Objetivos, Controles,
    Planes, Gerencias). Arriba lo vigente (slot por defecto); abajo, aparte y en
    ámbar punteado, lo que está en propuesta y todavía no forma parte del riesgo
    (slot `propuestas`). Mientras se edita, el borde y una franja explican qué va
    a pasar al guardar según `modo` ('directo' | 'propuesta' | 'doble').
    Slots: accion (botón del encabezado), pie (barra de edición), propuestas.
--}}
@props([
    'ancla',
    'titulo',
    'contador' => null,
    'subtitulo' => null,
    'modo' => null,
    'gerencias' => '',
    'editando' => false,
    'hayPropuestas' => false,
    'compacto' => false,
])
@php
    $borde = match (true) {
        $editando && $modo === 'directo' => 'border-indigo-300 ring-4 ring-indigo-50',
        $editando => 'border-amber-300 ring-4 ring-amber-50',
        default => 'border-gray-200',
    };
    $pad = $compacto ? 'px-4' : 'px-5';
@endphp
<section id="{{ $ancla }}" @if($editando) data-editando @endif class="scroll-mt-24 bg-white rounded-2xl border shadow-sm overflow-hidden transition-shadow {{ $borde }}">

    <header class="{{ $pad }} pt-4 pb-3 flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <h2 class="text-[13px] font-extrabold text-gray-900 tracking-tight">{{ $titulo }}</h2>
                @if(!is_null($contador))
                    <span class="text-[11px] font-bold text-gray-500 bg-gray-100 rounded-full px-2 py-0.5 tabular-nums">{{ $contador }}</span>
                @endif
            </div>
            @if($subtitulo)
                <p class="text-xs text-gray-500 mt-0.5">{{ $subtitulo }}</p>
            @endif
        </div>
        @isset($accion)
            <div class="shrink-0 flex items-center gap-2">{{ $accion }}</div>
        @endisset
    </header>

    @if($editando && $modo)
        <div class="{{ $compacto ? 'mx-4' : 'mx-5' }} mb-3 flex items-start gap-2 rounded-xl px-3 py-2 text-xs leading-5
                    {{ $modo === 'directo' ? 'bg-indigo-50 text-indigo-800' : 'bg-amber-50 text-amber-900' }}">
            <svg class="h-4 w-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>
                @switch($modo)
                    @case('directo')
                        Los cambios se aplican al riesgo en cuanto guardes.
                        @break
                    @case('doble')
                        <strong>Riesgo compartido.</strong> Vas a armar una propuesta: lo vigente no cambia hasta que
                        voten a favor {{ $gerencias }}. Tu gerencia vota a favor al enviarla.
                        @break
                    @default
                        Vas a armar una <strong>propuesta</strong>: lo vigente no cambia hasta que se valide.
                @endswitch
            </span>
        </div>
    @endif

    <div class="{{ $pad }} pb-4 space-y-2">
        {{ $slot }}
    </div>

    {{-- Se decide con los props y no mirando si el slot vino vacío: Livewire mete
         marcadores <!--[if BLOCK]--> dentro de @if/@foreach y el slot nunca queda vacío. --}}
    @if($editando && isset($pie))
        <div class="{{ $pad }} py-3 border-t border-gray-100 bg-gray-50/70">{{ $pie }}</div>
    @endif

    @isset($propuestas)
        @if($hayPropuestas)
            <div class="border-t-2 border-dashed border-amber-200 bg-[repeating-linear-gradient(135deg,rgba(254,243,199,0.35)_0_10px,rgba(255,251,235,0.6)_10px_20px)] {{ $pad }} py-3 space-y-2">
                <p class="flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-widest text-amber-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    En propuesta · todavía no forma parte del riesgo
                </p>
                {{ $propuestas }}
            </div>
        @endif
    @endisset
</section>
