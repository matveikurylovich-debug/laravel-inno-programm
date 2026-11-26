<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use Ecommerce\PlatformKit\Laravel\JwtUser;
use Ecommerce\PlatformKit\Laravel\PermissionMap;

class OrderPolicy
{
    public function viewAny(JwtUser $user): bool
    {
        return PermissionMap::allows($user->roles(), 'orders.view')
            || PermissionMap::allows($user->roles(), 'orders.view-any');
    }

    public function view(JwtUser $user, Order $order): bool
    {
        if (PermissionMap::allows($user->roles(), 'orders.view-any')) {
            return true;
        }

        return PermissionMap::allows($user->roles(), 'orders.view')
            && (string) $order->user_id === (string) $user->getAuthIdentifier();
    }

    public function cancel(JwtUser $user, Order $order): bool
    {
        if (PermissionMap::allows($user->roles(), 'orders.cancel-any')) {
            return true;
        }

        return PermissionMap::allows($user->roles(), 'orders.cancel')
            && (string) $order->user_id === (string) $user->getAuthIdentifier();
    }
}
