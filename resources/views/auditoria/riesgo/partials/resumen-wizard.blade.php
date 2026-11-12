{{--
    Resumen en vivo del alta del riesgo, en el panel derecho desde el paso 2: lo cargado en
    cada paso, para no perderlo de vista. Lee el x-data de riesgoWizard (riesgo/create) y no
    escribe nada. Los totales son la misma vista previa del paso 4: el valor lo calcula
    EvaluacionRiesgo en el servidor.
    Lo del paso 4 (área, sedes, fundamento, interno, criticidad) lo toma de leerFormulario(),
    porque esos campos vienen de partials compartidos con la edición, sin x-model.
    Variables: $tiposRiesgo, $dimensiones, $preguntas, $ambiental, $areas, $sedes.
--}}
@php
    $caracterizaciones = [
        'temporalidad' => ['Temporalidad', \App\Enums\Auditoria\Temporalidad::cases()],
        'condicion' => ['Condición', \App\Enums\Auditoria\Condicion::cases()],
        'responsabilidad' => ['Responsabilidad', \App\Enums\Auditoria\Responsabilidad::cases()],
    ];
@endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-4 sticky top-6">
    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Resumen del riesgo</p>

    {{-- Paso 1: datos básicos --}}
    <div class="space-y-2">
        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">1 · Datos básicos</p>
        <p class="text-sm font-bold text-gray-900" x-text="nombre"></p>
        <p class="text-xs text-gray-500 leading-relaxed line-clamp-3" x-show="descripcion.trim() !== ''" x-text="descripcion" :title="descripcion"></p>
        <p class="text-xs text-gray-400 italic" x-show="descripcion.trim() === ''">Sin descripción</p>

        <dl class="text-xs space-y-1.5 pt-1">
            <div class="flex gap-2">
                <dt class="w-24 shrink-0 font-semibold text-gray-400">Tipo</dt>
                <dd class="text-gray-700">
                    @foreach ($tiposRiesgo as $tipo)
                        <span x-show="tipoRiesgoId === '{{ $tipo->id }}'">{{ $tipo->nombre }}</span>
                    @endforeach
                </dd>
            </div>
            <div class="flex gap-2">
                <dt class="w-24 shrink-0 font-semibold text-gray-400">Dimensiones</dt>
                <dd class="flex flex-wrap gap-1">
                    @foreach ($dimensiones as $dimension)
                        <span x-show="dimensiones.includes('{{ $dimension->id }}')"
                              class="inline-flex px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 font-semibold">{{ $dimension->nombre }}</span>
                    @endforeach
                </dd>
            </div>
            <div class="flex gap-2" x-show="dimensiones.length >= 2">
                <dt class="w-24 shrink-0 font-semibold text-gray-400">Fundamento</dt>
                <dd class="text-gray-600 leading-relaxed line-clamp-3" x-text="fundamentoDimension" :title="fundamentoDimension"></dd>
            </div>
            @foreach ($caracterizaciones as $campo => [$titulo, $casos])
                <div class="flex gap-2" x-show="esAmbiental">
                    <dt class="w-24 shrink-0 font-semibold text-gray-400">{{ $titulo }}</dt>
                    <dd class="text-gray-700">
                        @foreach ($casos as $caso)
                            <span x-show="caracterizacion.{{ $campo }} === '{{ $caso->value }}'">{{ $caso->label() }}</span>
                        @endforeach
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>

    {{-- Paso 2: probabilidad --}}
    <div class="space-y-2 pt-3 border-t border-gray-100">
        <div class="flex items-baseline justify-between gap-2">
            <p class="text-[11px] font-bold uppercase tracking-wider"
               :class="paso === 2 ? 'text-indigo-600' : 'text-gray-500'">2 · Probabilidad</p>
            <p class="text-sm font-extrabold text-gray-800 tabular-nums" x-text="probabilidadTotal"></p>
        </div>

        <details class="text-xs" x-show="!esAmbiental">
            <summary class="cursor-pointer font-semibold text-gray-500">Detalle por pregunta</summary>
            <ul class="mt-2 space-y-1.5">
                @foreach ($preguntas['probabilidad'] as $p)
                    <li class="flex justify-between gap-3">
                        <span class="text-gray-500 leading-snug">{{ $p['pregunta'] }}</span>
                        <span class="shrink-0 text-right font-semibold text-gray-700">
                            @foreach ($p['opciones'] as $o)
                                <span x-show="probabilidad[{{ $p['id'] }}] === {{ $o['v'] }}">{{ $o['t'] }} ({{ $o['v'] }})</span>
                            @endforeach
                            <span x-show="probabilidad[{{ $p['id'] }}] === undefined" class="text-gray-400">—</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </details>

        <details class="text-xs" x-show="esAmbiental" x-cloak>
            <summary class="cursor-pointer font-semibold text-gray-500">Detalle por pregunta</summary>
            <dl class="mt-2 space-y-1.5">
            @foreach ($ambiental['probabilidad'] as $eje => $definicion)
                <div>
                    <dt class="text-gray-500">{{ $definicion['titulo'] }}</dt>
                    <dd class="flex justify-between gap-2 font-semibold text-gray-700">
                        <span>
                            @foreach ($definicion['opciones'] as $o)
                                <span x-show="ambiental.{{ $eje }} === {{ $o['v'] }}">{{ $o['t'] }}</span>
                            @endforeach
                            <span x-show="ambiental.{{ $eje }} === null" class="font-normal italic text-gray-400">Sin responder</span>
                        </span>
                        <span class="text-gray-400 tabular-nums" x-text="ambiental.{{ $eje }} ?? ''"></span>
                    </dd>
                </div>
            @endforeach
            </dl>
        </details>
    </div>

    {{-- Paso 3: impacto --}}
    <div class="space-y-2 pt-3 border-t border-gray-100" x-show="paso >= 3" x-cloak>
        <div class="flex items-baseline justify-between gap-2">
            <p class="text-[11px] font-bold uppercase tracking-wider"
               :class="paso === 3 ? 'text-indigo-600' : 'text-gray-500'">3 · Impacto</p>
            <p class="text-sm font-extrabold text-gray-800 tabular-nums" x-text="impactoTotal"></p>
        </div>

        <details class="text-xs" x-show="!esAmbiental">
            <summary class="cursor-pointer font-semibold text-gray-500">Detalle por pregunta</summary>
            <ul class="mt-2 space-y-1.5">
                @foreach ($preguntas['impacto'] as $p)
                    <li class="flex justify-between gap-3">
                        <span class="text-gray-500 leading-snug">{{ $p['pregunta'] }}</span>
                        <span class="shrink-0 font-semibold"
                              :class="impacto[{{ $p['id'] }}] === 1 ? 'text-indigo-700' : 'text-gray-400'"
                              x-text="impacto[{{ $p['id'] }}] === undefined ? '—' : (impacto[{{ $p['id'] }}] === 1 ? 'Sí' : 'No')"></span>
                    </li>
                @endforeach
            </ul>
        </details>

        <details class="text-xs" x-show="esAmbiental" x-cloak>
            <summary class="cursor-pointer font-semibold text-gray-500">Detalle por pregunta</summary>
            <dl class="mt-2 space-y-1.5">
            @foreach ($ambiental['impacto'] as $eje => $definicion)
                <div>
                    <dt class="text-gray-500">{{ $definicion['titulo'] }}</dt>
                    <dd class="flex justify-between gap-2 font-semibold text-gray-700">
                        <span>
                            @foreach ($definicion['opciones'] as $o)
                                <span x-show="ambiental.{{ $eje }} === {{ $o['v'] }}">{{ $o['t'] }}</span>
                            @endforeach
                            <span x-show="ambiental.{{ $eje }} === null" class="font-normal italic text-gray-400">Sin responder</span>
                        </span>
                        <span class="text-gray-400 tabular-nums" x-text="ambiental.{{ $eje }} ?? ''"></span>
                    </dd>
                </div>
            @endforeach
            </dl>
        </details>
    </div>

    {{-- Valor: vista previa, se completa al llegar a la respuesta --}}
    <div class="pt-3 border-t border-gray-100 space-y-2" x-show="paso >= 4" x-cloak>
        <div class="flex items-baseline justify-between gap-2">
            <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-600">Valor total</p>
            <div class="flex items-baseline gap-2">
                <p class="text-lg font-extrabold tabular-nums" :class="clasificacion.texto" x-text="valorTotal"></p>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold"
                      :class="clasificacion.badge" x-text="clasificacion.etiqueta"></span>
            </div>
        </div>
        <div class="flex flex-wrap gap-1.5" x-show="(mayorCriticidad && habilitado) || interno">
            <span x-show="mayorCriticidad && habilitado"
                  class="inline-flex px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs font-bold">Mayor criticidad</span>
            <span x-show="interno"><x-auditoria.interno-badge :activo="true" /></span>
        </div>
        <dl class="text-xs space-y-1.5">
            <div class="flex gap-2">
                <dt class="w-24 shrink-0 font-semibold text-gray-400">Respuesta</dt>
                <dd class="text-gray-700">
                    @foreach (\App\Enums\Auditoria\RespuestaRiesgo::cases() as $opcion)
                        <span x-show="respuesta === '{{ $opcion->value }}'">{{ $opcion->label() }}</span>
                    @endforeach
                    <span x-show="respuesta === ''" class="italic text-gray-400">Sin elegir</span>
                </dd>
            </div>
            <div class="flex gap-2" x-show="fundamento.trim() !== ''">
                <dt class="w-24 shrink-0 font-semibold text-gray-400">Fundamento</dt>
                <dd class="text-gray-600 leading-relaxed line-clamp-3" x-text="fundamento" :title="fundamento"></dd>
            </div>
            <div class="flex gap-2">
                <dt class="w-24 shrink-0 font-semibold text-gray-400">Área</dt>
                <dd class="text-gray-700">
                    @foreach ($areas as $area)
                        <span x-show="areaId === '{{ $area->id }}'">{{ $area->nombre }}</span>
                    @endforeach
                    <span x-show="areaId === ''" class="italic text-gray-400">Sin elegir</span>
                </dd>
            </div>
            <div class="flex gap-2">
                <dt class="w-24 shrink-0 font-semibold text-gray-400">Sedes</dt>
                <dd class="flex flex-wrap gap-1">
                    @foreach ($sedes as $sede)
                        <span x-show="sedesMarcadas.includes('{{ $sede->id }}')"
                              class="inline-flex px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 font-semibold">{{ $sede->nombre }}</span>
                    @endforeach
                    <span x-show="sedesMarcadas.length === 0" class="italic text-gray-400">Sin elegir</span>
                </dd>
            </div>
        </dl>
    </div>
</div>
