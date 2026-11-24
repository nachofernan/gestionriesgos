<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pendientes</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #4f46e5; padding-bottom: 5px; margin-bottom: 15px; }
        .subtitulo { text-align: center; color: #777; font-size: 10px; margin-top: -10px; margin-bottom: 15px; }
        .section-title { background-color: #f4f4f4; padding: 5px 10px; font-weight: bold; margin-top: 14px; border-left: 4px solid #4f46e5; }
        .section-title.aprobar { border-left-color: #16a34a; }

        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f9f9f9; font-weight: bold; }

        .subseccion { font-weight: bold; margin: 12px 0 0; }
        .subseccion.baja { color: #be123c; }
        .nota { color: #777; font-size: 9px; }
        .vacio { color: #777; font-style: italic; padding: 8px 0; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 9px; color: #777; border-top: 1px solid #eee; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h2 style="margin: 0;">PENDIENTES</h2>
    </div>
    <p class="subtitulo">Generado por {{ $user->name }} el {{ now()->format('d/m/Y H:i') }} hs</p>

    @php
        $naturalezas = ['alta' => 'Altas', 'cambio' => 'Cambios', 'baja' => 'Bajas'];
        $total = collect($colas)->flatten()->count();
    @endphp

    @if($total === 0)
        <p class="vacio">No hay nada pendiente por ahora.</p>
    @endif

    @foreach($colas as $accion => $porNaturaleza)
        @php $totalCola = collect($porNaturaleza)->flatten()->count(); @endphp
        @if($totalCola > 0)
            <div class="section-title {{ $accion }}">Para {{ $accion }} ({{ $totalCola }})</div>

            @foreach($porNaturaleza as $naturaleza => $claves)
                @if(count($claves))
                    <p class="subseccion {{ $naturaleza }}">{{ $naturalezas[$naturaleza] }} ({{ count($claves) }})</p>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 12%">Tipo</th>
                                <th style="width: 28%">Elemento</th>
                                <th style="width: 18%">Área</th>
                                <th>{{ $naturaleza === 'alta' ? 'Registrado por' : ($naturaleza === 'baja' ? 'Motivo' : 'Cambios') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($claves as $clave)
                                @php $d = $detalles[$clave]; @endphp
                                <tr>
                                    <td>{{ $d['tipo_label'] }}</td>
                                    <td>
                                        {{ $d['nombre'] }}
                                        @if($d['junto_con'])
                                            <br><span class="nota">Junto con la baja de «{{ $d['junto_con'] }}»</span>
                                        @endif
                                        @if($d['revision_inicial'])
                                            <br><span class="nota">Revisión inicial</span>
                                        @endif
                                    </td>
                                    <td>{{ $d['area'] }}</td>
                                    <td>
                                        @switch($naturaleza)
                                            @case('alta') {{ $d['propietario'] ?? '—' }} @break
                                            @case('baja') {{ $d['mensaje'] }} @break
                                            @default
                                                @foreach($d['lineas'] as $linea)
                                                    {{ $linea }}<br>
                                                @endforeach
                                                @if($d['mensaje'])<span class="nota">“{{ $d['mensaje'] }}”</span>@endif
                                        @endswitch
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endforeach
        @endif
    @endforeach

    <div class="footer">
        Sistema de Gestión de Auditoría
    </div>
</body>
</html>
