@extends('layouts.auditoria')
@section('title', 'Nuevo Objetivo')

@section('content')
<div class="max-w-2xl space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('auditoria.objetivos.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
            </svg>
        </a>
        <h1 class="text-2xl font-extrabold text-gray-900">Nuevo Objetivo</h1>
    </div>

    <form action="{{ route('auditoria.objetivos.store') }}" method="POST">
        @csrf
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nombre *</label>
                <input type="text" name="nombre" value="{{ old('nombre') }}"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('nombre') border-red-300 @enderror"
                    placeholder="Ej: Garantizar la continuidad operativa..." />
                @error('nombre') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Descripción</label>
                <textarea name="descripcion" rows="3"
                    class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Descripción detallada del objetivo...">{{ old('descripcion') }}</textarea>
            </div>

            <div x-data="{ noAplica: {{ old('fecha_objetivo') ? 'false' : 'false' }} }">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Fecha Objetivo</label>
                <div class="flex items-center gap-3 mb-2">
                    <input type="date" name="fecha_objetivo" value="{{ old('fecha_objetivo') }}"
                        :disabled="noAplica"
                        class="flex-1 border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400 @error('fecha_objetivo') border-red-300 @enderror" />
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-500 whitespace-nowrap">
                        <input type="checkbox" x-model="noAplica"
                            class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        No aplica
                    </label>
                </div>
                @error('fecha_objetivo') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
            </div>

            <div x-data="{ peis: {{ old('peis') ? 'true' : 'false' }} }">
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 cursor-pointer p-3 rounded-xl border border-indigo-100 bg-indigo-50/50 flex-1">
                        <input type="hidden" name="estrategico" value="0" />
                        <input type="checkbox" name="estrategico" value="1"
                            {{ old('estrategico') ? 'checked' : '' }}
                            class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm font-semibold text-indigo-700">Estratégico</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer p-3 rounded-xl border border-amber-100 bg-amber-50/50 flex-1">
                        <input type="hidden" name="peis" value="0" />
                        <input type="checkbox" name="peis" value="1" x-model="peis"
                            class="w-4 h-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500" />
                        <span class="text-sm font-semibold text-amber-700">PEIS</span>
                    </label>
                </div>

                <div x-show="peis" x-cloak
                     class="mt-3 p-4 rounded-xl border border-amber-100 bg-amber-50/30 space-y-2.5">
                    <p class="text-xs font-bold text-amber-700 uppercase tracking-wider">
                        Ítems PEIS <span class="text-amber-500 font-normal normal-case">— seleccioná al menos uno *</span>
                    </p>
                    @foreach ($peisItems as $item)
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" name="peis_items[]" value="{{ $item->id }}"
                                {{ collect(old('peis_items'))->contains($item->id) ? 'checked' : '' }}
                                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500" />
                            <span class="text-sm">
                                <span class="font-semibold text-gray-700">{{ $item->nombre }}</span>
                                <span class="text-gray-400"> — {{ $item->descripcion }}</span>
                            </span>
                        </label>
                    @endforeach
                    @error('peis_items') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
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
                <a href="{{ route('auditoria.objetivos.index') }}"
                   class="px-5 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
                    Cancelar
                </a>
                <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 shadow-sm transition-colors">
                    Crear Objetivo
                </button>
            </div>

        </div>
    </form>

</div>
@endsection
