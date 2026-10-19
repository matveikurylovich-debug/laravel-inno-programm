<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AuthService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function index(): JsonResponse
    {
        $users = User::query()
            ->with('roles')
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $this->authService->primaryRole($user),
                'registered_at' => $user->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'data' => $users,
        ]);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        if ((int) $user->id === (int) $request->user()->id) {
            return response()->json([
                'error' => 'Нельзя изменить роль собственной учетной записи.',
            ], 403);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(['admin', 'analyst', 'customer'])],
        ]);

        Role::findOrCreate($validated['role'], 'web');
        $user->syncRoles([$validated['role']]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $validated['role'],
            ],
        ]);
    }
}
