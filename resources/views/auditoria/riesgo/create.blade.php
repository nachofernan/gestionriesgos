@extends('layouts.auditoria')
@section('title', 'Nuevo Riesgo')

@php
    // Arranca el wizard en el primer paso que tenga errores de validación,
    // para que no queden escondidos en un paso que Alpine no muestra de entrada.
    $pasoInicial = 1;
    if ($errors->hasAny(['nombre', 'descripcion', 'tipo_riesgo_id', 'dimensiones', 'dimensiones.*', 'fundamento_dimension', 'temporalidad', 'condicion', 'responsabilidad'])) {
        $pasoInicial = 1;
    } elseif ($errors->hasAny(['probabilidad_respuestas', 'probabilidad_respuestas.*', 'ambiental', 'ambiental.control', 'ambiental.frecuencia'])) {
        $pasoInicial = 2;
    } elseif ($errors->hasAny(['impacto_respuestas', 'impacto_respuestas.*', 'ambiental.medio', 'ambiental.naturaleza', 'ambiental.magnitud', 'ambiental.materialidad_impacto.*', 'ambiental.materialidad_financiera.*'])) {
        $pasoInicial = 3;
    } elseif ($errors->hasAny(['respuesta', 'fundamento', 'area_id', 'sedes', 'sedes.*'])) {
        $pasoInicial = 4;
    }
@endphp

@section('content')
<div class="space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('auditoria.riesgos.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
            </svg>
        </a>
        <h1 class="text-2xl font-extrabold text-gray-900">Nuevo Riesgo</h1>
    </div>

    <div class="grid grid-cols-2 gap-6 items-start"
         x-data="riesgoWizard({{ $pasoInicial }})">
    <form action="{{ route('auditoria.riesgos.store') }}" method="POST" class="flex-1 min-w-0">
        @csrf
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">

            {{-- Indicador de pasos --}}
            <div class="flex items-center gap-2 pb-2">
                <template x-for="n in 4" :key="n">
                    <div class="flex items-center gap-2 flex-1">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                             :class="paso === n ? 'bg-indigo-600 text-white' : (paso > n ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400')"
                             x-text="n"></div>
                        <div class="h-0.5 flex-1" :class="paso > n ? 'bg-indigo-200' : 'bg-gray-100'" x-show="n < 4"></div>
                    </div>
                </template>
            </div>
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider -mt-3">
                <span x-show="paso === 1">Paso 1 de 4 — Datos básicos</span>
                <span x-show="paso === 2">Paso 2 de 4 — Probabilidad</span>
                <span x-show="paso === 3">Paso 3 de 4 — Impacto</span>
                <span x-show="paso === 4">Paso 4 de 4 — Respuesta y área</span>
            </p>

            {{-- Paso 1: datos básicos --}}
            <div x-show="paso === 1" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nombre *</label>
                    <input type="text" name="nombre" value="{{ old('nombre') }}" x-model="nombre"
                        class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('nombre') border-red-300 @enderror"
                        placeholder="Ej: Fuga de datos sensibles..." />
                    @error('nombre') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Descripción</label>
                    <textarea name="descripcion" rows="3"
                        class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Descripción detallada del riesgo...">{{ old('descripcion') }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Tipo de Riesgo *</label>
                    <select name="tipo_riesgo_id" id="tipo_riesgo_id" x-model="tipoRiesgoId"
                        class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('tipo_riesgo_id') border-red-300 @enderror">
                        <option value="">— Seleccionar categoría —</option>
                        @foreach ($tiposRiesgo as $tipo)
                            <option value="{{ $tipo->id }}" {{ old('tipo_riesgo_id') == $tipo->id ? 'selected' : '' }}>
                                {{ $tipo->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('tipo_riesgo_id') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Los riesgos ambientales se evalúan con su propia matriz (D-043). --}}
                <div x-show="esAmbiental" x-cloak>
                    @include('auditoria.riesgo.partials.caracterizacion-ambiental', ['valores' => []])
                </div>

                @include('auditoria.partials.dimensiones-checkboxes', ['dimensiones' => $dimensiones, 'seleccionadas' => [], 'fundamento' => null, 'enWizard' => true])
            </div>

            {{-- Paso 2: preguntas de probabilidad --}}
            <div x-show="paso === 2 && !esAmbiental" class="space-y-5">
                @include('auditoria.riesgo.partials.wizard-clasico', ['eje' => 'probabilidad', 'preguntas' => $preguntas['probabilidad'], 'valores' => []])
            </div>
            <div x-show="paso === 2 && esAmbiental" x-cloak class="space-y-5">
                @include('auditoria.riesgo.partials.wizard-ambiental', ['seccion' => 'probabilidad', 'valores' => []])
            </div>

            {{-- Paso 3: preguntas de impacto --}}
            <div x-show="paso === 3 && !esAmbiental" class="space-y-5">
                @include('auditoria.riesgo.partials.wizard-clasico', ['eje' => 'impacto', 'preguntas' => $preguntas['impacto'], 'valores' => []])
            </div>
            <div x-show="paso === 3 && esAmbiental" x-cloak class="space-y-5">
                @include('auditoria.riesgo.partials.wizard-ambiental', ['seccion' => 'impacto', 'valores' => []])
            </div>

            {{-- Paso 4: respuesta, criticidad, área --}}
            <div x-show="paso === 4" class="space-y-5">

                <div class="grid grid-cols-3 gap-4 p-4 bg-gray-50 rounded-xl border border-gray-100">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Probabilidad calculada</p>
                        <p class="text-2xl font-extrabold text-gray-800" x-text="probabilidadTotal"></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Impacto calculado</p>
                        <p class="text-2xl font-extrabold text-gray-800" x-text="impactoTotal"></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Valor total</p>
                        <div class="flex items-baseline gap-2">
                            <p class="text-2xl font-extrabold" :class="clasificacion.texto" x-text="valorTotal"></p>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold"
                                  :class="clasificacion.badge" x-text="clasificacion.etiqueta"></span>
                        </div>
                    </div>
                </div>

                {{--
                    No se reutiliza <x-auditoria.criticidad-checkbox> acá: ese componente detecta
                    cambios de impacto/probabilidad escuchando eventos "input" de inputs numéricos
                    hermanos, y acá esos valores ya no se tipean, se calculan de las respuestas del
                    wizard (mismo x-data). Se resuelve inline contra probabilidadTotal/impactoTotal.
                --}}
                <div class="rounded-xl border transition-colors"
                     :class="habilitado ? 'bg-red-50 border-red-100 p-3' : 'bg-gray-50 border-gray-100 p-3'"
                     x-effect="if (!habilitado && $refs.mayorCriticidad) $refs.mayorCriticidad.checked = false">
                    <input type="hidden" name="mayor_criticidad" value="0" />
                    <input type="checkbox" name="mayor_criticidad" id="mayor_criticidad" value="1"
                        x-ref="mayorCriticidad"
                        {{ old('mayor_criticidad', false) ? 'checked' : '' }}
                        :disabled="!habilitado"
                        class="w-4 h-4 rounded border-gray-300 text-red-600 focus:ring-red-500 disabled:opacity-40 disabled:cursor-not-allowed" />
                    <label for="mayor_criticidad"
                        :class="habilitado ? 'text-red-700 cursor-pointer' : 'text-gray-400 cursor-not-allowed'"
                        class="text-sm font-semibold ml-2">
                        Marcar como mayor criticidad
                        <span x-show="!habilitado" class="text-xs font-normal ml-1">(requiere impacto+probabilidad ≥ 14)</span>
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Respuesta *</label>
                    <select name="respuesta" x-model="respuesta"
                        class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('respuesta') border-red-300 @enderror">
                        <option value="">— Seleccionar respuesta —</option>
                        @foreach (\App\Enums\Auditoria\RespuestaRiesgo::cases() as $opcion)
                            @php $restringida = $opcion->estaRestringida(); @endphp
                            <option value="{{ $opcion->value }}" {{ old('respuesta') === $opcion->value ? 'selected' : '' }}
                                @if ($restringida) :disabled="tipoRestringido" @endif>{{ $opcion->label() }}</option>
                        @endforeach
                    </select>
                    @error('respuesta') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
                    <p class="text-xs text-amber-600 mt-1.5 font-medium" x-show="tipoRestringido" x-cloak>
                        Un riesgo de este tipo no puede compartirse ni aceptarse: sólo se puede mitigar o evitar.
                    </p>
                    <p class="text-xs text-amber-600 mt-1.5 font-medium" x-show="respuesta === 'mitigar'" x-cloak>
                        Si la respuesta es "Reducir / Mitigar", va a hacer falta asociar un plan de acción antes de poder validar este riesgo.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider mb-2 transition-colors"
                        :class="exigeFundamento ? 'text-red-600' : 'text-gray-500'">
                        Fundamento <span x-show="exigeFundamento" x-cloak>*</span>
                    </label>
                    <textarea name="fundamento" rows="3"
                        class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('fundamento') border-red-300 @enderror"
                        placeholder="Por qué se eligió esta respuesta frente al riesgo...">{{ old('fundamento') }}</textarea>
                    @error('fundamento') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-400 mt-1.5" x-show="!exigeFundamento" x-cloak>
                        Opcional. Podés dejar registrado por qué se eligió esta respuesta.
                    </p>
                </div>

                <div class="pt-2 border-t border-gray-100">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Área *</label>
                    <select name="area_id"
                        class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('area_id') border-red-300 @enderror">
                        <option value="" disabled @selected(! old('area_id', auth()->user()->area_id))>— Elegí un área —</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" {{ old('area_id', auth()->user()->area_id) == $area->id ? 'selected' : '' }}>
                                {{ $area->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('area_id') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                @include('auditoria.partials.sedes-checkboxes', ['sedes' => $sedes, 'seleccionadas' => []])
            </div>

            {{-- Navegación del wizard --}}
            <div class="flex justify-between gap-3 pt-2 border-t border-gray-100">
                <div>
                    <a x-show="paso === 1" href="{{ route('auditoria.riesgos.index') }}"
                       class="px-5 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
                        Cancelar
                    </a>
                    <button type="button" x-show="paso > 1" x-cloak @click="atras()"
                        class="px-5 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
                        Atrás
                    </button>
                </div>
                <div>
                    <button type="button" x-show="paso < 4" x-cloak @click="siguiente()" :disabled="!pasoCompleto"
                        :class="pasoCompleto ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-indigo-300 cursor-not-allowed'"
                        class="px-6 py-2.5 text-white text-sm font-bold rounded-xl shadow-sm transition-colors">
                        Siguiente
                    </button>
                    <button type="submit" x-show="paso === 4" x-cloak
                        class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 shadow-sm transition-colors">
                        Crear Riesgo
                    </button>
                </div>
            </div>

        </div>
    </form>

    {{-- Panel de referencia: tipos de riesgo y dimensiones --}}
    <div class="shrink-0">
        @include('auditoria.partials.referencia-riesgo', ['dimensionesMarcadas' => old('dimensiones', [])])
    </div>
    </div>

</div>
@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('riesgoWizard', (pasoInicial) => ({
            paso: pasoInicial,
            nombre: @js(old('nombre', '')),
            tipoRiesgoId: @js((string) old('tipo_riesgo_id', '')),
            // Los usa el partial dimensiones-checkboxes (D-032).
            dimensiones: @js(collect(old('dimensiones', []))->map(fn ($id) => (string) $id)->values()),
            fundamentoDimension: @js(old('fundamento_dimension', '') ?? ''),
            // Objeto y no array: con un array vacío, responder primero la última pregunta estira el
            // length y las siguientes respuestas ya no disparan la reactividad de pasoCompleto.
            probabilidad: Object.assign({}, @json(collect(old('probabilidad_respuestas', []))->map(fn($v) => (int) $v))),
            impacto: Object.assign({}, @json(collect(old('impacto_respuestas', []))->map(fn($v) => (int) $v))),
            // Método ambiental (D-043): sus respuestas y su caracterización.
            tiposAmbientales: @js($tiposRiesgo->filter(fn ($t) => $t->metodo_evaluacion === \App\Enums\Auditoria\MetodoEvaluacion::Ambiental)->pluck('id')->map(fn ($id) => (string) $id)->values()),
            ambiental: {
                @foreach (array_keys(\App\Services\Auditoria\EvaluacionRiesgo::EJES_AMBIENTALES) as $eje)
                    {{ $eje }}: @js(old('ambiental.'.$eje) !== null ? (int) old('ambiental.'.$eje) : null),
                @endforeach
                materialidad_impacto: @js(array_map('intval', (array) old('ambiental.materialidad_impacto', []))),
                materialidad_financiera: @js(array_map('intval', (array) old('ambiental.materialidad_financiera', []))),
            },
            caracterizacion: {
                temporalidad: @js(old('temporalidad')),
                condicion: @js(old('condicion')),
                responsabilidad: @js(old('responsabilidad')),
            },
            respuesta: @js(old('respuesta', '')),
            tiposRestringidos: @js($tiposRiesgo->where('restringe_respuesta')->pluck('id')->map(fn($id) => (string) $id)->values()),
            respuestasRestringidas: @js(array_column(\App\Enums\Auditoria\RespuestaRiesgo::restringidas(), 'value')),
            respuestasConFundamento: @js(array_column(\App\Enums\Auditoria\RespuestaRiesgo::exigenFundamento(), 'value')),

            // El tipo elegido no admite compartir/aceptar (ver TipoRiesgo::restringe_respuesta).
            get tipoRestringido() {
                return this.tiposRestringidos.includes(this.tipoRiesgoId);
            },
            // La respuesta elegida hay que justificarla (ver RespuestaRiesgo::exigenFundamento()).
            get exigeFundamento() {
                return this.respuestasConFundamento.includes(this.respuesta);
            },

            init() {
                // Volver al paso 1 y pasar el riesgo a un tipo restringido dejaría
                // seleccionada una respuesta que ya no es válida para ese tipo.
                this.$watch('tipoRestringido', (restringido) => {
                    if (restringido && this.respuestasRestringidas.includes(this.respuesta)) {
                        this.respuesta = '';
                    }
                });
            },

            get esAmbiental() {
                return this.tiposAmbientales.includes(this.tipoRiesgoId);
            },
            // Vista previa de EvaluacionRiesgo::extraMaterialidad(): el servidor decide.
            get extraMaterialidad() {
                const marcadas = this.ambiental.materialidad_impacto.length + this.ambiental.materialidad_financiera.length;
                return marcadas === 0 ? 0 : (marcadas <= 10 ? 1 : 2);
            },
            get probabilidadTotal() {
                if (this.esAmbiental) {
                    return (this.ambiental.control || 0) + (this.ambiental.frecuencia || 0);
                }
                return Object.values(this.probabilidad).reduce((a, b) => a + (Number(b) || 0), 0);
            },
            get impactoTotal() {
                if (this.esAmbiental) {
                    const a = this.ambiental;
                    return Math.min(10, (a.medio || 0) + (a.naturaleza || 0) + (a.magnitud || 0) + this.extraMaterialidad);
                }
                return Object.values(this.impacto).reduce((a, b) => a + (Number(b) || 0), 0);
            },
            get valorTotal() {
                return this.probabilidadTotal + this.impactoTotal;
            },
            // Mismos umbrales que Riesgo::clasificacion() (0-9 / 10-13 / 14+).
            get clasificacion() {
                if (this.valorTotal <= 9) {
                    return { etiqueta: 'Bajo', texto: 'text-green-700', badge: 'bg-green-100 text-green-700' };
                }
                if (this.valorTotal <= 13) {
                    return { etiqueta: 'Moderado', texto: 'text-yellow-700', badge: 'bg-yellow-100 text-yellow-700' };
                }
                return { etiqueta: 'Crítico', texto: 'text-red-700', badge: 'bg-red-100 text-red-700' };
            },
            get habilitado() {
                return this.valorTotal >= 14;
            },
            get pasoCompleto() {
                if (this.paso === 1) {
                    // Al menos una dimensión; con dos o más, fundamento (D-032).
                    const dimensionOk = this.dimensiones.length > 0
                        && (this.dimensiones.length < 2 || this.fundamentoDimension.trim() !== '');
                    const c = this.caracterizacion;
                    const caracterizacionOk = !this.esAmbiental || (c.temporalidad && c.condicion && c.responsabilidad);
                    return this.nombre.trim() !== '' && this.tipoRiesgoId !== '' && dimensionOk && caracterizacionOk;
                }
                if (this.paso === 2) {
                    if (this.esAmbiental) return this.ambiental.control !== null && this.ambiental.frecuencia !== null;
                    return Object.keys(this.probabilidad).length === {{ count($preguntas['probabilidad']) }};
                }
                if (this.paso === 3) {
                    if (this.esAmbiental) return ['medio', 'naturaleza', 'magnitud'].every(e => this.ambiental[e] !== null);
                    return Object.keys(this.impacto).length === {{ count($preguntas['impacto']) }};
                }
                return true;
            },
            siguiente() {
                if (this.pasoCompleto) this.paso++;
            },
            atras() {
                this.paso--;
            },
        }));
    });

    const select = document.querySelector('select[name="tipo_riesgo_id"]');
    function highlight(val) {
        document.querySelectorAll('#tipo-riesgo-ref > div').forEach(el => {
            el.classList.remove('border-indigo-300', 'bg-indigo-50/50', 'shadow-sm');
            el.classList.add('border-gray-100');
        });
        if (val) {
            const active = document.getElementById('ref-tipo-' + val);
            if (active) {
                active.classList.remove('border-gray-100');
                active.classList.add('border-indigo-300', 'bg-indigo-50/50', 'shadow-sm');
                active.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        }
    }
    select.addEventListener('change', e => highlight(e.target.value));
    highlight(select.value);
</script>
@endpush
@endsection
