{{--
    Modal antes de guardar un cambio sobre algo aprobado que puede quedar como
    aprobación del comité (trait EligeRol). Con rol de gerente y de comité sobre su
    subárbol elige: propuesta o establecer como comité (D-052). Si el cambio igual
    se establece como comité, sólo confirma y pide N° y fecha (D-053). Lo abre
    pedirGuardar() y sus botones llaman a guardarComo(). Va dentro de la vista de
    un componente que use el trait.
--}}
@if($eligiendoRol)
@php $eligeRol = $this->requiereElegirRol(); @endphp
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg max-w-lg w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900">{{ $eligeRol ? '¿Cómo querés guardar este cambio?' : 'Establecer el cambio como coordinador(a) del comité' }}</h3>
            <button type="button" wire:click="$set('eligiendoRol', false)" class="text-gray-400 hover:text-gray-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="px-6 py-4 space-y-4">
            @if($eligeRol)
                <p class="text-sm text-gray-600">Sos responsable primario de esta área y también coordinador(a) del comité. Elegí con qué rol actuás.</p>

                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 space-y-2">
                    <p class="text-sm font-bold text-amber-800">Enviar como propuesta</p>
                    <p class="text-xs text-amber-700">Queda validada por vos como responsable primario y pendiente de aprobación del comité.</p>
                    <div class="flex justify-end">
                        <button type="button" wire:click="guardarComo('propuesta')" wire:loading.attr="disabled" wire:target="guardarComo"
                            class="px-4 py-2 text-xs font-bold rounded-lg text-white bg-amber-600 hover:bg-amber-700 disabled:opacity-60">
                            Enviar propuesta
                        </button>
                    </div>
                </div>
            @endif

            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 space-y-3">
                @if($eligeRol)
                    <p class="text-sm font-bold text-indigo-800">Establecer como coordinador(a) del comité</p>
                @endif
                <p class="text-xs text-indigo-700">Se aplica en el acto como aprobación del comité. Cargá el N° y la fecha del comité que lo resolvió; si todavía no los tenés, se pueden completar después.</p>
                @include('auditoria.partials.campos-comite')
                <div class="flex justify-end gap-2">
                    @unless($eligeRol)
                        <button type="button" wire:click="$set('eligiendoRol', false)" class="px-3 py-2 text-xs font-semibold text-gray-500 hover:text-gray-800">Cancelar</button>
                    @endunless
                    <button type="button" wire:click="guardarComo('comite')" wire:loading.attr="disabled" wire:target="guardarComo"
                        class="px-4 py-2 text-xs font-bold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60">
                        Establecer como coordinador(a) del comité
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
