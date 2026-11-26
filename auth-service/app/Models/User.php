<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use App\Events\PasswordResetLinkRequested;
use App\Support\FrontendUrl;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, MassPrunable, Notifiable, SoftDeletes;

    protected string $guard_name = 'web';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::onlyTrashed()->where('deleted_at', '<=', now()->subDays(30));
    }

    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeEmail($value),
        );
    }

    public function primaryRoleName(): string
    {
        $roles = $this->getRoleNames();

        foreach (RoleName::cases() as $role) {
            if ($roles->contains($role->value)) {
                return $role->value;
            }
        }

        return RoleName::Customer->value;
    }

    public function sendPasswordResetNotification(mixed $token): void
    {
        event(new PasswordResetLinkRequested($this, (string) $token, FrontendUrl::baseFor(request())));
    }
}
