@extends('layouts.auditoria')
@section('title', 'Nuevo Plan de Acción')

@section('content')
<div class="max-w-2xl space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('auditoria.planes.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
            </svg>
        </a>
        <h1 class="text-2xl font-extrabold text-gray-900">Nuevo Plan de Acción</h1>
    </div>

    <form action="{{ route('auditoria.planes.store') }}" method="POST">
        @csrf
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">
                    Código Identificador *
                </label>
                <input type="text" name="codigo" value="{{ old('codigo', $codigoSugerido) }}"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500 @error('codigo') border-red-300 @enderror"
                    placeholder="PA-0001" />
                @error('codigo') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nombre del Plan *</label>
                <input type="text" name="nombre" value="{{ old('nombre') }}"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('nombre') border-red-300 @enderror"
                    placeholder="Ej: Plan de contingencia ante ciberataques..." />
                @error('nombre') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Descripción</label>
                <textarea name="descripcion" rows="3"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Descripción general del plan de acción...">{{ old('descripcion') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Riesgos Asociados</label>
                <div class="space-y-2 max-h-52 overflow-y-auto border border-gray-200 rounded-xl p-3">
                    @forelse ($riesgos as $riesgo)
                        <label class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-orange-50/30 cursor-pointer transition-all">
                            <input type="checkbox" name="riesgo_ids[]" value="{{ $riesgo->id }}"
                                {{ in_array($riesgo->id, old('riesgo_ids', [])) ? 'checked' : '' }}
                                class="w-4 h-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500" />
                            <div class="flex-1 min-w-0 flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700">{{ $riesgo->nombre }}</span>
                                <span class="text-[10px] text-orange-600 font-bold bg-orange-50 border border-orange-100 px-2 py-0.5 rounded-full ml-2 shrink-0">
                                    Total: {{ $riesgo->valor_total }}
                                </span>
                            </div>
                        </label>
                    @empty
                        <p class="text-sm text-gray-400 italic p-2">No hay riesgos disponibles. Crea uno primero.</p>
                    @endforelse
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Área</label>
                    <select name="area_id"
                        class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('area_id') border-red-300 @enderror">
                        <option value="">— Sin área —</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" {{ old('area_id') == $area->id ? 'selected' : '' }}>
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
                            <option value="{{ $usuario->id }}" {{ old('user_id', auth()->id()) == $usuario->id ? 'selected' : '' }}>
                                {{ $usuario->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('user_id') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                <a href="{{ route('auditoria.planes.index') }}"
                   class="px-5 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
                    Cancelar
                </a>
                <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 shadow-sm transition-colors">
                    Crear Plan
                </button>
            </div>

        </div>
    </form>

</div>
@endsection
