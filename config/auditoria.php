<?php

return [

    // Pantalla transitoria de revisión de la carga inicial (D-024). Se apaga
    // cuando se da por terminado el salto desde Excel/Redmine: desaparecen la
    // ruta y el menú, las firmas quedan en la base como registro.
    'revision_inicial' => [
        'activa' => env('AUDITORIA_REVISION_INICIAL', true),
    ],

];
