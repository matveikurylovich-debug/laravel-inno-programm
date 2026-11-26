<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, email: string, phone: string|null, roles: list<string>, role: string, registered_at: string|null}
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => array_values($user->getRoleNames()->map(fn (mixed $name): string => (string) $name)->all()),
            'role' => $user->primaryRoleName(),
            'registered_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
