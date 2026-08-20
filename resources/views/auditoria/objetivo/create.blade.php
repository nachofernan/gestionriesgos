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
                <div class="space-y-2.5">
                    <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-200 bg-white cursor-pointer hover:border-indigo-200 transition-colors">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-gray-800">Objetivo estratégico</span>
                            <span class="block text-xs text-gray-400 mt-0.5">Marcalo si este objetivo es prioritario dentro de la planificación estratégica.</span>
                        </span>
                        <input type="hidden" name="estrategico" value="0" />
                        <input type="checkbox" name="estrategico" value="1"
                            {{ old('estrategico') ? 'checked' : '' }}
                            class="sr-only peer" />
                        <span class="relative w-11 h-6 shrink-0 bg-gray-200 rounded-full peer-checked:bg-indigo-600 transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-transform peer-checked:after:translate-x-5"></span>
                    </label>
                    <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-200 bg-white cursor-pointer hover:border-amber-200 transition-colors">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-gray-800">Contribuye al PEIS</span>
                            <span class="block text-xs text-gray-400 mt-0.5">Este objetivo aporta al Plan Estratégico de Integridad Sostenible.</span>
                        </span>
                        <input type="hidden" name="peis" value="0" />
                        <input type="checkbox" name="peis" value="1" x-model="peis"
                            class="sr-only peer" />
                        <span class="relative w-11 h-6 shrink-0 bg-gray-200 rounded-full peer-checked:bg-amber-500 transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-transform peer-checked:after:translate-x-5"></span>
                    </label>
                </div>

                <div x-show="peis" x-cloak
                     class="mt-3 p-4 rounded-2xl border border-amber-100 bg-amber-50/30">
                    <p class="text-xs font-bold text-amber-700 uppercase tracking-wider mb-3">
                        Puntos del PEIS a los que contribuye este objetivo <span class="text-amber-500 font-normal normal-case">— seleccioná al menos uno *</span>
                    </p>
                    <div class="space-y-2">
                        @foreach ($peisItems as $item)
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-amber-100/70 bg-white cursor-pointer transition-colors hover:border-amber-300 hover:bg-amber-50/60 has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50 has-[:checked]:ring-1 has-[:checked]:ring-amber-300">
                                <input type="checkbox" name="peis_items[]" value="{{ $item->id }}"
                                    {{ collect(old('peis_items'))->contains($item->id) ? 'checked' : '' }}
                                    class="mt-0.5 w-4 h-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500 shrink-0" />
                                <span class="text-sm text-gray-700 leading-snug">{{ $item->descripcion }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('peis_items') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
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
