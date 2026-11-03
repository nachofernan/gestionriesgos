<?php

// Matriz de evaluación de aspectos e impactos ambientales (D-043), tal como
// la pasó auditoría (docs/matriz_de_evaluaci_n_de_impacto_ambiental.md).
// Probabilidad = control + frecuencia. Impacto = medio + naturaleza + magnitud
// + extra por materialidades (ver EvaluacionRiesgo::calcular()).
// 'v' = valor, 't' = criterio, 'd' = descripción.
return [
    'probabilidad' => [
        'control' => ['titulo' => 'Control', 'opciones' => [
            ['v' => 5, 't' => 'Aspecto no controlado', 'd' => [
                'No tiene procedimientos escritos',
                'No se realizan mediciones',
                'No tiene sistema de protección o barreras',
            ]],
            ['v' => 3, 't' => 'Aspecto parcialmente controlado', 'd' => [
                'Cuando los procedimientos escritos o instrucciones no son suficientes para el control',
                'Cuando las mediciones realizadas no son periódicas',
                'Cuando las barreras o sistemas de protección no son suficientes',
            ]],
            ['v' => 1, 't' => 'Aspecto controlado', 'd' => [
                'Cuando tienen implementadas medidas de control operativo',
                'Cuando se realizan mediciones periódicas',
                'Cuando son efectivas las barreras físicas',
            ]],
        ]],
        'frecuencia' => ['titulo' => 'Frecuencia', 'opciones' => [
            ['v' => 5, 't' => 'Muy frecuente', 'd' => ['Cuando se produce o puede producirse con una frecuencia diaria']],
            ['v' => 3, 't' => 'Frecuente', 'd' => ['Cuando se produce o puede producirse con una frecuencia semanal o mensual']],
            ['v' => 1, 't' => 'Poco frecuente', 'd' => ['Cuando se produce o puede producirse en forma no periódica']],
        ]],
    ],

    'impacto' => [
        'medio' => ['titulo' => 'Medio sobre el que ocurre el impacto', 'opciones' => [
            ['v' => 3, 't' => 'Muy sensible', 'd' => ['Cercanías a centros poblados, napas subterráneas poco profundas.']],
            ['v' => 2, 't' => 'Sensible', 'd' => ['Suelo, áreas pobladas.']],
            ['v' => 1, 't' => 'Poco sensible', 'd' => ['Zona industrial, pisos con cemento.']],
        ]],
        'naturaleza' => ['titulo' => 'Naturaleza del elemento contaminante', 'opciones' => [
            ['v' => 3, 't' => 'Peligrosa', 'd' => ['Sustancias corrosivas, inflamables, reactivas, carcinogénicas, tóxicas, irritantes, lixiviables, mutagénicas, teratogénicas e infecciosas, y aquellas mencionadas en la ley 11720 de la provincia de Buenos Aires.']],
            ['v' => 2, 't' => 'Poco peligrosa', 'd' => ['Ruidos, vibraciones, residuos domiciliarios.']],
            ['v' => 1, 't' => 'No peligrosa', 'd' => ['El elemento no es contaminante.']],
        ]],
        // Una sola elección 1-4; las seis variables son guía para pensarla, no se puntúan.
        'magnitud' => ['titulo' => 'Magnitud del impacto', 'opciones' => [
            ['v' => 4, 't' => 'Crítico', 'd' => ['La afección de este produce una pérdida permanente de la calidad en las condiciones ambientales. No existe posibilidad de recuperación.']],
            ['v' => 3, 't' => 'Severo', 'd' => ['La afección de este exige la recuperación de las condiciones del medio a través de medidas correctivas.']],
            ['v' => 2, 't' => 'Moderado', 'd' => ['La afección de este no precisa prácticas correctivas.']],
            ['v' => 1, 't' => 'Bajo', 'd' => ['La afección de este es irrelevante.']],
        ]],
    ],

    'guia_magnitud' => [
        'Intensidad (I)' => 'Grado probable de destrucción: el grado de incidencia de la acción sobre el factor. Puede ser baja (afección mínima) o total (destrucción total).',
        'Extensión (Ex)' => 'Área de influencia del impacto con relación al entorno.',
        'Momento (Mo)' => 'Plazo en que se manifiesta el impacto: el tiempo entre la aparición de la acción y el comienzo del efecto (largo plazo, medio plazo o inmediato).',
        'Reversibilidad (Rv)' => 'Posibilidad de retornar a las condiciones iniciales previas a la acción por medios naturales.',
        'Efecto (Ef)' => 'La relación causa-efecto.',
        'Recuperabilidad (Mc)' => 'Posibilidad de reconstrucción, total o parcial, del factor afectado por medio de la intervención humana.',
    ],

    // Cada lista, id => texto. Marcar 0 suma 0 al impacto; de 1 a 10, suma 1; más de 10, suma 2.
    'materialidad' => [
        'impacto' => ['titulo' => 'Materialidad de impacto', 'items' => [
            1 => 'Consumo de combustibles fósiles',
            2 => 'Consumo de agua',
            3 => 'Generación de residuos industriales',
            4 => 'Generación de residuos especiales',
            5 => 'Generación de asbestos',
            6 => 'Generación de RAEE-AEE',
            7 => 'Recupero de materiales ferrosos / no ferrosos',
            8 => 'Emisiones de fuentes móviles',
            9 => 'Contaminación de agua y suelo',
            10 => 'Impacto sobre los DDHH de trabajadores, comunidades, terceros o la S y SO',
        ]],
        'financiera' => ['titulo' => 'Materialidad financiera', 'pregunta' => '¿Se genera alguno o varios temas con impacto financiero-económico para BAESA?', 'items' => [
            1 => 'Parada de planta / multa / pérdida de ingreso por falta de permiso',
            2 => 'Incumplimiento por parte de proveedores de los requisitos ambientales y/o seguridad y salud ocupacional',
            3 => 'Sobreprecio-pagos por trabajos de menor calidad-pagos sin contraprestación (corrupción)',
            4 => 'Reclamos de terceros',
            5 => 'Conflictos gremiales',
            6 => 'Multa/sanción por incumplimiento normativo',
            7 => 'Posibles consecuencias penales',
            8 => 'Gestión de compras y contrataciones sin perspectiva ambiental',
            9 => 'Resarcimiento a terceros por impacto sobre los DDHH de trabajadores, comunidades, terceros o la S y SO',
            10 => 'Externalidad positiva al medio ambiente que permite acceso a mejor financiación',
        ]],
    ],
];
