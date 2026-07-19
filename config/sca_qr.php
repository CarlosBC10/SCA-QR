<?php
// config/sca_qr.php

return [
    // Prefijo de los QR para externos
    'prefijo_qr_externo' => 'E-',
    
    // URL base donde están las fotos de los empleados en sitec
    'url_fotos_sitec' => env('URL_FOTOS_SITEC', 'http://172.27.56.154/'),
    
    // Revisión aleatoria
    'exentos_revision' => [],
    'probabilidad_revision' => 10,
    'minutos_timeout_revision' => 5,
    
    // NUEVO: PIN para activar el modo kiosko (cámbialo cuando quieras)
    'pin_kiosko' => env('PIN_KIOSKO', '2580'),
];