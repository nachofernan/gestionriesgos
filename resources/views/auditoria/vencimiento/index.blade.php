@extends('layouts.auditoria')
@section('title', 'Vencimientos')

@section('content')
<div class="space-y-10">

    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Vencimientos</h1>
        <p class="text-sm text-gray-500 mt-1">
            Tareas comprometidas ordenadas por fecha, primero lo vencido. No se listan las terminadas al 100% ni las que siguen en borrador.
        </p>
    </div>

    @if($vencidas->isNotEmpty())
        @include('auditoria.vencimiento.partials.grupo', [
            'items'   => $vencidas,
            'titulo'  => 'Vencidas',
            'color'   => 'red',
            'leyenda' => 'La fecha ya pasó y la tarea no está terminada.',
        ])
    @endif

    @if($porVencer->isNotEmpty())
        @include('auditoria.vencimiento.partials.grupo', [
            'items'   => $porVencer,
            'titulo'  => 'Por vencer',
            'color'   => 'amber',
            'leyenda' => 'Vencen dentro de los próximos '.$diasPorVencer.' días.',
        ])
    @endif

    @if($enPlazo->isNotEmpty())
        @include('auditoria.vencimiento.partials.grupo', [
            'items'   => $enPlazo,
            'titulo'  => 'En plazo',
            'color'   => 'green',
            'leyenda' => 'Vencen a más de '.$diasPorVencer.' días.',
        ])
    @endif

    @if($sinFecha->isNotEmpty())
        @include('auditoria.vencimiento.partials.grupo', [
            'items'   => $sinFecha,
            'titulo'  => 'Sin vencimiento definido',
            'color'   => 'gray',
            'leyenda' => 'Tareas sin fecha cargada: no hay contra qué medirlas.',
        ])
    @endif

    @if($vencidas->isEmpty() && $porVencer->isEmpty() && $enPlazo->isEmpty() && $sinFecha->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400 italic text-sm">
            No hay tareas con vencimiento para revisar.
        </div>
    @endif

</div>
@endsection
