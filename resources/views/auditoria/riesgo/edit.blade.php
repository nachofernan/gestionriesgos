@extends('layouts.auditoria')
@section('title', 'Editar Riesgo')

@section('content')
<div class="max-w-5xl space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('auditoria.riesgos.show', $riesgo) }}" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
            </svg>
        </a>
        <h1 class="text-2xl font-extrabold text-gray-900">Editar Riesgo</h1>
    </div>

    <div class="flex gap-6 items-start">
    <form action="{{ route('auditoria.riesgos.update', $riesgo) }}" method="POST" class="flex-1 min-w-0">
        @csrf @method('PATCH')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nombre *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $riesgo->nombre) }}"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('nombre') border-red-300 @enderror" />
                @error('nombre') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Descripción</label>
                <textarea name="descripcion" rows="3"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('descripcion', $riesgo->descripcion) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Impacto (0–10)</label>
                    <div class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm bg-gray-50 text-gray-700 font-semibold">{{ $riesgo->impacto }}</div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Probabilidad (0–10)</label>
                    <div class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm bg-gray-50 text-gray-700 font-semibold">{{ $riesgo->probabilidad }}</div>
                </div>
            </div>
            <a href="{{ route('auditoria.riesgos.recalcular', $riesgo) }}"
               class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 hover:text-indigo-700 -mt-3">
                Recalcular impacto y probabilidad (vuelve a pasar el wizard) →
            </a>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Tipo de Riesgo *</label>
                <select name="tipo_riesgo_id"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('tipo_riesgo_id') border-red-300 @enderror">
                    <option value="">— Seleccionar categoría —</option>
                    @foreach ($tiposRiesgo as $tipo)
                        <option value="{{ $tipo->id }}" {{ old('tipo_riesgo_id', $riesgo->tipo_riesgo_id) == $tipo->id ? 'selected' : '' }}>
                            {{ $tipo->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('tipo_riesgo_id') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Respuesta</label>
                <select name="respuesta"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('respuesta') border-red-300 @enderror">
                    <option value="">— Seleccionar respuesta —</option>
                    @foreach (\App\Enums\Auditoria\RespuestaRiesgo::cases() as $opcion)
                        <option value="{{ $opcion->value }}" {{ old('respuesta', $riesgo->respuesta?->value) === $opcion->value ? 'selected' : '' }}>
                            {{ $opcion->label() }}
                        </option>
                    @endforeach
                </select>
                @error('respuesta') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
            </div>

            <x-auditoria.criticidad-checkbox
                :impacto="old('impacto', $riesgo->impacto)"
                :probabilidad="old('probabilidad', $riesgo->probabilidad)"
                :checked="old('mayor_criticidad', $riesgo->mayor_criticidad)"
            />

            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Área</label>
                    <select name="area_id"
                        class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('area_id') border-red-300 @enderror">
                        <option value="">— Sin área —</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" {{ old('area_id', $riesgo->area_id) == $area->id ? 'selected' : '' }}>
                                {{ $area->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('area_id') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Responsable</label>
                    <select name="user_id"
                        class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('user_id') border-red-300 @enderror">
                        <option value="">— Sin asignar —</option>
                        @foreach ($usuarios as $usuario)
                            <option value="{{ $usuario->id }}" {{ old('user_id', $riesgo->user_id) == $usuario->id ? 'selected' : '' }}>
                                {{ $usuario->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('user_id') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                <a href="{{ route('auditoria.riesgos.show', $riesgo) }}"
                   class="px-5 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
                    Cancelar
                </a>
                <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 shadow-sm transition-colors">
                    Guardar Cambios
                </button>
            </div>

        </div>
    </form>

    {{-- Panel de referencia: tipos de riesgo --}}
    <div class="w-72 shrink-0">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-3 sticky top-6">
            <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Referencia de Tipos</h2>
            <div class="space-y-2" id="tipo-riesgo-ref">
                @foreach ($tiposRiesgo as $tipo)
                    <div id="ref-tipo-{{ $tipo->id }}"
                        class="p-3 rounded-lg border border-gray-100 transition-all duration-200">
                        <p class="text-xs font-bold text-gray-800 mb-1">{{ $tipo->nombre }}</p>
                        <p class="text-xs text-gray-500 leading-relaxed">{{ $tipo->descripcion }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    </div>

</div>
@push('scripts')
<script>
    const select = document.querySelector('select[name="tipo_riesgo_id"]');
    function highlight(val) {
        document.querySelectorAll('#tipo-riesgo-ref > div').forEach(el => {
            el.classList.remove('border-indigo-300', 'bg-indigo-50/50', 'shadow-sm');
            el.classList.add('border-gray-100');
        });
        if (val) {
            const active = document.getElementById('ref-tipo-' + val);
            if (active) {
                active.classList.remove('border-gray-100');
                active.classList.add('border-indigo-300', 'bg-indigo-50/50', 'shadow-sm');
                active.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        }
    }
    select.addEventListener('change', e => highlight(e.target.value));
    highlight(select.value);
</script>
@endpush
@endsection
