<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Todo rechazo lleva motivo (D-031): el de una propuesta y el registro del rechazo de un elemento. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actualizaciones', function (Blueprint $table) {
            $table->text('motivo_rechazo')->nullable()->after('rechazado_en');
        });
    }

    public function down(): void
    {
        Schema::table('actualizaciones', function (Blueprint $table) {
            $table->dropColumn('motivo_rechazo');
        });
    }
};
