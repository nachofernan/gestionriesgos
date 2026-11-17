{{-- Badge de la clasificación de un control (D-051): deficiente / moderado / suficiente, de Control::clasificacion(). --}}
@props(['control', 'size' => 'sm'])
@php
    $clasificacion = $control->clasificacion();
    $colores = [
        'rojo' => 'bg-red-100 text-red-800',
        'amarillo' => 'bg-amber-100 text-amber-800',
        'verde' => 'bg-green-100 text-green-800',
    ];
    $tamano = $size === 'xs' ? 'text-[10px] px-1.5 py-0.5' : 'text-[11px] px-2 py-0.5';
@endphp
<span {{ $attributes->merge(['class' => "inline-block rounded-full font-bold whitespace-nowrap {$tamano} ".$colores[$clasificacion['color']]]) }}
      title="Clasificación del control según su cuestionario">{{ ucfirst($clasificacion['etiqueta']) }}</span>
