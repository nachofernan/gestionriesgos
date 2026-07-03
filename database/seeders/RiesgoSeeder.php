<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;

class RiesgoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos       = TipoRiesgo::pluck('id')->toArray();
        $users       = User::where('id', '>', 1)->whereNotNull('area_id')->get();
        $objetivoIds = Objetivo::pluck('id')->toArray();
        $respuestas  = RespuestaRiesgo::cases();

        // 15 riesgos: 2 borrado, 3 borrador, 5 validado, 5 aprobado
        $estadoDistribucion = [
            'borrado'  => 2,
            'borrador' => 3,
            'validado' => 5,
            'aprobado' => 5,
        ];

        $estadoIds = Estado::pluck('id', 'nombre')->toArray();

        $estadosAsignados = [];
        foreach ($estadoDistribucion as $nombre => $cantidad) {
            for ($j = 0; $j < $cantidad; $j++) {
                $estadosAsignados[] = $nombre;
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
            $user         = $users->random();
            $estadoNombre = $estadosAsignados[$i];
            $impacto      = rand(0, 10);
            $probabilidad = rand(0, 10);

            $riesgo = Riesgo::create([
                'codigo'           => 'RSG-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'nombre'           => $nombre,
                'descripcion'      => "Descripción del riesgo: {$nombre}.",
                'impacto'          => $impacto,
                'probabilidad'     => $probabilidad,
                'mayor_criticidad' => ($impacto + $probabilidad) >= 14 && (bool) rand(0, 1),
                'respuesta'        => $respuestas[array_rand($respuestas)],
                'tipo_riesgo_id'   => $tipos[array_rand($tipos)],
                'estado_id'        => $estadoIds[$estadoNombre],
                'user_id'          => $user->id,
                'area_id'          => $user->area_id,
            ]);

            // motivosBloqueoValidacion(): validar exige al menos un objetivo asociado,
            // así que todo riesgo validado/aprobado lo tiene garantizado; en
            // borrador/borrado es variable, para que se vea variedad real.
            $tieneObjetivo = in_array($estadoNombre, ['validado', 'aprobado']) || (bool) rand(0, 1);
            if ($tieneObjetivo) {
                $cantidad = rand(1, min(3, count($objetivoIds)));
                $keys     = (array) array_rand($objetivoIds, $cantidad);
                $riesgo->objetivos()->attach(array_map(fn($k) => $objetivoIds[$k], $keys));
            }
        }
    }
}
