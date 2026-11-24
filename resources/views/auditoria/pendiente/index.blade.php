@extends('layouts.auditoria')
@section('title', 'Pendientes')

{{--
    Pendientes en colas y pestañas (D-059). Arriba, la cola (validar como
    gerente / aprobar como comité; el selector aparece sólo con doble rol). En
    cada cola, pestañas por naturaleza: altas (elementos nuevos), cambios
    (propuestas sobre algo aprobado) y bajas (propuestas de eliminación). Todas
    las filas se pintan en Blade y Alpine sólo las muestra u oculta según cola,
    pestaña y filtros, que viven en la query string para que recargar no te saque
    de donde estabas. Los permisos de cada fila vienen resueltos del controlador.
--}}
@php
    $naturalezas = ['alta' => ['label' => 'Altas'], 'cambio' => ['label' => 'Cambios'], 'baja' => ['label' => 'Bajas']];
    $indice = collect($colas)->flatMap(fn ($porNaturaleza) => collect($porNaturaleza)->flatten())
        ->map(fn ($clave) => collect($detalles[$clave])->only(['clave', 'accion', 'naturaleza', 'tipo', 'area_id', 'revision_inicial'])->all())
        ->values();
    $total = $indice->count();
    $tiposPresentes = collect($tipos)->only($indice->pluck('tipo')->filter()->unique()->all());
    $areas = collect($detalles)->filter(fn ($d) => $d['area_id'])->mapWithKeys(fn ($d) => [$d['area_id'] => $d['area']])->sort();
    $hayRevision = $indice->contains('revision_inicial', true);
@endphp

@section('content')
<div class="space-y-6"
     x-data="{
        filas: @js($indice),
        colas: @js(array_keys($colas)),
        cola: null, tab: null, tipo: '', area: '', origen: '',
        procesados: [],
        init() {
            const p = new URLSearchParams(location.search);
            this.cola = this.colas.includes(p.get('cola')) ? p.get('cola')
                : (this.colas.find(c => this.pendientes(c) > 0) ?? this.colas[0]);
            this.tipo = p.get('tipo') ?? '';
            this.area = p.get('area') ?? '';
            this.origen = p.get('origen') === 'revision' ? 'revision' : '';
            this.tab = ['alta', 'cambio', 'baja'].includes(p.get('tab')) ? p.get('tab') : this.primeraPestana();
            ['cola', 'tab', 'tipo', 'area', 'origen'].forEach(k => this.$watch(k, () => this.sincronizar()));
        },
        primeraPestana() {
            return ['alta', 'cambio', 'baja'].find(n => this.cuenta(n) > 0) ?? 'alta';
        },
        elegirCola(c) { this.cola = c; this.tab = this.primeraPestana(); },
        sincronizar() {
            const p = new URLSearchParams();
            if (this.colas.length > 1) p.set('cola', this.cola);
            p.set('tab', this.tab);
            if (this.tipo) p.set('tipo', this.tipo);
            if (this.area) p.set('area', this.area);
            if (this.origen) p.set('origen', this.origen);
            history.replaceState(null, '', location.pathname + '?' + p.toString());
        },
        pasaFiltros(f) {
            return (!this.tipo || f.tipo === this.tipo)
                && (!this.area || String(f.area_id) === this.area)
                && (!this.origen || f.revision_inicial);
        },
        abierta(f) { return !this.procesados.includes(f.clave); },
        pendientes(c) { return this.filas.filter(f => f.accion === c && this.abierta(f)).length; },
        cuenta(n) { return this.filas.filter(f => f.accion === this.cola && f.naturaleza === n && this.pasaFiltros(f) && this.abierta(f)).length; },
        muestra(clave) {
            const f = this.filas.find(f => f.clave === clave);
            return f && f.accion === this.cola && f.naturaleza === this.tab && this.pasaFiltros(f);
        },
        vacia() { return !this.filas.some(f => f.accion === this.cola && f.naturaleza === this.tab && this.pasaFiltros(f)); },
        filtrando() { return this.tipo || this.area || this.origen; },
        limpiar() { this.tipo = ''; this.area = ''; this.origen = ''; },
     }"
     x-on:cascada-procesada.window="procesados.push($event.detail.tipo + '-' + $event.detail.id)"
     x-on:actualizacion-procesada.window="procesados.push('actualizacion-' + $event.detail.id)">

    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Pendientes</h1>
            <p class="text-sm text-gray-500 mt-1 max-w-3xl">
                Lo que te toca decidir. <span class="font-semibold text-gray-700">Altas</span> son elementos nuevos,
                <span class="font-semibold text-gray-700">cambios</span> son propuestas sobre algo ya aprobado y
                <span class="font-semibold text-rose-700">bajas</span> son propuestas de eliminación. La lista se
                actualiza al recargar; cada acción sólo afecta a ese elemento.
            </p>
        </div>
        @if($total > 0)
            <a href="{{ route('auditoria.pendientes.pdf') }}"
               class="shrink-0 px-4 py-2 bg-gray-50 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-100 transition-colors">
                Descargar PDF
            </a>
        @endif
    </div>

    @if($total === 0)
        <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400 italic text-sm">
            No tenés nada pendiente por ahora.
        </div>
    @else
        {{-- Cola: sólo con doble rol (D-052). Nunca se mezclan. --}}
        @if(count($colas) > 1)
            <div class="inline-flex rounded-xl bg-gray-100 p-1">
                @foreach(['validar' => 'Me toca validar', 'aprobar' => 'Me toca aprobar'] as $accion => $label)
                    <button type="button" x-on:click="elegirCola('{{ $accion }}')"
                            class="px-4 py-2 rounded-lg text-sm font-bold transition-colors"
                            :class="cola === '{{ $accion }}' ? 'bg-white shadow-sm {{ $accion === 'validar' ? 'text-blue-700' : 'text-green-700' }}' : 'text-gray-500 hover:text-gray-700'">
                        {{ $label }}
                        <span class="ml-1 text-xs font-semibold opacity-70" x-text="pendientes('{{ $accion }}')"></span>
                    </button>
                @endforeach
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            {{-- Pestañas por naturaleza --}}
            <div class="flex items-end gap-1 px-4 pt-3 border-b border-gray-100">
                @foreach($naturalezas as $naturaleza => $cfg)
                    <button type="button" x-on:click="tab = '{{ $naturaleza }}'"
                            class="px-4 py-2 -mb-px border-b-2 text-sm font-bold transition-colors"
                            :class="tab === '{{ $naturaleza }}'
                                ? '{{ $naturaleza === 'baja' ? 'border-rose-500 text-rose-700' : 'border-indigo-500 text-indigo-700' }}'
                                : 'border-transparent text-gray-400 hover:text-gray-600'">
                        {{ $cfg['label'] }}
                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[11px]"
                              :class="cuenta('{{ $naturaleza }}') === 0 ? 'bg-gray-50 text-gray-300'
                                  : '{{ $naturaleza === 'baja' ? 'bg-rose-50 text-rose-700' : 'bg-indigo-50 text-indigo-700' }}'"
                              x-text="cuenta('{{ $naturaleza }}')"></span>
                    </button>
                @endforeach
            </div>

            {{-- Filtros --}}
            <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center gap-2">
                @if($tiposPresentes->count() > 1)
                    <button type="button" x-on:click="tipo = ''"
                            class="px-2.5 py-1 rounded-full text-xs font-semibold transition-colors"
                            :class="tipo === '' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">Todos</button>
                    @foreach($tiposPresentes as $clave => $cfg)
                        <button type="button" x-on:click="tipo = '{{ $clave }}'"
                                class="px-2.5 py-1 rounded-full text-xs font-semibold transition-colors"
                                :class="tipo === '{{ $clave }}' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">{{ $cfg['label'] }}</button>
                    @endforeach
                @endif

                @if($hayRevision)
                    <button type="button" x-on:click="origen = origen ? '' : 'revision'"
                            class="px-2.5 py-1 rounded-full text-xs font-semibold transition-colors"
                            :class="origen ? 'bg-violet-600 text-white' : 'bg-violet-50 text-violet-700 hover:bg-violet-100'">
                        De la revisión inicial
                    </button>
                @endif

                <div class="ml-auto flex items-center gap-2">
                    @if($areas->count() > 1)
                        <select x-model="area" class="border-gray-200 rounded-lg text-xs py-1 pr-8 focus:border-indigo-400 focus:ring-indigo-400">
                            <option value="">Todas las áreas</option>
                            @foreach($areas as $id => $nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button type="button" x-show="filtrando()" x-cloak x-on:click="limpiar()"
                            class="text-xs font-semibold text-gray-400 hover:text-gray-600">Limpiar filtros</button>
                </div>
            </div>

            {{-- Filas: todas las colas y pestañas, Alpine muestra las que tocan --}}
            <div class="divide-y divide-gray-100">
                @foreach($colas as $accion => $porNaturaleza)
                    @foreach($porNaturaleza as $naturaleza => $claves)
                        @foreach($claves as $clave)
                            @include('auditoria.pendiente.partials.fila', ['detalle' => $detalles[$clave]])
                        @endforeach
                    @endforeach
                @endforeach
            </div>

            <div x-show="vacia()" x-cloak class="px-6 py-10 text-center text-sm text-gray-400 italic">
                <span x-show="!filtrando()">No hay <span x-text="{ alta: 'altas', cambio: 'cambios', baja: 'bajas' }[tab]"></span> para <span x-text="cola"></span>.</span>
                <span x-show="filtrando()">Nada con estos filtros. <button type="button" x-on:click="limpiar()" class="not-italic font-semibold text-indigo-600 hover:text-indigo-700">Limpiar</button></span>
            </div>
        </div>
    @endif

</div>

@include('auditoria.pendiente.partials.modal-detalle')
@livewire('auditoria.validacion-cascada-modal')
@livewire('auditoria.actualizaciones.accion-actualizacion-modal')
@endsection
