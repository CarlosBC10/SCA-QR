<?php
// config/auth.php
// Configuración de autenticación.
// Le dice a Laravel: "para loguear usuarios, usa el modelo UsuarioSca
// (que representa la tabla usuarios_sca), no el modelo User por defecto."

use App\Models\UsuarioSca;

return [

    'defaults' => [
        'guard'     => env('AUTH_GUARD', 'web'),
        // Antes apuntaba a 'users'. Cambiamos a 'usuarios_sca':
        'passwords' => env('AUTH_PASSWORD_BROKER', 'usuarios_sca'),
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',          // autenticación por sesión (cookie)
            // Antes decía 'users'. Ahora apunta a nuestro provider:
            'provider' => 'usuarios_sca',
        ],
    ],

    'providers' => [
        // Provider nuevo: usa UsuarioSca (nuestra tabla usuarios_sca)
        'usuarios_sca' => [
            'driver' => 'eloquent',
            'model'  => UsuarioSca::class,
        ],
        // El provider 'users' original se ELIMINA porque no lo usamos.
        // Si lo dejábamos, el reset de contraseña buscaba la tabla 'users' que no existe.
    ],

    'passwords' => [
        // Broker para reset de contraseñas (si lo implementas después)
        'usuarios_sca' => [
            'provider' => 'usuarios_sca',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];