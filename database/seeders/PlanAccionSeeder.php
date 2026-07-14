<?php

namespace Database\Seeders;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Illuminate\Database\Seeder;

class PlanAccionSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('id', '>', 1)->whereNotNull('area_id')->get();
        $riesgoIds = Riesgo::pluck('id')->toArray();
        $tareaIds = Tarea::pluck('id')->toArray();
        $estadoIds = Estado::pluck('id', 'nombre');

        // 4 planes con variedad de estados (antes quedaban todos hardcodeados en "aprobado").
        $estadosPlan = ['borrador', 'validado', 'aprobado', 'aprobado'];

        // Nombres/descripciones propios, para que no queden como "Plan de Acción 01".
        $planes = [
            ['nombre' => 'Fortalecimiento de accesos y autenticación',   'descripcion' => 'Reforzar los controles de acceso lógico y el doble factor.'],
            ['nombre' => 'Mejora de controles financieros',             'descripcion' => 'Robustecer la conciliación de cuentas y la segregación de funciones.'],
            ['nombre' => 'Actualización de seguridad de infraestructura', 'descripcion' => 'Poner al día el parcheo, el cifrado y el monitoreo de logs.'],
            ['nombre' => 'Continuidad operativa y cumplimiento',         'descripcion' => 'Asegurar el plan de continuidad y la revisión legal de contratos.'],
        ];

        for ($i = 1; $i <= 4; $i++) {
            $user = $users->random();
            $datos = $planes[$i - 1];
            $plan = PlanAccion::create([
                'codigo' => 'PLAN-'.str_pad($i, 3, '0', STR_PAD_LEFT),
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'],
                'user_id' => $user->id,
                'area_id' => $user->area_id,
                'estado_id' => $estadoIds[$estadosPlan[$i - 1]],
            ]);

            // 1-3 riesgos
            $rSample = (array) array_rand($riesgoIds, rand(1, min(3, count($riesgoIds))));
            $plan->riesgos()->attach(array_map(fn ($idx) => $riesgoIds[$idx], $rSample));

            // 3-5 tareas
            $tSample = (array) array_rand($tareaIds, rand(3, min(5, count($tareaIds))));
            $plan->tareas()->attach(array_map(fn ($idx) => $tareaIds[$idx], $tSample));
        }

        // Riesgo::motivosBloqueoValidacion(): un riesgo con respuesta "mitigar" ya
        // validado/aprobado exige al menos un plan de acción asociado. Los planes recién
        // existen a partir de acá, así que se garantiza como fixup al final del seeder.
        $planIds = PlanAccion::pluck('id')->toArray();
        Riesgo::where('respuesta', RespuestaRiesgo::Mitigar->value)
            ->whereHas('estado', fn ($q) => $q->whereIn('nombre', ['validado', 'aprobado']))
            ->whereDoesntHave('planesAccion')
            ->get()
            ->each(fn ($riesgo) => $riesgo->planesAccion()->attach($planIds[array_rand($planIds)]));
    }
}
