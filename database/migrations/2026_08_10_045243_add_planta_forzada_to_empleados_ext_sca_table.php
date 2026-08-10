<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FIX E: Agrega el campo 'planta_forzada' a la tabla 'empleados_ext_sca'.
     *
     * RAZÓN DE SER:
     * Este campo booleano (0 o 1) nos permite distinguir cómo fue asignada
     * la planta del empleado:
     *   - 0 (Automático): La planta se asignó masivamente al mapear la 
     *     clasificación de sitec. Si RH cambia la clasificación del empleado 
     *     en sitec, el sistema SCA actualizará esta planta automáticamente.
     *   - 1 (Manual/Forzada): El Admin modificó la planta individualmente en 
     *     la ficha del empleado. El sistema respetará esta decisión y NO la 
     *     sobreescribirá si RH cambia la clasificación en sitec, ni si se 
     *     realiza un re-mapeo masivo (a menos que el admin elija forzarlo).
     */
    public function up(): void
    {
        Schema::table('empleados_ext_sca', function (Blueprint $table) {
            // Agregamos el campo después de id_planta, por defecto 0 (automático)
            $table->boolean('planta_forzada')->default(false)->after('id_planta')->comment('0=Automática (de sitec), 1=Manual (forzada por admin)');
        });
    }

    public function down(): void
    {
        Schema::table('empleados_ext_sca', function (Blueprint $table) {
            $table->dropColumn('planta_forzada');
        });
    }
};