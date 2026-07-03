@extends('layouts.auditoria')
@section('title', 'Pendientes')

@php
    $totalValidar = collect($paraValidar)->sum(fn($c) => $c->count()) + $actualizacionesParaValidar->count();
    $totalAprobar = collect($paraAprobar)->sum(fn($c) => $c->count()) + $actualizacionesParaAprobar->count();
@endphp

@section('content')
<div class="space-y-10">

    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Pendientes</h1>
        <p class="text-sm text-gray-500 mt-1">Lo que tenés que validar o aprobar. La lista se actualiza al recargar la página; cada acción sólo afecta a ese elemento.</p>
    </div>

    @if($totalValidar === 0 && $totalAprobar === 0)
        <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400 italic text-sm">
            No tenés nada pendiente por ahora.
        </div>
    @endif

    @if($totalValidar > 0)
        <section class="space-y-4">
            <h2 class="text-sm font-bold text-blue-700 uppercase tracking-wide">Para validar ({{ $totalValidar }})</h2>

            @foreach($tipos as $tipo => $cfg)
                @if(($paraValidar[$tipo] ?? collect())->isNotEmpty())
                    @include('auditoria.pendiente.partials.entidades', ['items' => $paraValidar[$tipo], 'tipo' => $tipo, 'cfg' => $cfg, 'accion' => 'validar'])
                @endif
            @endforeach

            @if($actualizacionesParaValidar->isNotEmpty())
                @include('auditoria.pendiente.partials.actualizaciones', ['items' => $actualizacionesParaValidar, 'accion' => 'validar'])
            @endif
        </section>
    @endif

    @if($totalAprobar > 0)
        <section class="space-y-4">
            <h2 class="text-sm font-bold text-green-700 uppercase tracking-wide">Para aprobar ({{ $totalAprobar }})</h2>

            @foreach($tipos as $tipo => $cfg)
                @if(($paraAprobar[$tipo] ?? collect())->isNotEmpty())
                    @include('auditoria.pendiente.partials.entidades', ['items' => $paraAprobar[$tipo], 'tipo' => $tipo, 'cfg' => $cfg, 'accion' => 'aprobar'])
                @endif
            @endforeach

            @if($actualizacionesParaAprobar->isNotEmpty())
                @include('auditoria.pendiente.partials.actualizaciones', ['items' => $actualizacionesParaAprobar, 'accion' => 'aprobar'])
            @endif
        </section>
    @endif

</div>

@livewire('auditoria.validacion-cascada-modal')
@livewire('auditoria.actualizaciones.accion-actualizacion-modal')
@endsection
