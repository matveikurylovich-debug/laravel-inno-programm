<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AuthService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    #[OA\Get(
        path: '/api/admin/users',
        summary: 'Список пользователей',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        description: 'Доступно ролям admin и analyst',
        responses: [
            new OA\Response(response: 200, description: 'Список пользователей'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 403, description: 'Недостаточно прав'),
        ]
    )]
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

    #[OA\Patch(
        path: '/api/admin/users/{user}/role',
        summary: 'Изменить роль пользователя',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        description: 'Доступно только роли admin',
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['role'],
                properties: [
                    new OA\Property(property: 'role', type: 'string', enum: ['admin', 'analyst', 'customer']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Роль обновлена'),
            new OA\Response(response: 403, description: 'Запрещено'),
        ]
    )]
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
