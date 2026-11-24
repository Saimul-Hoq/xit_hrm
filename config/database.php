<?php

declare(strict_types=1);

use App\Core\Env;

/**
 * Database settings. TCP only: there is deliberately no socket option.
 */
return static function (Env $env): array {
    return [
        'host'     => $env->get('DB_HOST', '127.0.0.1'),
        'port'     => $env->getInt('DB_PORT', 3306, 1, 65535),
        'name'     => $env->required('DB_NAME'),
        'user'     => $env->required('DB_USER'),
        'password' => $env->get('DB_PASS', ''),

        // Fixed by design, not configurable.
        'charset'  => 'utf8mb4',
    ];
};