<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los roles pasan de la columna users.rol a spatie/laravel-permission (D-052),
 * para que un usuario pueda tener más de uno (gerente + comité). Cada usuario
 * conserva el rol que tenía; el down() rearma la columna con el primero.
 */
return new class extends Migration
{
    private const ROLES = ['gerente', 'comite', 'empleado', 'auditor'];

    public function up(): void
    {
        $ahora = now();
        foreach (self::ROLES as $rol) {
            DB::table('roles')->insertOrIgnore(['name' => $rol, 'guard_name' => 'web', 'created_at' => $ahora, 'updated_at' => $ahora]);
        }
        $rolId = DB::table('roles')->pluck('id', 'name');

        DB::table('users')->select('id', 'rol')->orderBy('id')->each(function ($user) use ($rolId) {
            if (isset($rolId[$user->rol])) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $rolId[$user->rol],
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $user->id,
                ]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rol');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol')->default('empleado')->after('area_id');
        });

        $asignaciones = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_type', 'App\\Models\\User')
            ->orderBy('roles.id')
            ->get(['model_id', 'roles.name']);

        foreach ($asignaciones->unique('model_id') as $asignacion) {
            DB::table('users')->where('id', $asignacion->model_id)->update(['rol' => $asignacion->name]);
        }

        DB::table('model_has_roles')->where('model_type', 'App\\Models\\User')->delete();
        DB::table('roles')->whereIn('name', self::ROLES)->delete();
    }
};
