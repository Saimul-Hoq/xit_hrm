<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who is logged in. The string value is what the Session stores.
 *
 *   Role::Admin->value       === 'admin'
 *   Role::tryFrom('admin')   === Role::Admin
 *   Role::tryFrom('root')    === null   (unknown values never become a role)
 */
enum Role: string
{
    case Admin = 'admin';
    case Employee = 'employee';
}