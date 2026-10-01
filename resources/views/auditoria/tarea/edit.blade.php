@extends('layouts.auditoria')
@section('title', 'Editar Tarea')

@section('content')
<div class="max-w-2xl space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('auditoria.tareas.show', $tarea) }}" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
            </svg>
        </a>
        <h1 class="text-2xl font-extrabold text-gray-900">Editar Tarea</h1>
    </div>

    <form action="{{ route('auditoria.tareas.update', $tarea) }}" method="POST">
        @csrf @method('PATCH')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nombre *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $tarea->nombre) }}"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('nombre') border-red-300 @enderror" />
                @error('nombre') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Descripción</label>
                <textarea name="descripcion" rows="3"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('descripcion', $tarea->descripcion) }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Fecha límite</label>
                <input type="date" name="fecha" value="{{ old('fecha', $tarea->fecha?->format('Y-m-d') ?? '') }}"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('fecha') border-red-300 @enderror" />
                @error('fecha') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
            </div>

            <div x-data="{ avance: {{ old('porcentaje_avance', $tarea->porcentaje_avance) }} }">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">
                    Porcentaje de Avance (<span x-text="avance + '%'"></span>) *
                </label>
                <input type="range" name="porcentaje_avance" min="0" max="100" step="5"
                    x-model="avance"
                    class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-indigo-600 mb-2" />
                <div class="flex justify-between text-[10px] text-gray-400 font-bold uppercase">
                    <span>Inicio (0%)</span>
                    <span>Completo (100%)</span>
                </div>
                @error('porcentaje_avance') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
            </div>

            @include('auditoria.partials.select-area-responsable', [
                'areaSeleccionada' => old('area_id', $tarea->area_id),
                'responsableSeleccionado' => old('user_id', $tarea->user_id),
            ])

            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                <a href="{{ route('auditoria.tareas.show', $tarea) }}"
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
