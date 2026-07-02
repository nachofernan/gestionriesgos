<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\Auditoria\EstadoRiesgo;
use App\Models\User;

class RiesgoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos  = TipoRiesgo::pluck('id')->toArray();
        $users  = User::where('id', '>', 1)->whereNotNull('area_id')->get();

        // 15 riesgos: 2 borrado, 2 borrador, 6 aprobado, 5 validado
        $estadoDistribucion = [
            'borrado'  => 2,
            'borrador' => 2,
            'aprobado' => 6,
            'validado' => 5,
        ];

        $estadoIds = EstadoRiesgo::pluck('id', 'nombre')->toArray();

        $estadosAsignados = [];
        foreach ($estadoDistribucion as $nombre => $cantidad) {
            for ($j = 0; $j < $cantidad; $j++) {
                $estadosAsignados[] = $estadoIds[$nombre];
            }
        }

        shuffle($estadosAsignados);

        $nombres = [
            'Fallo en controles de acceso lógico',
            'Pérdida de datos confidenciales',
            'Fraude interno en procesos de pago',
            'Incumplimiento regulatorio tributario',
            'Interrupción de sistemas críticos',
            'Error en conciliaciones bancarias',
            'Fuga de información de clientes',
            'Desactualización de parches de seguridad',
            'Incumplimiento de política de contraseñas',
            'Errores en reportes financieros',
            'Acceso no autorizado a servidores',
            'Falta de segregación de funciones',
            'Contratos sin revisión legal',
            'Riesgo de continuidad operativa',
            'Deficiencias en auditoría de logs',
        ];

        foreach ($nombres as $i => $nombre) {
            $user = $users->random();
            Riesgo::create([
                'codigo'           => 'RSG-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'nombre'           => $nombre,
                'descripcion'      => "Descripción del riesgo: {$nombre}.",
                'impacto'          => rand(1, 10),
                'probabilidad'     => rand(1, 10),
                'mayor_criticidad'  => false,
                'tipo_riesgo_id'   => $tipos[array_rand($tipos)],
                'estado_id'         => $estadosAsignados[$i],
                'user_id'          => $user->id,
                'area_id'          => $user->area_id,
            ]);
        }
    }
}
