<?php
// config/sca_qr.php

return [
    // Prefijo de los QR para externos
    'prefijo_qr_externo' => 'E-',
    
    // URL base donde están las fotos de los empleados en sitec
    'url_fotos_sitec' => env('URL_FOTOS_SITEC', 'http://172.27.56.154/'),
    
    // Revisión aleatoria
    'exentos_revision' => [],
    'probabilidad_revision' => 100,
    'minutos_timeout_revision' => 5,
];