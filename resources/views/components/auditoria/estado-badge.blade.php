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
@endphp
<span class="{{ $sz }} rounded-full font-bold capitalize {{ $clases }}">{{ $n }}</span>
