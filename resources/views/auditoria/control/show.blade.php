@extends('layouts.auditoria')
@section('title', $control->nombre)

@section('content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('auditoria.controles.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </a>
            <h1 class="text-2xl font-extrabold text-gray-900">{{ $control->nombre }}</h1>
            <x-auditoria.estado-badge :estado="$control->estado" />
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @include('auditoria.partials.estado-acciones', ['item' => $control, 'routePrefix' => 'controles'])
            @if($control->estado?->nombre === 'borrador' || !$control->estado)
                <a href="{{ route('auditoria.controles.edit', $control) }}"
                   class="px-4 py-2 bg-indigo-50 text-indigo-700 text-sm font-bold rounded-xl hover:bg-indigo-100 transition-colors">
                    Editar
                </a>
            @endif
            <form action="{{ route('auditoria.controles.destroy', $control) }}" method="POST"
                  onsubmit="return confirm('¿Eliminar este control?')">
                @csrf @method('DELETE')
                <button type="submit"
                    class="px-4 py-2 bg-red-50 text-red-700 text-sm font-bold rounded-xl hover:bg-red-100 transition-colors">
                    Eliminar
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <div class="space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Información</h2>

                @if($control->descripcion)
                    <p class="text-sm text-gray-700 mb-4">{{ $control->descripcion }}</p>
                @endif

                <div class="bg-blue-50 rounded-xl p-4 text-center mb-4">
                    <div class="text-4xl font-extrabold text-blue-700">{{ $control->mitigacion_default }}</div>
                    <div class="text-[10px] text-blue-500 uppercase font-bold tracking-wider mt-0.5">Mitigación por defecto</div>
                </div>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-400 font-medium">Estado</dt>
                        <dd><x-auditoria.estado-punto :estado="$control->estado" /></dd>
                    </div>
                    @if($control->area)
                        <div class="flex justify-between">
                            <dt class="text-gray-400 font-medium">Área</dt>
                            <dd class="text-gray-700">{{ $control->area->nombre }}</dd>
                        </div>
                    @endif
                    @if($control->user)
                        <div class="flex justify-between">
                            <dt class="text-gray-400 font-medium">Registrado por</dt>
                            <dd class="text-gray-700">{{ $control->user->name }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-400 font-medium">Fecha de creación</dt>
                        <dd class="text-gray-700">{{ $control->created_at->format('d/m/Y') }}</dd>
                    </div>
                </dl>
            </div>

            <x-auditoria.card-seccion titulo="Riesgos que mitiga" subtitulo="asignado desde cada riesgo">
                    @forelse ($control->riesgos as $riesgo)
                        <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors cursor-pointer"
                             onclick="Livewire.dispatch('ver-riesgo', {id: {{$riesgo->id}}})">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <x-auditoria.estado-punto :estado="$riesgo->estado" soloPunto />
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $riesgo->nombre }}</p>
                                    <p class="text-[11px] text-gray-400 mt-0.5">{{ $riesgo->tipoRiesgo?->nombre ?? '—' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100 px-2 py-0.5 rounded-full">
                                    Mit: {{ $riesgo->pivot->mitigacion ?? $control->mitigacion_default }}
                                </span>
                                <span class="text-[10px] font-bold bg-orange-50 text-orange-700 border border-orange-100 px-2 py-0.5 rounded-full">
                                    Total: {{ $riesgo->valor_total }}
                                </span>
                                <span class="text-[10px] font-bold bg-green-50 text-green-700 border border-green-100 px-2 py-0.5 rounded-full">
                                    Res: {{ $riesgo->valor_residual }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-gray-400 italic text-sm">
                            Este control no está asociado a ningún riesgo todavía.
                        </div>
                    @endforelse
            </x-auditoria.card-seccion>
        </div>

        <div class="space-y-4">
            @livewire('auditoria.actualizaciones.gestion-actualizaciones', ['modelType' => 'control', 'modelId' => $control->id])
        </div>

    </div>

</div>

@livewire('auditoria.validacion-cascada-modal')
@endsection

