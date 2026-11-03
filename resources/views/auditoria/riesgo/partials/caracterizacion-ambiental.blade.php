{{--
    Caracterización de un riesgo ambiental (D-043): temporalidad, condición y
    responsabilidad, una opción de cada una. No mueve el valor. Mismo formato que
    el bloque de dimensiones. Necesita un x-data con un objeto `caracterizacion`.
    Recibe: $valores (temporalidad/condicion/responsabilidad vigentes, como string).
--}}
@php
    $grupos = [
        'temporalidad' => ['Temporalidad', \App\Enums\Auditoria\Temporalidad::cases()],
        'condicion' => ['Condición', \App\Enums\Auditoria\Condicion::cases()],
        'responsabilidad' => ['Responsabilidad', \App\Enums\Auditoria\Responsabilidad::cases()],
    ];
    $conError = $errors->hasAny(array_keys($grupos));
@endphp
<div>
    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Caracterización ambiental *</label>
    <p class="text-xs text-gray-400 mb-2">Elegí una opción de cada grupo. Da contexto al riesgo; no cambia su valor.</p>
    <div class="rounded-xl border divide-y divide-gray-100 {{ $conError ? 'border-red-300' : 'border-gray-200' }}">
        @foreach ($grupos as $campo => [$titulo, $casos])
            <div class="p-3">
                <p class="text-[11px] font-bold uppercase tracking-wider mb-1"
                   :class="caracterizacion.{{ $campo }} ? 'text-gray-400' : 'text-gray-500'">
                    {{ $titulo }}
                    <span x-show="caracterizacion.{{ $campo }}" x-cloak class="text-emerald-600 normal-case tracking-normal font-semibold">✓</span>
                </p>
                <div class="space-y-0.5">
                    @foreach ($casos as $caso)
                        <label class="flex items-start gap-2.5 px-1 py-1 rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="{{ $campo }}" value="{{ $caso->value }}"
                                x-model="caracterizacion.{{ $campo }}"
                                @checked(old($campo, $valores[$campo] ?? null) === $caso->value)
                                class="mt-0.5 w-4 h-4 border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-gray-800">{{ $caso->label() }}</span>
                                <span class="block text-xs text-gray-400 leading-snug line-clamp-1" title="{{ $caso->descripcion() }}">{{ $caso->descripcion() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error($campo) <p class="text-xs text-red-500 mt-1 font-medium">{{ $message }}</p> @enderror
            </div>
        @endforeach
    </div>
</div>
