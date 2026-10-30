{{--
    Botones de una propuesta pendiente (validar / aprobar / rechazar / retirar),
    ya resueltos contra la policy en x-auditoria.propuesta-pendiente. Disparan
    'resolver-actualizacion', que atiende GestionActualizaciones. Al confirmar, el botón
    se deshabilita hasta que el bloque se vuelve a dibujar (no es un wire:click propio,
    así que wire:loading no aplica).
    Variables: $propuesta, $btn, $puedeValidar, $puedeAprobar, $puedeRechazar, $puedeRetirar.
--}}
@if($puedeValidar)
    <button type="button" class="{{ $btn }} bg-blue-600 text-white hover:bg-blue-700"
            onclick="if (confirm('¿Validar esta propuesta?')) { this.disabled = true; this.classList.add('opacity-50', 'cursor-wait'); Livewire.dispatch('resolver-actualizacion', { id: {{ $propuesta->id }}, accion: 'validar' }) }">
        Validar
    </button>
@endif
@if($puedeAprobar)
    <x-auditoria.boton-aprobar-propuesta :actualizacionId="$propuesta->id" :clase="$btn.' bg-green-600 text-white hover:bg-green-700'" />
@endif
@if($puedeRechazar)
    <x-auditoria.boton-rechazar :actualizacionId="$propuesta->id" :clase="$btn.' text-red-600 hover:bg-red-50'" />
@endif
@if($puedeRetirar)
    <button type="button" class="{{ $btn }} text-gray-500 hover:bg-gray-100"
            onclick="if (confirm('¿Retirar tu propuesta?')) { this.disabled = true; this.classList.add('opacity-50', 'cursor-wait'); Livewire.dispatch('resolver-actualizacion', { id: {{ $propuesta->id }}, accion: 'cancelar' }) }">
        Retirar
    </button>
@endif
