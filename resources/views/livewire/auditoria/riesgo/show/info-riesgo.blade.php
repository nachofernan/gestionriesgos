<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Información</h2>

    @if($riesgo->descripcion)
        <p class="text-sm text-gray-700 mb-4">{{ $riesgo->descripcion }}</p>
    @endif

    @php
        $cardMap = [
            'verde'    => ['bg' => 'bg-green-50',  'text' => 'text-green-700',  'sub' => 'text-green-500'],
            'amarillo' => ['bg' => 'bg-yellow-50', 'text' => 'text-yellow-700', 'sub' => 'text-yellow-500'],
            'rojo'     => ['bg' => 'bg-red-50',    'text' => 'text-red-700',    'sub' => 'text-red-500'],
        ];
        $cTotal = $cardMap[$riesgo->clasificacion_total['color']];

        // Mismo criterio de color que usaba el x-data de Alpine (umbrales 9/13),
        // resuelto acá porque el bloque entero ya se re-renderiza vía Livewire.
        $cResidual = match (true) {
            $riesgo->valor_residual <= 9 => $cardMap['verde'],
            $riesgo->valor_residual <= 13 => $cardMap['amarillo'],
            default => $cardMap['rojo'],
        };
    @endphp
    <div class="grid grid-cols-2 gap-3 mb-4">
        <div class="{{ $cTotal['bg'] }} rounded-xl p-3 text-center">
            <div class="text-3xl font-extrabold {{ $cTotal['text'] }}">{{ $riesgo->valor_total }}</div>
            <div class="text-[10px] {{ $cTotal['sub'] }} uppercase font-bold tracking-wider mt-0.5">Valor Total</div>
        </div>
        <div class="{{ $cResidual['bg'] }} rounded-xl p-3 text-center">
            <div class="text-3xl font-extrabold {{ $cResidual['text'] }}">{{ $riesgo->valor_residual }}</div>
            <div class="text-[10px] {{ $cResidual['sub'] }} uppercase font-bold tracking-wider mt-0.5">Residual</div>
        </div>
    </div>

    <dl class="space-y-3 text-sm">
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Estado</dt>
            <dd><x-auditoria.estado-punto :estado="$riesgo->estado" /></dd>
        </div>
        @if($riesgo->codigo)
            <div class="flex justify-between">
                <dt class="text-gray-400 font-medium">Código</dt>
                <dd class="font-mono text-xs font-bold text-gray-600 uppercase">{{ $riesgo->codigo }}</dd>
            </div>
        @endif
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Impacto</dt>
            <dd class="font-bold text-gray-800">{{ $riesgo->impacto }}<span class="text-xs text-gray-400 font-normal">/10</span></dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Probabilidad</dt>
            <dd class="font-bold text-gray-800">{{ $riesgo->probabilidad }}<span class="text-xs text-gray-400 font-normal">/10</span></dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Criticidad</dt>
            <dd>
                @if($riesgo->mayor_criticidad)
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">Mayor criticidad</span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-500">Normal</span>
                @endif
            </dd>
        </div>
        @if($riesgo->respuesta)
            <div class="flex justify-between">
                <dt class="text-gray-400 font-medium">Respuesta</dt>
                <dd class="font-bold text-gray-800">{{ $riesgo->respuesta->label() }}</dd>
            </div>
        @endif
        @if($riesgo->fundamento)
            {{-- Apilado y no en fila: es texto libre y largo. --}}
            <div>
                <dt class="text-gray-400 font-medium mb-1">Fundamento</dt>
                <dd class="text-gray-700 whitespace-pre-line">{{ $riesgo->fundamento }}</dd>
            </div>
        @endif
        @if($riesgo->area)
            <div class="flex justify-between">
                <dt class="text-gray-400 font-medium">Área</dt>
                <dd class="text-gray-700">{{ $riesgo->area->nombre }}</dd>
            </div>
            @if($riesgo->area->gerencia())
                <div class="flex justify-between">
                    <dt class="text-gray-400 font-medium">Gerencia</dt>
                    <dd class="text-gray-700">{{ $riesgo->area->gerencia()->nombre }}</dd>
                </div>
            @endif
        @endif
        @if($riesgo->user)
            <div class="flex justify-between">
                <dt class="text-gray-400 font-medium">Registrado por</dt>
                <dd class="text-gray-700">{{ $riesgo->user->name }}</dd>
            </div>
        @endif
        <div class="flex justify-between">
            <dt class="text-gray-400 font-medium">Fecha de creación</dt>
            <dd class="text-gray-700">{{ $riesgo->created_at->format('d/m/Y') }}</dd>
        </div>
    </dl>
</div>
