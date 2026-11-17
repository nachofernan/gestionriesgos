<?php

// Cuestionario que valora un control (D-051). Cada pregunta es de sí/no y vale
// lo que dicen 'si' y 'no'; el puntaje va de 1 a 10. 'operativo' es
// determinante: si el control es una expresión de deseo, el puntaje se muestra
// pero el control mitiga 0. Algunas respuestas «sí» piden un detalle
// ('detalle'): un texto libre, los sistemas donde ocurre o la frecuencia.
return [
    'preguntas' => [
        ['id' => 'operativo', 'texto' => '¿El control está operativo hoy o es una expresión de deseo?',
            'si_texto' => 'Sí, existe y se encuentra operando', 'no_texto' => 'No, es una expresión de deseo',
            'si' => 1, 'no' => 0],
        ['id' => 'documentado', 'texto' => '¿Está documentado en un procedimiento, instructivo o anexo?',
            'si' => 2, 'no' => 0, 'detalle' => 'procedimiento'],
        ['id' => 'sistema', 'texto' => '¿Ocurre dentro de un sistema propio o de terceros?',
            'si_texto' => 'Sí', 'no_texto' => 'No, es manual',
            'si' => 3, 'no' => 1, 'detalle' => 'sistemas'],
        ['id' => 'responsable', 'texto' => '¿Tiene identificado un responsable?',
            'si' => 1, 'no' => 0],
        ['id' => 'frecuencia', 'texto' => '¿Ocurre con una frecuencia determinada?',
            'si' => 1, 'no' => 0, 'detalle' => 'frecuencia'],
        ['id' => 'evidencia', 'texto' => 'Una vez que el control operó, ¿deja evidencia de su realización?',
            'si' => 1, 'no' => 0],
        ['id' => 'detecta', 'texto' => '¿Le permitió este control prevenir o detectar errores en el proceso al cual pertenece?',
            'si' => 1, 'no' => 0],
    ],

    'sistemas' => [
        'SAP',
        'IDEA',
        'Sistema de mesa de entradas',
        'Línea Ética',
        'VISMA',
        'SOTR 2',
        'Paquete Office 365',
        'IVMS4200 Registro de ingreso y egreso',
        'Backup de Sistemas',
        'Plataforma (Registro de Proveedores / Gestión de Concursos / Sistema de Capacitaciones / Helpdesk / Inventario de elementos informáticos)',
    ],

    'frecuencias' => [
        'diaria' => 'Diaria',
        'semanal' => 'Semanal',
        'mensual' => 'Mensual',
        'trimestral' => 'Trimestral',
        'anual' => 'Anual',
        'cada_operacion' => 'Con cada operación',
    ],
];
