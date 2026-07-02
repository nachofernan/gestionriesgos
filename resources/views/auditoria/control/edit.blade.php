@extends('layouts.auditoria')
@section('title', 'Editar Control')

@section('content')
<div class="max-w-2xl space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('auditoria.controles.show', $control) }}" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
            </svg>
        </a>
        <h1 class="text-2xl font-extrabold text-gray-900">Editar Control</h1>
    </div>

    <form action="{{ route('auditoria.controles.update', $control) }}" method="POST">
        @csrf @method('PATCH')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nombre *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $control->nombre) }}"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('nombre') border-red-300 @enderror" />
                @error('nombre') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Descripción</label>
                <textarea name="descripcion" rows="3"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('descripcion', $control->descripcion) }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">
                    Valor de Mitigación por defecto (1–10) *
                </label>
                <input type="number" name="mitigacion_default" value="{{ old('mitigacion_default', $control->mitigacion_default) }}" min="1" max="10"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('mitigacion_default') border-red-300 @enderror" />
                @error('mitigacion_default') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Área</label>
                    <select name="area_id"
                        class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('area_id') border-red-300 @enderror">
                        <option value="">— Sin área —</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" {{ old('area_id', $control->area_id) == $area->id ? 'selected' : '' }}>
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
                            <option value="{{ $usuario->id }}" {{ old('user_id', $control->user_id) == $usuario->id ? 'selected' : '' }}>
                                {{ $usuario->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('user_id') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                <a href="{{ route('auditoria.controles.show', $control) }}"
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

</div>
@endsection
