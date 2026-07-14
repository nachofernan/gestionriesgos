<?php

namespace Database\Seeders;

use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use Illuminate\Database\Seeder;

class ControlSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('id', '>', 1)->whereNotNull('area_id')->get();
        $riesgoIds = Riesgo::pluck('id')->toArray();
        $estadoIds = Estado::pluck('id', 'nombre');

        // Pool de estados con variedad (antes quedaban todos hardcodeados en "aprobado").
        $pool = ['borrador', 'borrador', 'validado', 'validado', 'validado',
            'aprobado', 'aprobado', 'aprobado', 'aprobado', 'borrado'];

        // Distribución de controles por riesgo sobre 15 riesgos:
        // 20% (3) → 0 controles
        // 30% (5) → 1 control    (aprox: usamos 4 para llegar a 15 exacto)
        // 40% (6) → 2 controles
        // 10% (2) → 3 controles
        // Total: 3+4+6+2 = 15
        $distribucion = array_merge(
            array_fill(0, 3, 0),
            array_fill(0, 4, 1),
            array_fill(0, 6, 2),
            array_fill(0, 2, 3),
        );
        shuffle($distribucion);

        // 15 controles con nombre/descripción propios (uno por cada control que
        // genera la distribución de arriba), para que no queden como "Control 01".
        $controles = [
            ['nombre' => 'Revisión periódica de accesos privilegiados', 'descripcion' => 'Revisar trimestralmente los permisos de usuarios con accesos elevados.'],
            ['nombre' => 'Doble factor de autenticación',              'descripcion' => 'Exigir segundo factor para el ingreso a sistemas críticos.'],
            ['nombre' => 'Cifrado de datos en reposo',                 'descripcion' => 'Cifrar la información sensible almacenada en bases de datos.'],
            ['nombre' => 'Conciliación diaria de cuentas',             'descripcion' => 'Conciliar movimientos bancarios contra los registros contables cada día.'],
            ['nombre' => 'Segregación de funciones en pagos',          'descripcion' => 'Separar quién solicita, autoriza y ejecuta los pagos.'],
            ['nombre' => 'Backup automático diario',                   'descripcion' => 'Respaldar automáticamente los datos productivos todas las noches.'],
            ['nombre' => 'Monitoreo de logs de seguridad',             'descripcion' => 'Analizar los registros de eventos en busca de actividad sospechosa.'],
            ['nombre' => 'Aprobación dual de transacciones',           'descripcion' => 'Requerir dos aprobadores para transacciones por encima del umbral.'],
            ['nombre' => 'Gestión mensual de parches',                 'descripcion' => 'Aplicar las actualizaciones de seguridad publicadas cada mes.'],
            ['nombre' => 'Política de contraseñas robustas',           'descripcion' => 'Imponer complejidad y rotación mínima de contraseñas.'],
            ['nombre' => 'Control de cambios en producción',           'descripcion' => 'Documentar y aprobar todo cambio antes de pasarlo a producción.'],
            ['nombre' => 'Revisión legal de contratos',                'descripcion' => 'Someter los contratos a validación del área legal antes de firmar.'],
            ['nombre' => 'Plan de continuidad probado',                'descripcion' => 'Mantener y ensayar el plan de recuperación ante desastres.'],
            ['nombre' => 'Auditoría trimestral de firewall',           'descripcion' => 'Revisar las reglas de firewall y depurar las obsoletas.'],
            ['nombre' => 'Capacitación anual en seguridad',            'descripcion' => 'Formar al personal en buenas prácticas de seguridad una vez al año.'],
        ];

        $controlCounter = 1;

        foreach ($riesgoIds as $idx => $riesgoId) {
            $cantidadControles = $distribucion[$idx] ?? 0;

            for ($j = 0; $j < $cantidadControles; $j++) {
                $user = $users->random();
                $datos = $controles[($controlCounter - 1) % count($controles)];
                $control = Control::create([
                    'nombre' => $datos['nombre'],
                    'descripcion' => $datos['descripcion'],
                    'mitigacion_default' => rand(1, 10),
                    'user_id' => $user->id,
                    'area_id' => $user->area_id,
                    'estado_id' => $estadoIds[$pool[array_rand($pool)]],
                ]);

                $control->riesgos()->attach([
                    $riesgoId => ['mitigacion' => rand(0, 1) ? null : rand(1, 10)],
                ]);

                $controlCounter++;
            }
        }
    }
}
