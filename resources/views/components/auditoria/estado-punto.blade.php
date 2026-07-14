{{--
    Indicador de estado liviano: un punto de color, con el nombre del estado
    como texto al lado (por defecto) o como tooltip cuando va solo el punto.
    Pensado para listas y filas de detalle donde el badge con fondo pesa de más.
    Props: $estado (modelo) o $color/$nombre sueltos; $soloPunto; $size ('sm'|'normal').
--}}
@props(['estado' => null, 'color' => null, 'nombre' => null, 'soloPunto' => false, 'size' => 'normal'])
@php
    $c = $color  ?? $estado?->color  ?? 'gray';
    $n = $nombre ?? $estado?->nombre ?? 'borrador';
    $punto = match($c) {
        'blue'   => 'bg-blue-500',
        'green'  => 'bg-green-500',
        'red'    => 'bg-red-500',
        'purple' => 'bg-purple-500',
        default  => 'bg-gray-400',
    };
    $texto = match($c) {
        'blue'   => 'text-blue-700',
        'green'  => 'text-green-700',
        'red'    => 'text-red-700',
        'purple' => 'text-purple-700',
        default  => 'text-gray-600',
    };
    $dot = $size === 'sm' ? 'w-2 h-2' : 'w-2.5 h-2.5';
@endphp
@if($soloPunto)
    <span {{ $attributes->merge(['class' => "inline-block shrink-0 {$dot} rounded-full {$punto}"]) }} title="{{ ucfirst($n) }}"></span>
@else
    <span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 text-sm font-semibold capitalize {$texto}"]) }}>
        <span class="{{ $dot }} rounded-full {{ $punto }}"></span>{{ $n }}
    </span>
@endif
