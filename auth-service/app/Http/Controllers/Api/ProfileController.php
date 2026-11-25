<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileDestroyRequest;
use App\Http\Requests\ProfileUpdatePasswordRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        if ($request->validated('email') !== $user->email) {
            throw ValidationException::withMessages([
                'email' => 'Смена email требует текущий пароль и подтверждение по ссылке.',
            ]);
        }

        $user->fill($request->safe()->only(['name', 'phone']));

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
        $user = $request->user();
        $user->update([
            'password' => $request->validated('password'),
        ]);
        $this->authService->revokeAllForUser((int) $user->id);

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

        $this->authService->revokeAllForUser((int) $user->id);
        if ($token = $request->bearerToken()) {
            $this->authService->blacklistAccessToken($token);
        }

        $user->delete();

        return response()->json([
            'message' => 'Аккаунт удалён.',
        ]);
    }

    public function requestEmailChange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'current_password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Неверный текущий пароль.',
            ]);
        }

        Cache::put($this->pendingEmailKey((int) $user->id), $data['email'], now()->addHour());
        $confirm = URL::temporarySignedRoute('email-change.confirm', now()->addHour(), [
            'user' => $user->id,
            'email' => $data['email'],
        ]);
        $cancel = URL::temporarySignedRoute('email-change.cancel', now()->addHour(), [
            'user' => $user->id,
        ]);

        Mail::raw('Подтвердите новый адрес: '.$confirm, function ($message) use ($data): void {
            $message->to($data['email'])->subject('Подтверждение email');
        });
        Mail::raw('Если это были не вы, отмените смену: '.$cancel, function ($message) use ($user): void {
            $message->to($user->email)->subject('Запрос смены email');
        });

        return response()->json([
            'message' => 'Ждём подтверждения нового адреса.',
        ]);
    }

    public function confirmEmail(Request $request): JsonResponse
    {
        $user = User::query()->findOrFail($request->query('user'));
        $email = (string) $request->query('email');
        $pending = Cache::get($this->pendingEmailKey((int) $user->id));
        abort_unless(is_string($pending) && hash_equals($pending, $email), 404);

        $user->forceFill([
            'email' => $email,
            'email_verified_at' => now(),
        ])->save();
        Cache::forget($this->pendingEmailKey((int) $user->id));
        $this->authService->revokeAllForUser((int) $user->id);

        return response()->json(['message' => 'Email обновлён.']);
    }

    public function cancelEmailChange(Request $request): JsonResponse
    {
        $user = User::query()->findOrFail($request->query('user'));
        Cache::forget($this->pendingEmailKey((int) $user->id));

        return response()->json(['message' => 'Смена email отменена.']);
    }

    private function pendingEmailKey(int $userId): string
    {
        return 'email-change:'.$userId;
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
