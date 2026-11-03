{{--
    Caracterización de un riesgo ambiental (D-043): temporalidad, condición y
    responsabilidad. No mueve el valor. Necesita un x-data con un objeto `caracterizacion`.
    Recibe: $valores (temporalidad/condicion/responsabilidad vigentes, como string).
--}}
@php
    $grupos = [
        'temporalidad' => ['Temporalidad', \App\Enums\Auditoria\Temporalidad::cases()],
        'condicion' => ['Condición', \App\Enums\Auditoria\Condicion::cases()],
        'responsabilidad' => ['Responsabilidad', \App\Enums\Auditoria\Responsabilidad::cases()],
    ];
@endphp
<div class="space-y-4 rounded-xl border border-emerald-100 bg-emerald-50/30 p-4">
    <div>
        <p class="text-xs font-bold text-emerald-800 uppercase tracking-wider">Caracterización ambiental *</p>
        <p class="text-xs text-gray-500 mt-0.5">Da contexto al riesgo; no cambia su valor.</p>
    </div>
    @foreach ($grupos as $campo => [$titulo, $casos])
        <div>
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">{{ $titulo }}</p>
            <div class="grid gap-1.5" style="grid-template-columns: repeat({{ count($casos) }}, minmax(0, 1fr))">
                @foreach ($casos as $caso)
                    <label class="flex items-start gap-2 p-2 rounded-lg border bg-white cursor-pointer transition-all"
                           :class="caracterizacion.{{ $campo }} === '{{ $caso->value }}' ? 'border-emerald-300 bg-emerald-50' : 'border-gray-100 hover:border-emerald-200'"
                           title="{{ $caso->descripcion() }}">
                        <input type="radio" name="{{ $campo }}" value="{{ $caso->value }}"
                            x-model="caracterizacion.{{ $campo }}"
                            @checked(old($campo, $valores[$campo] ?? null) === $caso->value)
                            class="mt-0.5 w-4 h-4 border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                        <span>
                            <span class="block text-sm font-semibold text-gray-700">{{ $caso->label() }}</span>
                            <span class="block text-[11px] leading-4 text-gray-500">{{ $caso->descripcion() }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error($campo) <p class="text-xs text-red-500 mt-1 font-medium">{{ $message }}</p> @enderror
        </div>
    @endforeach
</div>
