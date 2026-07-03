@extends('layouts.auditoria')
@section('title', 'Recalcular Riesgo')

@php
    $pasoInicial = $errors->hasAny(['impacto_respuestas', 'impacto_respuestas.*']) ? 2 : 1;
@endphp

@section('content')
<div class="max-w-3xl space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('auditoria.riesgos.edit', $riesgo) }}" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
            </svg>
        </a>
        <h1 class="text-2xl font-extrabold text-gray-900">Recalcular Impacto y Probabilidad</h1>
    </div>

    <form action="{{ route('auditoria.riesgos.recalcular.store', $riesgo) }}" method="POST"
          x-data="recalcularWizard({{ $pasoInicial }})">
        @csrf
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">

            <div class="flex items-center gap-2 pb-2">
                <template x-for="n in 2" :key="n">
                    <div class="flex items-center gap-2 flex-1">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                             :class="paso === n ? 'bg-indigo-600 text-white' : (paso > n ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400')"
                             x-text="n"></div>
                        <div class="h-0.5 flex-1" :class="paso > n ? 'bg-indigo-200' : 'bg-gray-100'" x-show="n < 2"></div>
                    </div>
                </template>
            </div>
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider -mt-3">
                <span x-show="paso === 1">Paso 1 de 2 — Probabilidad</span>
                <span x-show="paso === 2">Paso 2 de 2 — Impacto</span>
            </p>

            {{-- Paso 1: preguntas de probabilidad --}}
            <div x-show="paso === 1" class="space-y-5">
                <p class="text-sm text-gray-500">Estas respuestas determinan la <strong>probabilidad</strong> del riesgo (0 a 10).</p>
                @foreach ($preguntas['probabilidad'] as $p)
                    <div class="border border-gray-100 rounded-xl p-4 @error('probabilidad_respuestas.'.$p['id']) border-red-300 bg-red-50/30 @enderror">
                        <p class="text-sm font-semibold text-gray-700 mb-3">{{ $p['pregunta'] }}</p>
                        <div class="space-y-1.5">
                            @foreach ($p['opciones'] as $o)
                                @php $checked = old('probabilidad_respuestas.'.$p['id']) == $o['v']; @endphp
                                <label class="flex items-center gap-3 p-2 rounded-lg border cursor-pointer transition-all
                                    {{ $checked ? 'border-indigo-200 bg-indigo-50/50' : 'border-gray-100 hover:border-indigo-200 hover:bg-indigo-50/20' }}">
                                    <input type="radio" name="probabilidad_respuestas[{{ $p['id'] }}]" value="{{ $o['v'] }}"
                                        {{ $checked ? 'checked' : '' }}
                                        x-model.number="probabilidad[{{ $p['id'] }}]"
                                        class="w-4 h-4 border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span class="text-sm text-gray-700">{{ $o['t'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @error('probabilidad_respuestas') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
            </div>

            {{-- Paso 2: preguntas de impacto + resumen + mayor criticidad --}}
            <div x-show="paso === 2" class="space-y-5">
                <p class="text-sm text-gray-500">Estas respuestas determinan el <strong>impacto</strong> del riesgo (0 a 10).</p>
                @foreach ($preguntas['impacto'] as $p)
                    <div class="border border-gray-100 rounded-xl p-4 @error('impacto_respuestas.'.$p['id']) border-red-300 bg-red-50/30 @enderror">
                        <p class="text-sm font-semibold text-gray-700 mb-3">{{ $p['pregunta'] }}</p>
                        <div class="space-y-1.5">
                            @foreach ($p['opciones'] as $o)
                                @php $checked = old('impacto_respuestas.'.$p['id']) == $o['v']; @endphp
                                <label class="flex items-center gap-3 p-2 rounded-lg border cursor-pointer transition-all
                                    {{ $checked ? 'border-indigo-200 bg-indigo-50/50' : 'border-gray-100 hover:border-indigo-200 hover:bg-indigo-50/20' }}">
                                    <input type="radio" name="impacto_respuestas[{{ $p['id'] }}]" value="{{ $o['v'] }}"
                                        {{ $checked ? 'checked' : '' }}
                                        x-model.number="impacto[{{ $p['id'] }}]"
                                        class="w-4 h-4 border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span class="text-sm text-gray-700">{{ $o['t'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @error('impacto_respuestas') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror

                <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-xl border border-gray-100">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Probabilidad calculada</p>
                        <p class="text-2xl font-extrabold text-gray-800" x-text="probabilidadTotal"></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Impacto calculado</p>
                        <p class="text-2xl font-extrabold text-gray-800" x-text="impactoTotal"></p>
                    </div>
                </div>

                {{--
                    No se reutiliza <x-auditoria.criticidad-checkbox> acá por el mismo motivo que en
                    create.blade.php: ese componente escucha inputs numéricos hermanos, y acá
                    impacto/probabilidad se calculan de las respuestas del wizard (mismo x-data).
                --}}
                <div class="rounded-xl border transition-colors"
                     :class="habilitado ? 'bg-red-50 border-red-100 p-3' : 'bg-gray-50 border-gray-100 p-3'"
                     x-effect="if (!habilitado && $refs.mayorCriticidad) $refs.mayorCriticidad.checked = false">
                    <input type="hidden" name="mayor_criticidad" value="0" />
                    <input type="checkbox" name="mayor_criticidad" id="mayor_criticidad" value="1"
                        x-ref="mayorCriticidad"
                        {{ old('mayor_criticidad', $riesgo->mayor_criticidad) ? 'checked' : '' }}
                        :disabled="!habilitado"
                        class="w-4 h-4 rounded border-gray-300 text-red-600 focus:ring-red-500 disabled:opacity-40 disabled:cursor-not-allowed" />
                    <label for="mayor_criticidad"
                        :class="habilitado ? 'text-red-700 cursor-pointer' : 'text-gray-400 cursor-not-allowed'"
                        class="text-sm font-semibold ml-2">
                        Marcar como mayor criticidad
                        <span x-show="!habilitado" class="text-xs font-normal ml-1">(requiere impacto+probabilidad ≥ 14)</span>
                    </label>
                </div>
            </div>

            {{-- Navegación --}}
            <div class="flex justify-between gap-3 pt-2 border-t border-gray-100">
                <div>
                    <a x-show="paso === 1" href="{{ route('auditoria.riesgos.edit', $riesgo) }}"
                       class="px-5 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
                        Cancelar
                    </a>
                    <button type="button" x-show="paso === 2" x-cloak @click="paso = 1"
                        class="px-5 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
                        Atrás
                    </button>
                </div>
                <div>
                    <button type="button" x-show="paso === 1" x-cloak @click="siguiente()" :disabled="!pasoCompleto"
                        :class="pasoCompleto ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-indigo-300 cursor-not-allowed'"
                        class="px-6 py-2.5 text-white text-sm font-bold rounded-xl shadow-sm transition-colors">
                        Siguiente
                    </button>
                    <button type="submit" x-show="paso === 2" x-cloak
                        class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 shadow-sm transition-colors">
                        Recalcular
                    </button>
                </div>
            </div>

        </div>
    </form>
</div>
@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('recalcularWizard', (pasoInicial) => ({
            paso: pasoInicial,
            probabilidad: @json(collect(old('probabilidad_respuestas', []))->map(fn($v) => (int) $v)),
            impacto: @json(collect(old('impacto_respuestas', []))->map(fn($v) => (int) $v)),

            get probabilidadTotal() {
                return Object.values(this.probabilidad).reduce((a, b) => a + (Number(b) || 0), 0);
            },
            get impactoTotal() {
                return Object.values(this.impacto).reduce((a, b) => a + (Number(b) || 0), 0);
            },
            get habilitado() {
                return (this.probabilidadTotal + this.impactoTotal) >= 14;
            },
            get pasoCompleto() {
                return Object.keys(this.probabilidad).length === {{ count($preguntas['probabilidad']) }};
            },
            siguiente() {
                if (this.pasoCompleto) this.paso = 2;
            },
        }));
    });
</script>
@endpush
@endsection
