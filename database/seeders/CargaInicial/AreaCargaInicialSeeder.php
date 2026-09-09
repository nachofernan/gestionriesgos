<?php

namespace Database\Seeders\CargaInicial;

use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Area;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea el árbol de áreas real de la empresa (30 áreas: CR + las 29 de
 * UsuariosAreas.csv, ver MapeoCargaInicial::AREAS) y un usuario por cada
 * responsable distinto. El área "home" de cada usuario (users.area_id) es la más
 * alta de la jerarquía que ese apellido tiene a cargo — la que no cuelga de otra
 * área con el mismo responsable — para que la autorización por área
 * (User::puedeGestionarArea) le dé automáticamente el subárbol completo. No está
 * hardcodeada aparte: se calcula acá a partir de MapeoCargaInicial::AREAS, que es
 * la única fuente de verdad del árbol.
 */
class AreaCargaInicialSeeder extends Seeder
{
    public function run(): void
    {
        // Padre antes que hijo: MapeoCargaInicial::AREAS ya está declarado en ese orden.
        foreach (MapeoCargaInicial::AREAS as $codigo => $datos) {
            $padreId = $datos['padre'] ? MapeoCargaInicial::$areaIdPorCodigo[$datos['padre']] : null;

            $area = Area::create([
                'nombre' => $datos['nombre'],
                'area_padre_id' => $padreId,
                'tipo' => $datos['gerencia'] ? TipoArea::Gerencia : null,
            ]);

            MapeoCargaInicial::$areaIdPorCodigo[$codigo] = $area->id;
        }

        $homePorApellido = [];
        foreach (MapeoCargaInicial::AREAS as $codigo => $datos) {
            $padreMismoResponsable = $datos['padre']
                && MapeoCargaInicial::AREAS[$datos['padre']]['responsable'] === $datos['responsable'];

            if (! $padreMismoResponsable) {
                $homePorApellido[$datos['responsable']] = $codigo;
            }
        }

        foreach ($homePorApellido as $apellido => $codigoArea) {
            $rol = $codigoArea === 'CR' ? 'comite' : (MapeoCargaInicial::AREAS[$codigoArea]['gerencia'] ? 'gerente' : 'empleado');
            $this->crearUsuario($apellido, $codigoArea, $rol);
        }

        // Apellidos que solo aparecen en tareas puntuales, sin ser responsables de área.
        foreach (MapeoCargaInicial::USUARIOS_SIN_AREA_PROPIA as $apellido => $codigoArea) {
            $this->crearUsuario($apellido, $codigoArea, 'empleado');
        }
    }

    /**
     * Patrón de email/password heredado literal de AreaSeeder.php: no es una
     * convención nueva inventada para esta carga.
     */
    private function crearUsuario(string $apellido, string $codigoArea, string $rol): void
    {
        $user = User::create([
            'name' => $apellido,
            'email' => strtolower($apellido).'@example.com',
            'password' => Hash::make('password'),
            'area_id' => MapeoCargaInicial::$areaIdPorCodigo[$codigoArea],
            'rol' => $rol,
        ]);

        MapeoCargaInicial::$userIdPorApellido[$apellido] = $user->id;
    }
}
