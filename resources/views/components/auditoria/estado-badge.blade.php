@props(['estado' => null, 'color' => null, 'nombre' => null, 'size' => 'normal'])
@php
    $c  = $color  ?? $estado?->color  ?? 'gray';
    $n  = $nombre ?? $estado?->nombre ?? 'borrador';
    $sz = match($size) {
        'xs'    => 'px-1.5 py-0.5 text-[9px]',
        'sm'    => 'px-2 py-0.5 text-[10px]',
        default => 'px-2.5 py-0.5 text-xs',
    };
    $clases = match($c) {
        'blue'  => 'bg-blue-100 text-blue-700',
        'green' => 'bg-green-100 text-green-700',
        'red'   => 'bg-red-100 text-red-700',
        default => 'bg-gray-100 text-gray-600',
    };
    // El punto refuerza que es un indicador de estado (info), no un botón.
    $punto = match($c) {
        'blue'  => 'bg-blue-500',
        'green' => 'bg-green-500',
        'red'   => 'bg-red-500',
        default => 'bg-gray-400',
    };
@endphp
<span class="inline-flex items-center gap-1.5 {{ $sz }} rounded-full font-bold capitalize {{ $clases }}">
    <span class="w-1.5 h-1.5 rounded-full {{ $punto }}"></span>{{ $n }}
</span>
