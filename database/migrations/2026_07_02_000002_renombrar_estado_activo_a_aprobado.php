<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('estados')->where('nombre', 'activo')->update(['nombre' => 'aprobado']);
    }

    public function down(): void
    {
        DB::table('estados')->where('nombre', 'aprobado')->update(['nombre' => 'activo']);
    }
};
