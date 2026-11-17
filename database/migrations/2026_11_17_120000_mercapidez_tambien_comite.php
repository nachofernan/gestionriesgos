<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mercapidez es gerenta y además la encargada del comité (D-052): se le suma el
 * rol de comité. Si el usuario no existe (otra base), no hace nada. En una carga
 * nueva lo asigna AreaCargaInicialSeeder (MapeoCargaInicial::ROLES_ADICIONALES).
 */
return new class extends Migration
{
    public function up(): void
    {
        $userId = DB::table('users')->where('email', 'mercapidez@example.com')->value('id');
        $rolId = DB::table('roles')->where('name', 'comite')->where('guard_name', 'web')->value('id');

        if ($userId && $rolId) {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $rolId,
                'model_type' => 'App\\Models\\User',
                'model_id' => $userId,
            ]);
        }
    }

    public function down(): void
    {
        $userId = DB::table('users')->where('email', 'mercapidez@example.com')->value('id');
        $rolId = DB::table('roles')->where('name', 'comite')->where('guard_name', 'web')->value('id');

        DB::table('model_has_roles')
            ->where(['role_id' => $rolId, 'model_type' => 'App\\Models\\User', 'model_id' => $userId])
            ->delete();
    }
};
