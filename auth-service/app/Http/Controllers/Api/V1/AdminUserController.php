<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\TokenService;
use App\Enums\RoleName;
use App\Exceptions\LastAdminException;
use App\Exceptions\SelfRoleModificationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\RoleAuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    public function __construct(
        protected TokenService $authService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('id')
            ->paginate(50);

        return UserResource::collection($users)->additional([
            'roles' => $this->availableRoles(),
        ]);
    }

    public function roles(): JsonResponse
    {
        return response()->json([
            'roles' => $this->availableRoles(),
        ]);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
        $actor = $request->user();
        assert($actor instanceof User);

        $role = RoleName::from($request->string('role')->toString());

        if ($user->hasRole(RoleName::Admin) && $role !== RoleName::Admin && User::role(RoleName::Admin->value)->count() <= 1) {
            throw new LastAdminException;
        }

        if ($user->is($actor)) {
            throw new SelfRoleModificationException;
        }

        $oldRole = $user->primaryRoleName();

        DB::transaction(function () use ($actor, $user, $oldRole, $role): void {
            if ($user->hasRole(RoleName::Admin) && $role !== RoleName::Admin && User::role(RoleName::Admin->value)->lockForUpdate()->count() <= 1) {
                throw new LastAdminException;
            }

            $user->syncRoles([$role]);

            RoleAuditLog::query()->create([
                'actor_id' => (int) $actor->id,
                'target_user_id' => (int) $user->id,
                'old_role' => $oldRole,
                'new_role' => $role->value,
                'created_at' => CarbonImmutable::now(),
            ]);
        });

        $this->authService->revokeAllForUser((int) $user->id);
        $user->unsetRelation('roles');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * @return list<string>
     */
    private function availableRoles(): array
    {
        return array_map(
            static fn (RoleName $role): string => $role->value,
            RoleName::cases(),
        );
    }
}
