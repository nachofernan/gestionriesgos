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
        $totalValidar = collect($paraValidar)->sum(fn($c) => $c->count()) + $actualizacionesParaValidar->count();
        $totalAprobar = collect($paraAprobar)->sum(fn($c) => $c->count()) + $actualizacionesParaAprobar->count();

        $labelTipo = fn($actualizacion) => match(get_class($actualizacion->actualizable)) {
            \App\Models\Auditoria\Riesgo::class     => 'Riesgo',
            \App\Models\Auditoria\Control::class    => 'Control',
            \App\Models\Auditoria\Objetivo::class   => 'Objetivo',
            \App\Models\Auditoria\PlanAccion::class => 'Plan de Acción',
            \App\Models\Auditoria\Tarea::class      => 'Tarea',
            default                                  => 'Elemento',
        };
    @endphp

    @if($totalValidar === 0 && $totalAprobar === 0)
        <p class="vacio">No hay nada pendiente por ahora.</p>
    @endif

    @if($totalValidar > 0)
        <div class="section-title">Para validar ({{ $totalValidar }})</div>

        @foreach($tipos as $tipo => $cfg)
            @if(($paraValidar[$tipo] ?? collect())->isNotEmpty())
                <strong>{{ $cfg['label'] }}</strong>
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Área</th>
                            <th>Registrado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($paraValidar[$tipo] as $item)
                            <tr>
                                <td>{{ $item->nombre }}</td>
                                <td>{{ $item->area?->nombre ?? 'Sin área' }}</td>
                                <td>{{ $item->user?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach

        @if($actualizacionesParaValidar->isNotEmpty())
            <strong>Cambios propuestos</strong>
            <table>
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Elemento</th>
                        <th>Mensaje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($actualizacionesParaValidar as $actualizacion)
                        <tr>
                            <td>{{ $labelTipo($actualizacion) }}</td>
                            <td>{{ $actualizacion->actualizable?->nombre ?? '(elemento eliminado)' }}</td>
                            <td>{{ $actualizacion->mensaje }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if($totalAprobar > 0)
        <div class="section-title aprobar">Para aprobar ({{ $totalAprobar }})</div>

        @foreach($tipos as $tipo => $cfg)
            @if(($paraAprobar[$tipo] ?? collect())->isNotEmpty())
                <strong>{{ $cfg['label'] }}</strong>
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Área</th>
                            <th>Registrado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($paraAprobar[$tipo] as $item)
                            <tr>
                                <td>{{ $item->nombre }}</td>
                                <td>{{ $item->area?->nombre ?? 'Sin área' }}</td>
                                <td>{{ $item->user?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach

        @if($actualizacionesParaAprobar->isNotEmpty())
            <strong>Cambios propuestos</strong>
            <table>
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Elemento</th>
                        <th>Mensaje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($actualizacionesParaAprobar as $actualizacion)
                        <tr>
                            <td>{{ $labelTipo($actualizacion) }}</td>
                            <td>{{ $actualizacion->actualizable?->nombre ?? '(elemento eliminado)' }}</td>
                            <td>{{ $actualizacion->mensaje }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    <div class="footer">
        Sistema de Gestión de Auditoría
    </div>
</body>
</html>
