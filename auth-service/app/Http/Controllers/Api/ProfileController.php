<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileDestroyRequest;
use App\Http\Requests\ProfileUpdatePasswordRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    #[OA\Patch(
        path: '/api/profile',
        summary: 'Обновить профиль',
        security: [['bearerAuth' => []]],
        tags: ['Profile'],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Профиль обновлён'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    #[OA\Put(
        path: '/api/profile/password',
        summary: 'Сменить пароль',
        security: [['bearerAuth' => []]],
        tags: ['Profile'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Пароль обновлён'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ]
    )]
    public function updatePassword(ProfileUpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return response()->json([
            'message' => 'Пароль обновлён.',
        ]);
    }

    #[OA\Delete(
        path: '/api/profile',
        summary: 'Удалить аккаунт',
        security: [['bearerAuth' => []]],
        tags: ['Profile'],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Аккаунт удалён'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    public function destroy(ProfileDestroyRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($token = $request->bearerToken()) {
            $this->authService->blacklistAccessToken($token);
        }

        $user->delete();

        return response()->json([
            'message' => 'Аккаунт удалён.',
        ]);
    }

    /**
     * @return array{id: int, name: string, email: string, phone: string|null, role: string}
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $this->authService->primaryRole($user),
        ];
    }
}
