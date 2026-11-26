<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Database\ConnectionInterface;

class RegisterUser
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly EventDispatcher $events,
    ) {}

    /**
     * @param  array{name: string, email: string, phone: string, password: string}  $data
     */
    public function handle(array $data): User
    {
        return $this->connection->transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
            ]);

            $user->assignRole(RoleName::Customer);

            $this->events->dispatch(new Registered($user));

            return $user;
        });
    }
}
