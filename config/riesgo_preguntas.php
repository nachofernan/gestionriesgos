<?php

// Preguntas del método clásico para calcular impacto y probabilidad (0-10
// cada uno) como suma de las respuestas de su eje: probabilidad, 5 preguntas
// de 0 a 2; impacto, 10 preguntas de sí/no (0 o 1).
return [
    'probabilidad' => [
        ['id' => 1, 'pregunta' => '¿Este riesgo ya ocurrió en el pasado en esta área o similares?', 'opciones' => [
            ['v' => 0, 't' => 'Nunca o hace mucho'],
            ['v' => 1, 't' => 'Ocurrió hace poco'],
            ['v' => 2, 't' => 'Ha ocurrido varias veces'],
        ]],
        ['id' => 2, 'pregunta' => '¿Existen condiciones actuales que favorezcan que ocurra?', 'opciones' => [
            ['v' => 0, 't' => 'No hay condiciones'],
            ['v' => 1, 't' => 'Algunas condiciones'],
            ['v' => 2, 't' => 'Muchas condiciones'],
        ]],
        ['id' => 3, 'pregunta' => '¿Qué tan expuestos estamos a este riesgo hoy?', 'opciones' => [
            ['v' => 0, 't' => 'Nula'],
            ['v' => 1, 't' => 'Media'],
            ['v' => 2, 't' => 'Alta'],
        ]],
        ['id' => 4, 'pregunta' => '¿Depende de terceros o factores externos fuera de control?', 'opciones' => [
            ['v' => 0, 't' => 'No depende'],
            ['v' => 1, 't' => 'Depende parcialmente'],
            ['v' => 2, 't' => 'Depende en gran parte'],
        ]],
        ['id' => 5, 'pregunta' => '¿Qué tan fácil sería que un error humano lo materialice?', 'opciones' => [
            ['v' => 0, 't' => 'Improbable'],
            ['v' => 1, 't' => 'Moderado'],
            ['v' => 2, 't' => 'Probable'],
        ]],
    ],
    // Doble materialidad (D-045): 10 preguntas de sí/no, 1 punto cada una.
    'impacto' => array_map(
        fn (array $p) => $p + ['opciones' => [['v' => 0, 't' => 'No'], ['v' => 1, 't' => 'Sí']]],
        [
            ['id' => 1, 'grupo' => 'Materialidad de impacto', 'pregunta' => '¿Puede afectar la salud, seguridad o integridad de trabajadores, contratistas, usuarios o comunidades?'],
            ['id' => 2, 'grupo' => 'Materialidad de impacto', 'pregunta' => '¿Puede vulnerar derechos humanos o laborales, incluidos el trato digno, la igualdad y la no discriminación?'],
            ['id' => 3, 'grupo' => 'Materialidad de impacto', 'pregunta' => '¿Puede dañar el ambiente, los recursos naturales o la biodiversidad?'],
            ['id' => 4, 'grupo' => 'Materialidad de impacto', 'pregunta' => '¿Puede afectar a terceros o a la comunidad, por ejemplo, mediante la interrupción de un servicio esencial?'],
            ['id' => 5, 'grupo' => 'Materialidad de impacto', 'pregunta' => '¿Puede generar impactos extendidos o difíciles de revertir, por su alcance o duración?'],
            ['id' => 6, 'grupo' => 'Materialidad financiera', 'pregunta' => '¿Puede afectar la operación normal del área, de otras áreas o la prestación del servicio?'],
            ['id' => 7, 'grupo' => 'Materialidad financiera', 'pregunta' => '¿Puede generar pérdidas económicas, mayores costos, sanciones, multas, indemnizaciones o costos legales?'],
            ['id' => 8, 'grupo' => 'Materialidad financiera', 'pregunta' => '¿Puede dañar la imagen de la empresa, deteriorar nuestra reputación o la confianza de accionistas, clientes, proveedores u otros grupos de interés o la relación con terceros?'],
            ['id' => 9, 'grupo' => 'Materialidad financiera', 'pregunta' => '¿Puede implicar un incumplimiento legal, normativo o contractual?'],
            ['id' => 10, 'grupo' => 'Materialidad financiera', 'pregunta' => '¿Puede generar pérdida de ingresos, contratos, oportunidades de negocio o acceso a mercados?'],
        ],
    ),
];
