<?php

// Preguntas del wizard de creación de riesgos para calcular impacto y
// probabilidad (0-10 cada una) como suma de las 5 respuestas de su dimensión.
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
    'impacto' => [
        ['id' => 1, 'pregunta' => '¿Se afectaría la operación normal del área o de otras áreas?', 'opciones' => [
            ['v' => 0, 't' => 'No afectaría'],
            ['v' => 1, 't' => 'Impacto moderado'],
            ['v' => 2, 't' => 'Afectaría altamente'],
        ]],
        ['id' => 2, 'pregunta' => '¿Podría generar pérdidas económicas, sanciones o multas?', 'opciones' => [
            ['v' => 0, 't' => 'No generaría'],
            ['v' => 1, 't' => 'Pérdidas moderadas'],
            ['v' => 2, 't' => 'Altas pérdidas'],
        ]],
        ['id' => 3, 'pregunta' => '¿Este riesgo podría dañar la imagen de la empresa o relación con terceros?', 'opciones' => [
            ['v' => 0, 't' => 'No dañaría'],
            ['v' => 1, 't' => 'Daño moderado'],
            ['v' => 2, 't' => 'Daño significativo'],
        ]],
        ['id' => 4, 'pregunta' => '¿Implica un incumplimiento legal, normativo o contractual?', 'opciones' => [
            ['v' => 0, 't' => 'Ninguno'],
            ['v' => 1, 't' => 'Varios incumplimientos'],
            ['v' => 2, 't' => 'Alto incumplimiento'],
        ]],
        ['id' => 5, 'pregunta' => '¿Puede afectar la seguridad física de personas o instalaciones?', 'opciones' => [
            ['v' => 0, 't' => 'No afecta'],
            ['v' => 1, 't' => 'Probabilidad moderada'],
            ['v' => 2, 't' => 'Alto impacto'],
        ]],
    ],
];
