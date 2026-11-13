<?php

// Preguntas para valorar un plan de acción (D-049): 5 de reducción de la
// probabilidad y 5 de reducción del impacto, de sí/no. Cada sí reduce 2 puntos
// su eje (hasta 10). Cada pregunta tiene dos redacciones: 'estimada' (al diseñar
// el plan: ¿se espera que logre…?) y 'final' (con el plan implementado: ¿logró…?).
return [
    'probabilidad' => [
        ['id' => 1, 'estimada' => '¿Eliminará o reducirá las causas directas o subyacentes que podrían dar origen al riesgo?',
            'final' => '¿Eliminó o redujo las causas directas o subyacentes que podrían dar origen al riesgo?'],
        ['id' => 2, 'estimada' => '¿Reducirá la exposición al riesgo, disminuyendo las oportunidades, operaciones, procesos o situaciones en las que podría producirse?',
            'final' => '¿Redujo la exposición al riesgo, disminuyendo las oportunidades, operaciones, procesos o situaciones en las que podría producirse?'],
        ['id' => 3, 'estimada' => '¿Incorporará o fortalecerá controles preventivos, mecanismos de autorización, supervisión o verificación que disminuyan la posibilidad de ocurrencia del riesgo?',
            'final' => '¿Incorporó o fortaleció controles preventivos, mecanismos de autorización, supervisión o verificación que disminuyan la posibilidad de ocurrencia del riesgo?'],
        ['id' => 4, 'estimada' => '¿Reducirá la dependencia de decisiones individuales o discrecionales, o establecerá una mayor separación de funciones en las actividades expuestas al riesgo?',
            'final' => '¿Redujo la dependencia de decisiones individuales o discrecionales, o estableció una mayor separación de funciones en las actividades expuestas al riesgo?'],
        ['id' => 5, 'estimada' => '¿Establecerá mecanismos de seguimiento, indicadores o alertas tempranas que permitan identificar y corregir oportunamente desvíos antes de que el riesgo se materialice?',
            'final' => '¿Estableció mecanismos de seguimiento, indicadores o alertas tempranas que permitan identificar y corregir oportunamente desvíos antes de que el riesgo se materialice?'],
    ],
    'impacto' => [
        ['id' => 1, 'estimada' => '¿Reducirá la cantidad de personas, áreas, procesos o terceros que podrían resultar afectados por la materialización del riesgo?',
            'final' => '¿Redujo la cantidad de personas, áreas, procesos o terceros que podrían resultar afectados por la materialización del riesgo?'],
        ['id' => 2, 'estimada' => '¿Disminuirá la gravedad de las consecuencias sobre la salud, seguridad, integridad o derechos de las personas?',
            'final' => '¿Disminuyó la gravedad de las consecuencias sobre la salud, seguridad, integridad o derechos de las personas?'],
        ['id' => 3, 'estimada' => '¿Reducirá las posibles pérdidas económicas, costos, sanciones o indemnizaciones derivadas de la materialización del riesgo?',
            'final' => '¿Redujo las posibles pérdidas económicas, costos, sanciones o indemnizaciones derivadas de la materialización del riesgo?'],
        ['id' => 4, 'estimada' => '¿Disminuirá las posibles consecuencias sobre la continuidad operativa, la prestación del servicio o el funcionamiento de los procesos?',
            'final' => '¿Disminuyó las posibles consecuencias sobre la continuidad operativa, la prestación del servicio o el funcionamiento de los procesos?'],
        ['id' => 5, 'estimada' => '¿Disminuirá las posibles consecuencias legales, regulatorias o reputacionales para la organización y/o terceros?',
            'final' => '¿Disminuyó las posibles consecuencias legales, regulatorias o reputacionales para la organización y/o terceros?'],
    ],
];
