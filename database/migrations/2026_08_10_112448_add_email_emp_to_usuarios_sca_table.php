<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios_sca', function (Blueprint $table) {
            // FIX I-10: Agregar correo institucional del empleado.
            // Es obligatorio para enviar credenciales y reseteos.
            $table->string('email_emp', 100)->nullable()->after('username')->comment('Correo institucional de la empresa');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios_sca', function (Blueprint $table) {
            $table->dropColumn('email_emp');
        });
    }
};