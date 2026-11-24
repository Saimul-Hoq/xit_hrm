<?php

declare(strict_types=1);

use App\Core\Env;

/**
 * Application settings.
 * Every default lives HERE, not at the places that read the setting.
 */
return static function (Env $env): array {
    // Missing APP_ENV means production: the forgotten case must be the safe case.
    $environment = $env->getChoice('APP_ENV', ['local', 'production'], 'production');

    return [
        'name'      => $env->get('APP_NAME', 'XIT HRM'),
        'env'       => $environment,

        // APP_DEBUG is ignored entirely in production.
        'debug'     => $environment !== 'production' && $env->getBool('APP_DEBUG', false),

        'timezone'  => $env->get('APP_TIMEZONE', 'Asia/Dhaka'),

        // Raw value; the Url helper (Step 7) normalises it.
        'base_path' => $env->get('APP_BASE_PATH', ''),

        'session'   => [
            'name' => $env->get('SESSION_NAME', 'XITHRMSESSID'),
        ],

        'upload'    => [
            // 2 MB = 2097152 bytes
            'max_bytes' => $env->getInt('UPLOAD_MAX_BYTES', 2097152, 1),
        ],
    ];
};