<?php

namespace Database\Seeders\CargaInicial;

use RuntimeException;

/**
 * Registro central de la carga inicial: el árbol de áreas real de la empresa
 * (código de área del Excel → nombre/padre/gerencia/responsable, ver
 * docs/reconstruccion.md sección 2) y los mapeos código→id resueltos en runtime
 * a medida que cada seeder de CargaInicial va creando filas. Existe para que
 * Riesgo/Control/Objetivo/PlanAccion/Tarea puedan resolver el área y el usuario
 * en texto que trae cada CSV contra la fila real ya creada, sin repetir la
 * lógica de mapeo en cada seeder.
 */
class MapeoCargaInicial
{
    /**
     * Árbol de áreas reconstruido desde UsuariosAreas.csv, con CR (Comité de
     * Riesgo) como raíz agregada — mismo patrón que ya usa AreaSeeder.php para el
     * nodo "Comité de Riesgo". Declarado en orden topológico (todo padre aparece
     * antes que sus hijos) porque AreaCargaInicialSeeder recorre este array en
     * orden para crear las áreas.
     */
    public const AREAS = [
        'CR' => ['nombre' => 'Comité de Riesgo', 'padre' => null, 'gerencia' => true, 'responsable' => 'Munafo'],

        'CYD' => ['nombre' => 'Comercialización y Despacho', 'padre' => 'CR', 'gerencia' => true, 'responsable' => 'Doncheff'],
        'CYDC' => ['nombre' => 'Comercialización y Despacho Combustible', 'padre' => 'CYD', 'gerencia' => false, 'responsable' => 'Doncheff'],
        'CYDS' => ['nombre' => 'Comercialización y Despacho SMEC', 'padre' => 'CYD', 'gerencia' => false, 'responsable' => 'Doncheff'],
        'CYDG' => ['nombre' => 'Comercialización y Despacho COG', 'padre' => 'CYD', 'gerencia' => false, 'responsable' => 'Doncheff'],
        'CYDE' => ['nombre' => 'Comercialización y Despacho Economía Energética', 'padre' => 'CYD', 'gerencia' => false, 'responsable' => 'Doncheff'],

        'PRO' => ['nombre' => 'Gerencia de Producción', 'padre' => 'CR', 'gerencia' => true, 'responsable' => 'Grassi'],

        'GAL' => ['nombre' => 'Gerencia de Asuntos Legales', 'padre' => 'CR', 'gerencia' => true, 'responsable' => 'Langus'],

        'GAYF' => ['nombre' => 'Gerencia de Administración y Finanzas', 'padre' => 'CR', 'gerencia' => true, 'responsable' => 'Piris'],
        'GAYFF' => ['nombre' => 'Gerencia de Adm. y Fin. Coord. Financiera', 'padre' => 'GAYF', 'gerencia' => false, 'responsable' => 'Biasi'],
        'GAYFFT' => ['nombre' => 'Tesorería', 'padre' => 'GAYFF', 'gerencia' => false, 'responsable' => 'Biasi'],
        'GAYFFI' => ['nombre' => 'Impuestos', 'padre' => 'GAYFF', 'gerencia' => false, 'responsable' => 'Biasi'],
        'GAYFFC' => ['nombre' => 'Contabilidad', 'padre' => 'GAYFF', 'gerencia' => false, 'responsable' => 'Biasi'],
        'GAYFFZ' => ['nombre' => 'Cobranzas', 'padre' => 'GAYFF', 'gerencia' => false, 'responsable' => 'Biasi'],
        'GAYFFP' => ['nombre' => 'Presupuesto', 'padre' => 'GAYFF', 'gerencia' => false, 'responsable' => 'Biasi'],
        'GAYFS' => ['nombre' => 'Gerencia de Adm. y Fin. Coord. Sistemas', 'padre' => 'GAYF', 'gerencia' => false, 'responsable' => 'Aieta'],
        'GAYFA' => ['nombre' => 'Gerencia de Adm. y Fin. Coord. Administrativa', 'padre' => 'GAYF', 'gerencia' => false, 'responsable' => 'Berri'],
        'GAYFAC' => ['nombre' => 'Compras', 'padre' => 'GAYFA', 'gerencia' => false, 'responsable' => 'Berri'],
        'GAYFACP' => ['nombre' => 'Cuentas a Pagar', 'padre' => 'GAYFA', 'gerencia' => false, 'responsable' => 'Berri'],
        'GAYFACE' => ['nombre' => 'Comercio Exterior', 'padre' => 'GAYFA', 'gerencia' => false, 'responsable' => 'Berri'],
        'GAYFAA' => ['nombre' => 'Automotores', 'padre' => 'GAYFA', 'gerencia' => false, 'responsable' => 'Berri'],

        'RRHH' => ['nombre' => 'Coord. Gral. de RRHH', 'padre' => 'CR', 'gerencia' => true, 'responsable' => 'Pasquale'],

        'OYM' => ['nombre' => 'Organización y Métodos', 'padre' => 'CR', 'gerencia' => true, 'responsable' => 'Stefanelli'],

        'MASH' => ['nombre' => 'Medio Ambiente, Seguridad e Higiene', 'padre' => 'CR', 'gerencia' => true, 'responsable' => 'Fasano'],
        'MASHS' => ['nombre' => 'Seguridad e Higiene', 'padre' => 'MASH', 'gerencia' => false, 'responsable' => 'Uva'],
        'MASHMA' => ['nombre' => 'Medio Ambiente', 'padre' => 'MASH', 'gerencia' => false, 'responsable' => 'Torres'],

        'UAI' => ['nombre' => 'Auditoría Interna al PI', 'padre' => 'CR', 'gerencia' => true, 'responsable' => 'Castiglioni'],

        'PRE' => ['nombre' => 'Prensa', 'padre' => 'CR', 'gerencia' => true, 'responsable' => 'Aversa'],

        'FC' => ['nombre' => 'Fortalecimiento Institucional', 'padre' => 'CR', 'gerencia' => true, 'responsable' => 'Mercapidez'],
    ];

    /**
     * Apellidos que aparecen en los CSV haciendo tareas puntuales sin ser
     * responsables de ningún área (ver docs/reconstruccion.md sección 3):
     * apellido => código del área bajo la que quedan.
     */
    public const USUARIOS_SIN_AREA_PROPIA = [
        'Nicolini' => 'RRHH',
        'Martin' => 'GAL',
    ];

    /**
     * Correcciones de datos detectadas en el Excel (ver docs/reconstruccion.md):
     * "ALE" es un error de tipeo por "GAL" (Langus es responsable de ambas en el
     * dataset, GAL es la que existe en UsuariosAreas.csv).
     */
    public const CORRECCIONES_CODIGO_AREA = [
        'ALE' => 'GAL',
    ];

    /** Variación de mayúsculas del mismo apellido detectada en Tareas.csv. */
    public const CORRECCIONES_APELLIDO = [
        'MErcapidez' => 'Mercapidez',
    ];

    /** @var array<string, int> código de área → id real */
    public static array $areaIdPorCodigo = [];

    /** @var array<string, int> apellido → id real de usuario */
    public static array $userIdPorApellido = [];

    /** @var array<int, int> id numérico de TipoRiesgos.csv → id real */
    public static array $tipoRiesgoIdPorCsvId = [];

    /** @var array<int, int> id de Riesgos.csv → id real */
    public static array $riesgoIdPorCsvId = [];

    /** @var array<int, int> id de PlanesAccion.csv → id real */
    public static array $planAccionIdPorCsvId = [];

    /** @var array<int, int> id de Tareas.csv → id real */
    public static array $tareaIdPorCsvId = [];

    /** @var array<int, int> id de PeisItems.csv → id real */
    public static array $peisItemIdPorCsvId = [];

    /** @var array<string, int> nombre de estado → id */
    public static array $estadoIdPorNombre = [];

    public static function area(string $codigo): int
    {
        $codigo = self::CORRECCIONES_CODIGO_AREA[$codigo] ?? $codigo;

        return self::$areaIdPorCodigo[$codigo]
            ?? throw new RuntimeException("Área sin mapear: {$codigo}");
    }

    public static function usuario(string $apellido): int
    {
        $apellido = self::CORRECCIONES_APELLIDO[$apellido] ?? $apellido;

        return self::$userIdPorApellido[$apellido]
            ?? throw new RuntimeException("Usuario sin mapear: {$apellido}");
    }

    public static function estado(string $nombre): int
    {
        return self::$estadoIdPorNombre[$nombre]
            ?? throw new RuntimeException("Estado sin mapear: {$nombre}");
    }
}
