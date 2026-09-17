<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ============================================================
// MÓDULO DE CONFIGURACIÓN DEL SISTEMA (Fase 1)
// ============================================================
// Tabla clave/valor que persiste los parámetros operativos que
// antes vivían hardcodeados (constantes de AccesoService) o en
// config/sca_qr.php (prob. de revisión, tolerancias de horario).
//
// ¿Por qué tabla y no .env?
//   - .env requiere acceso SSH + config:clear; un Admin no puede.
//   - Aquí los cambios aplican EN CALIENTE desde la UI y quedan
//     auditados (quién, cuándo, valor viejo → nuevo).
//
// ¿Por qué clave/valor y no columnas?
//   - Agregar un parámetro nuevo = solo registrarlo en el catálogo
//     ConfigSistemaService::DEFECTOS; esta tabla NUNCA cambia.
//   - Solo se guardan DESVIACIONES del default (si el valor equals
//     default, se borra la fila) → la tabla queda mínima.
//
// La cadena de lectura es: cache → esta tabla → default en código.
// Si la tabla está vacía o no existe, el sistema se comporta
// EXACTAMENTE como hoy (cero riesgo de arranque).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_sistema', function (Blueprint $table) {
            $table->id('id_configuracion');
            // Clave del parámetro (ej: 'probabilidad_revision').
            // UNIQUE: una fila por parámetro, updateOrCreate garantiza eso.
            $table->string('clave', 60)->unique();
            // Valor SIEMPRE en texto; el service lo tipa al leer
            // (int / lista de IDs). Así un solo tipo de columna sirve
            // para todos los parámetros presentes y futuros.
            $table->string('valor', 500);
            // Quién modificó el valor (id de usuarios_sca). Se usa solo
            // para mostrar "modificado por X" en la vista. Sin FK física
            // a propósito: si el usuario se da de baja, la configuración
            // NO debe borrarse (el valor es un dato del sistema, no del
            // usuario) y así evitamos dependencias frágiles de tipos.
            $table->unsignedBigInteger('actualizado_por')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_sistema');
    }
};