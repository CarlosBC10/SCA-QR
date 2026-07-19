<?php
// config/database.php
// Define las conexiones a bases de datos.
// Las credenciales reales se leen del .env con env('VARIABLE', 'default').

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    // Conexión por defecto (la que usan todos los modelos que no especifican otra)
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        // ===== Conexión principal: sca (tu base de datos nueva) =====
        'mysql' => [
            'driver'         => 'mysql',
            'host'           => env('DB_HOST', '127.0.0.1'),        // lee DB_HOST del .env
            'port'           => env('DB_PORT', '3306'),
            'database'       => env('DB_DATABASE', 'sca'),
            'username'       => env('DB_USERNAME', 'root'),         // default genérico
            'password'       => env('DB_PASSWORD', ''),             // default vacío
            'unix_socket'    => env('DB_SOCKET', ''),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,                               // modo estricto: errores claros
            'engine'         => 'InnoDB',                           // motor con FKs
            'options'        => extension_loaded('pdo_mysql') ? array_filter([
                // CORRECCIÓN: Mysql::ATTR_SSL_CA (no PDO::MYSQL_ATTR_SSL_CA)
                // porque el import es 'use Pdo\Mysql;'
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // ===== Conexión a sitec (la BD existente, SOLO LECTURA) =====
        // El SRS prohíbe modificar sitec. Esta conexión se usa solo en el modelo
        // App\Models\Sitec\Empleado para leer los datos de empleados.
        'sitec' => [
            'driver'         => 'mysql',
            'host'           => env('DB_SITEC_HOST', '127.0.0.1'),
            'port'           => env('DB_SITEC_PORT', '3306'),
            'database'       => env('DB_SITEC_DATABASE', 'sitec'),
            'username'       => env('DB_SITEC_USERNAME', 'root'),
            'password'       => env('DB_SITEC_PASSWORD', ''),
            'unix_socket'    => env('DB_SOCKET', ''),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => 'InnoDB',
            'options'        => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // Mantenemos sqlite para tests y entornos sin MySQL (comentado por si lo necesitas)
        // 'sqlite' => [ ... ],

        // Mantenemos pgsql y sqlsrv comentados por si se necesitan en el futuro
        // 'pgsql' => [ ... ],
        // 'sqlsrv' => [ ... ],
    ],

    // Tabla que lleva el registro de qué migraciones se han ejecutado
    'migrations' => [
        'table'                  => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster'    => env('REDIS_CLUSTER', 'redis'),
            'prefix'      => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],
        'default' => [
            'url'      => env('REDIS_URL'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
        'cache' => [
            'url'      => env('REDIS_URL'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],
];