<?php
// config/sca_qr.php
//
// Configuración del sistema SCA-QR.
//
// Esta es la única fuente de verdad para parámetros ajustables del sistema.
// Los valores se leen con config('sca_qr.<key>') desde cualquier parte.
// Para sobreescribir en producción sin tocar este archivo, usar .env:

return [
    // Prefijo de los códigos QR de externos (para distinguirlos de empleados
    // cuyo QR es simplemente su semilla). Se concatena a un UUID v4.
    'prefijo_qr_externo' => env('SCA_PREFIJO_QR_EXTERNO', 'E-'),

    // URL base donde se sirven las fotos de los empleados (sistema SITEC).
    // El proxy /foto-empleado/{id} concatena esta URL con el id del empleado.
    'url_fotos_sitec' => env('URL_FOTOS_SITEC', 'http://172.27.56.154/'),

    // ============================================================
    // REVISIÓN ALEATORIA (F10) — SRS §4.6
    // ============================================================
    // El 10% de los accesos de empleados operativo/administrativo deben ser
    // revisados físicamente por el guardia. Si se sube a 100, TODOS los
    // escaneos quedan en 'pendiente_revision' y se satura al guardia.
    //
    // ⚠️ FIX CRÍTICO C3 (auditoría V6):
    //   Antes estaba hardcodeado en 100 (leftover de debugging). Eso hacía
    //   que el 100% de los escaneos de operativos/admin cayeran a F10,
    //   colapsando el flujo del kiosko. Se cambia a 10 (lo que pide el SRS)
    //   y se permite override por .env para pruebas de stress.
    'exentos_revision'        => env('SCA_EXENTOS_REVISION', []), // IDs de empleados que nunca se sortean
    'probabilidad_revision'   => env('SCA_PROBABILIDAD_REVISION', 20), // % de accesos sorteado (1-100)
    'minutos_timeout_revision'=> env('SCA_TIMEOUT_REVISION', 5),  // minutos máximos antes de descartar un pendiente
];