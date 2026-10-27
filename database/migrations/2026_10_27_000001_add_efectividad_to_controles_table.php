<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Efectividad del control (D-036): porcentaje 0-100 de su mitigación que se aplica de verdad. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controles', function (Blueprint $table) {
            $table->unsignedTinyInteger('efectividad')->default(100)->after('mitigacion_default');
        });
    }

    public function down(): void
    {
        Schema::table('controles', function (Blueprint $table) {
            $table->dropColumn('efectividad');
        });
    }
};
