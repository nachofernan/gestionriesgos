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
        $tareas = Tarea::with('estado')->get();
        $estadoIds = Estado::pluck('id', 'nombre');

        // 4 planes con variedad de estados (antes quedaban todos hardcodeados en "aprobado").
        $estadosPlan = ['borrador', 'validado', 'aprobado', 'aprobado'];

        // Estados de tarea coherentes con el estado del plan que las contiene: un plan no
        // puede estar más avanzado en el ciclo de vida que sus tareas (aprobado con tareas
        // en borrador), ni al revés (borrador con tareas ya aprobadas). Un plan aprobado sí
        // puede tener tareas en curso (validado) y terminadas (aprobado) mezcladas. Ninguna
        // tarea "borrado" cuelga de un plan vivo.
        $tareasCompatibles = [
            'borrador' => ['borrador', 'validado'],
            'validado' => ['validado', 'aprobado'],
            'aprobado' => ['validado', 'aprobado'],
        ];

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
            $estadoPlan = $estadosPlan[$i - 1];
            $plan = PlanAccion::create([
                'codigo' => 'PLAN-'.str_pad($i, 3, '0', STR_PAD_LEFT),
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'],
                'user_id' => $user->id,
                'area_id' => $user->area_id,
                'estado_id' => $estadoIds[$estadoPlan],
            ]);

            // 1-3 riesgos, cada uno con la mitigación que el plan aplica al llegar al 100%.
            $rSample = (array) array_rand($riesgoIds, rand(1, min(3, count($riesgoIds))));
            $plan->riesgos()->attach(
                collect($rSample)->mapWithKeys(fn ($idx) => [$riesgoIds[$idx] => ['mitigacion' => rand(2, 8)]])->all()
            );

            // 3-5 tareas, pero solo de las compatibles con el estado del plan (ver arriba).
            // Las tareas son many-to-many, así que reusarlas entre planes es válido.
            $elegibles = $tareas->whereIn('estado.nombre', $tareasCompatibles[$estadoPlan]);
            $plan->tareas()->attach(
                $elegibles->random(min(rand(3, 5), $elegibles->count()))->pluck('id')->all()
            );
        }

        // Riesgo::motivosBloqueoValidacion(): un riesgo con respuesta "mitigar" ya
        // validado/aprobado exige al menos un plan de acción asociado. Los planes recién
        // existen a partir de acá, así que se garantiza como fixup al final del seeder.
        // Se prefiere un plan validado/aprobado (un riesgo validado con un plan en borrador
        // como única mitigación quedaría flojo); si no hubiera, cae en cualquiera.
        $planesReales = PlanAccion::whereHas('estado', fn ($q) => $q->whereIn('nombre', ['validado', 'aprobado']))->pluck('id')->toArray();
        $planIds = $planesReales ?: PlanAccion::pluck('id')->toArray();
        Riesgo::where('respuesta', RespuestaRiesgo::Mitigar->value)
            ->whereHas('estado', fn ($q) => $q->whereIn('nombre', ['validado', 'aprobado']))
            ->whereDoesntHave('planesAccion')
            ->get()
            ->each(fn ($riesgo) => $riesgo->planesAccion()->attach($planIds[array_rand($planIds)], ['mitigacion' => rand(2, 8)]));
    }
}
