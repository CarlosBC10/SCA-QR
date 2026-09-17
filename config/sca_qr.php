<?php
// config/sca_qr.php
//
// Configuración FIJA del sistema SCA-QR (no editable en runtime).
//
// ⚠️ MÓDULO DE CONFIGURACIÓN (Fase 1): los parámetros operativos
// que antes vivían aquí (probabilidad_revision, exentos_revision,
// minutos_timeout_revision) MIGRARON a la tabla configuracion_sistema
// y se administran desde /configuracion (Admin/Superadmin).
// Única fuente de verdad de esos parámetros:
//   app/Services/ConfigSistemaService.php (catálogo DEFECTOS).
// Si actualizas desde una versión vieja y tu .env tiene definidas
// SCA_PROBABILIDAD_REVISION / SCA_EXENTOS_REVISION /
// SCA_TIMEOUT_REVISION, ya NO tienen efecto: configúralos en la UI.
//
// Los valores se leen con config('sca_qr.<key>') desde cualquier parte.
// Para sobreescribir en producción sin tocar este archivo, usar .env:

return [
    // Prefijo de los códigos QR de externos (para distinguirlos de empleados
    // cuyo QR es simplemente su semilla). Se concatena a un UUID v4.
    'prefijo_qr_externo' => env('SCA_PREFIJO_QR_EXTERNO', 'E-'),

    // URL base donde se sirven las fotos de los empleados (sistema SITEC).
    // El proxy /foto-empleado/{id} concatena esta URL con el id del empleado.
    'url_fotos_sitec' => env('URL_FOTOS_SITEC', 'http://172.27.56.154/'),
];