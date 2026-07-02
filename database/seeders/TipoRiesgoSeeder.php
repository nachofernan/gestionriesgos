<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TipoRiesgoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $tipos = [
            [
                'nombre' => 'Operacional',
                'descripcion' => 'Se define al riesgo operacional como el riesgo de pérdida debido a la inadecuación o el fallo de los procedimientos, las personas y los sistemas internos o a acontecimientos externos',
            ],
            [
                'nombre' => 'Económico',
                'descripcion' => 'Se define al riesgo económico como el riesgo de pérdida que surge deposibles eventualidades que pueden afectar al resultado de explotación de una empresa, que hacen que no se pueda garantizar ese resultado a lo largo del tiempo. Por ejemplo, disminución/incremento de ingresos, costos, margen bruto',
            ],
            [
                'nombre' => 'Cumplimiento',
                'descripcion' => 'El riesgo de cumplimiento es el riesgo de recibir sanciones, incluso económicas, o de ser objeto de otro tipo de medidas disciplinarias por parte de organismos supervisores como resultado de incumplir las leyes, regulaciones, normas, etc.',
            ],
            [
                'nombre' => 'Ambiental',
                'descripcion' => 'El riesgo ambiental es la posibilidad de que se produzca un daño o catástrofe en el medio ambiente debido a un fenómeno natural o a una acción humana.',
            ],
            [
                'nombre' => 'Seguridad e Higiene',
                'descripcion' => 'Es el riesgo de accidentes y enfermedades a que están expuestos los trabajadores en ejercicio o con motivo del trabajo.',
            ],
            [
                'nombre' => 'Financiero',
                'descripcion' => 'Son todos aquellos relacionados con la gestión financiera de la empresa, es decir aquellos movimientos, transacciones y demás elementos que tienen influencia en las finanzas empresariales: inversión, diversificación, expansión, financiación, entre otros',
            ],
            [
                'nombre' => 'Corrupción',
                'descripcion' => 'Es el riesgo de uso de los bienes públicos para beneficio privado, de una conducta deshonesta o fraudulenta de quienes ostentan poder, que suelen implicar sobornos y/o el abuso de poder encomendado para obtener beneficios',
            ], 
        ];
        
        foreach ($tipos as $tipo) {
            \App\Models\Auditoria\TipoRiesgo::create($tipo);
        }
    }
}
