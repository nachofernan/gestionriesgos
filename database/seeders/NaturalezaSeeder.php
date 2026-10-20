<?php

namespace Database\Seeders;

use App\Models\Auditoria\Naturaleza;
use Illuminate\Database\Seeder;

/** Las naturalezas del riesgo con sus ejemplos y referencias (D-032). Idempotente. */
class NaturalezaSeeder extends Seeder
{
    public const NATURALEZAS = [
        'Derechos humanos y estándares laborales' => [
            'ejemplos' => 'Seguridad y salud en el trabajo, EPP, protección contra el acoso o violencia laboral, diversidad de género, no discriminación, salario justo, trabajo digno, respeto a las comunidades, prohibición del trabajo infantil, libertad de asociación, etc.',
            'referencias' => [
                'Las empresas deben apoyar y respetar la protección de los derechos humanos proclamados internacionalmente.',
                'Las empresas deben asegurarse de no ser cómplices de la vulneración de los derechos humanos.',
                'Las empresas deben apoyar la libertad de afiliación y el reconocimiento efectivo del derecho a la negociación colectiva.',
                'Las empresas deben apoyar la eliminación de toda forma de trabajo forzoso o realizado bajo coacción.',
                'Las empresas deben apoyar la erradicación del trabajo infantil.',
                'Las empresas deben apoyar la abolición de las prácticas de discriminación en el empleo y la ocupación.',
            ],
        ],
        'Medioambiente' => [
            'ejemplos' => 'Reducción del consumo de recursos no renovables, separación de residuos, reutilización, utilización o promoción de energías renovables, ahorro de energía eléctrica/agua, etc.',
            'referencias' => [
                'Las empresas deberán mantener un enfoque preventivo que favorezca el medioambiente.',
                'Las empresas deben fomentar iniciativas que promuevan una mayor responsabilidad ambiental.',
                'Las empresas deben favorecer el desarrollo y la difusión de las tecnologías respetuosas con el medioambiente.',
            ],
        ],
        'Anticorrupción' => [
            'ejemplos' => 'Promoción de la transparencia e integridad, capacitaciones en temas de integridad, canales de denuncia, políticas y procedimientos, debida diligencia, prevención de delitos de la ley 27.401, etc.',
            'referencias' => [
                'Las empresas deben trabajar contra la corrupción en todas sus formas, incluidas extorsión y soborno.',
            ],
        ],
        'Gobernanza' => [
            'ejemplos' => 'Estructura, funciones, responsabilidades, gestión, identificación de riesgos, políticas y procedimientos, compromiso de la Alta Dirección, etc.',
            'referencias' => [
                // El texto original llegó cortado: falta completarlo.
                'Presenta una perspectiva transversal de la estructura de gobernanza de sostenibilidad de las empresas.',
            ],
        ],
    ];

    /**
     * Naturaleza que se infiere del tipo de riesgo, para los riesgos que se
     * cargan sin ella (carga inicial y datos de desarrollo).
     */
    public static function paraTipo(?string $nombreTipo): string
    {
        return match ($nombreTipo) {
            'Ambiental' => 'Medioambiente',
            'Corrupción' => 'Anticorrupción',
            'Seguridad e Higiene' => 'Derechos humanos y estándares laborales',
            default => 'Gobernanza',
        };
    }

    public function run(): void
    {
        foreach (self::NATURALEZAS as $nombre => $datos) {
            $naturaleza = Naturaleza::firstOrCreate(['nombre' => $nombre], ['ejemplos' => $datos['ejemplos']]);

            if ($naturaleza->referencias()->doesntExist()) {
                foreach ($datos['referencias'] as $orden => $texto) {
                    $naturaleza->referencias()->create(['texto' => $texto, 'orden' => $orden]);
                }
            }
        }
    }
}
