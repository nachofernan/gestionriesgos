<?php

namespace Database\Seeders;

use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Illuminate\Database\Seeder;

class TareaSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('id', '>', 1)->whereNotNull('area_id')->get();
        $estadoIds = Estado::pluck('id', 'nombre');

        // Pool de estados con variedad (antes quedaban todos hardcodeados en "aprobado").
        $pool = ['borrador', 'borrador', 'borrador', 'validado', 'validado', 'validado',
            'aprobado', 'aprobado', 'aprobado', 'aprobado', 'aprobado', 'borrado'];

        // 15 tareas con nombre/descripción propios, para que no queden como "Tarea 01".
        $tareas = [
            ['nombre' => 'Relevar accesos vigentes por sistema',        'descripcion' => 'Inventariar los usuarios y permisos activos en cada aplicación.'],
            ['nombre' => 'Implementar doble factor en el portal',       'descripcion' => 'Habilitar el segundo factor de autenticación en el portal interno.'],
            ['nombre' => 'Configurar cifrado en la base de datos',      'descripcion' => 'Activar el cifrado de las tablas con información sensible.'],
            ['nombre' => 'Documentar el proceso de conciliación',       'descripcion' => 'Redactar el instructivo de conciliación diaria de cuentas.'],
            ['nombre' => 'Definir la matriz de segregación de funciones', 'descripcion' => 'Mapear roles incompatibles en el circuito de pagos.'],
            ['nombre' => 'Verificar la restauración de backups',        'descripcion' => 'Probar la recuperación de un respaldo reciente en entorno aislado.'],
            ['nombre' => 'Instalar la herramienta de monitoreo de logs', 'descripcion' => 'Desplegar la solución de centralización y alertado de logs.'],
            ['nombre' => 'Revisar el circuito de aprobación de pagos',  'descripcion' => 'Auditar los umbrales y aprobadores del proceso de pagos.'],
            ['nombre' => 'Ejecutar la campaña de parcheo',              'descripcion' => 'Aplicar los parches pendientes en servidores y estaciones.'],
            ['nombre' => 'Actualizar la política de contraseñas',       'descripcion' => 'Revisar y reforzar los requisitos de complejidad y rotación.'],
            ['nombre' => 'Formalizar el control de cambios',            'descripcion' => 'Escribir el procedimiento de aprobación de cambios a producción.'],
            ['nombre' => 'Enviar contratos a revisión legal',          'descripcion' => 'Derivar los contratos pendientes al área legal para su validación.'],
            ['nombre' => 'Probar el plan de continuidad',              'descripcion' => 'Ejecutar un simulacro del plan de recuperación ante desastres.'],
            ['nombre' => 'Auditar las reglas de firewall',            'descripcion' => 'Revisar y depurar las reglas obsoletas del firewall perimetral.'],
            ['nombre' => 'Dictar la capacitación de seguridad',        'descripcion' => 'Coordinar y dictar la formación anual de concientización.'],
        ];

        for ($i = 1; $i <= 15; $i++) {
            $user = $users->random();
            $estadoNombre = $pool[array_rand($pool)];

            // Avance y fecha correlacionados con el estado: una tarea aprobada ya se
            // completó, una en borrador recién arranca. Un ~20% de las no aprobadas
            // queda "vencida" (fecha pasada con avance incompleto) para poder ver el
            // indicador de vencimiento de la vista (search.blade.php) con datos reales.
            $vencida = $estadoNombre !== 'aprobado' && rand(1, 100) <= 20;

            $porcentajeAvance = match ($estadoNombre) {
                'aprobado' => 100,
                'validado' => rand(60, 99),
                default => rand(0, 50),
            };

            $fecha = $vencida
                ? now()->subDays(rand(1, 60))
                : now()->addDays(rand(7, 180));

            $datos = $tareas[($i - 1) % count($tareas)];

            Tarea::create([
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'],
                'fecha' => $fecha->toDateString(),
                'porcentaje_avance' => $porcentajeAvance,
                'user_id' => $user->id,
                'area_id' => $user->area_id,
                'estado_id' => $estadoIds[$estadoNombre],
            ]);
        }
    }
}
