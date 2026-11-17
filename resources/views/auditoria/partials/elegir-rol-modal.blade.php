{{--
    Modal para elegir con qué rol se guarda un cambio cuando el usuario es gerente
    y comité (D-052). Lo abre abrirElegirRol() y llama a guardarComo() (trait
    EligeRol). Va dentro de la vista de un componente que use ese trait; el botón
    de guardar del componente llama a abrirElegirRol cuando requiereElegirRol().
--}}
@if($eligiendoRol)
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg max-w-lg w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900">¿Cómo querés guardar este cambio?</h3>
            <button type="button" wire:click="$set('eligiendoRol', false)" class="text-gray-400 hover:text-gray-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="px-6 py-4 space-y-4">
            <p class="text-sm text-gray-600">Sos gerente de esta área y también comité. Elegí con qué rol actuás.</p>

            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 space-y-2">
                <p class="text-sm font-bold text-amber-800">Enviar como propuesta</p>
                <p class="text-xs text-amber-700">Queda validada por vos como gerente y pendiente de aprobación del comité.</p>
                <div class="flex justify-end">
                    <button type="button" wire:click="guardarComo('propuesta')" wire:loading.attr="disabled" wire:target="guardarComo"
                        class="px-4 py-2 text-xs font-bold rounded-lg text-white bg-amber-600 hover:bg-amber-700 disabled:opacity-60">
                        Enviar propuesta
                    </button>
                </div>
            </div>

            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 space-y-3">
                <p class="text-sm font-bold text-indigo-800">Establecer como comité</p>
                <p class="text-xs text-indigo-700">Se aplica en el acto como aprobación del comité. El N° y la fecha se pueden completar después.</p>
                @include('auditoria.partials.campos-comite')
                <div class="flex justify-end">
                    <button type="button" wire:click="guardarComo('comite')" wire:loading.attr="disabled" wire:target="guardarComo"
                        class="px-4 py-2 text-xs font-bold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60">
                        Establecer como comité
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
