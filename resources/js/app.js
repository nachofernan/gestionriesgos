import './bootstrap';

// Livewire trae su propio Alpine y lo arranca en toda página con un componente.
// Un segundo Alpine se queda con los x-data: adentro no existe $wire y los
// wire:* no se enganchan. Sólo se arranca éste donde no hay Livewire (login, perfil).
if (! window.Livewire) {
    import('alpinejs').then(({ default: Alpine }) => {
        window.Alpine = Alpine;
        Alpine.start();
    });
}
