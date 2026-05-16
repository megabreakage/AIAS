<?php

declare(strict_types=1);

return [

    'default' => env('DB_CONNECTION', 'central'),

    'connections' => [
        'central' => [
            'driver'         => 'mysql',
            'host'           => env('DB_CENTRAL_HOST', '127.0.0.1'),
            'port'           => env('DB_CENTRAL_PORT', '3306'),
            'database'       => env('DB_CENTRAL_DATABASE', 'aias_central'),
            'username'       => env('DB_CENTRAL_USERNAME', 'aias'),
            'password'       => env('DB_CENTRAL_PASSWORD', ''),
            'unix_socket'    => env('DB_SOCKET', ''),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => 'InnoDB',
        ],

        'tenant_template' => [
            'driver'         => 'mysql',
            'host'           => env('DB_TENANT_HOST', '127.0.0.1'),
            'port'           => env('DB_TENANT_PORT', '3306'),
            'database'       => null,
            'username'       => env('DB_TENANT_USERNAME', 'aias'),
            'password'       => env('DB_TENANT_PASSWORD', ''),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => 'InnoDB',
        ],

        'sqlite' => [
            'driver'                  => 'sqlite',
            'database'                => env('DB_DATABASE', database_path('database.sqlite')),
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],
    ],

    'migrations' => [
        'table'                  => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [
        'client'  => env('REDIS_CLIENT', 'predis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix'  => env('REDIS_PREFIX', 'aias_'),
        ],
        'default' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
        'cache' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],

];
