<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Laravel;

final class PermissionMap
{
    /**
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        'admin' => [
            'catalog.write',
            'notifications.read',
            'notifications.replay',
            'orders.view',
            'orders.view-any',
            'orders.cancel',
            'orders.cancel-any',
        ],
        'analyst' => [
            'notifications.read',
            'orders.view',
            'orders.view-any',
        ],
        'customer' => [
            'orders.view',
            'orders.cancel',
        ],
    ];

    /**
     * @param  list<string>  $roles
     */
    public static function allows(array $roles, string $permission): bool
    {
        foreach ($roles as $role) {
            $granted = self::ROLE_PERMISSIONS[strtolower($role)] ?? [];
            if (in_array($permission, $granted, true)) {
                return true;
            }
        }

        return false;
    }

    public static function isKnown(string $permission): bool
    {
        foreach (self::ROLE_PERMISSIONS as $granted) {
            if (in_array($permission, $granted, true)) {
                return true;
            }
        }

        return false;
    }
}
