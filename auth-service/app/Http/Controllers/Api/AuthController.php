<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Spatie\Permission\Models\Role;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected TwoFactorService $twoFactorService,
    ) {}

    #[OA\Post(
        path: '/api/register',
        summary: 'Регистрация',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'phone', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Пользователь создан, выданы токены'),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
        ]);

        Role::findOrCreate('customer', 'web');
        $user->assignRole('customer');

        event(new Registered($user));

        return $this->tokenResponse($user, 201);
    }

    #[OA\Post(
        path: '/api/login',
        summary: 'Логин (запуск 2FA)',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Код 2FA отправлен на почту'),
            new OA\Response(response: 401, description: 'Неверный логин или пароль'),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['error' => 'Неверный логин или пароль'], 401);
        }

        $ttlMinutes = $this->twoFactorService->challenge($user);

        return response()->json([
            'two_factor' => true,
            'user_id' => $user->id,
            'expires_in' => $ttlMinutes * 60,
            'message' => 'Код подтверждения отправлен на почту.',
        ]);
    }

    #[OA\Post(
        path: '/api/two-factor/verify',
        summary: 'Подтверждение 2FA',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'code'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer'),
                    new OA\Property(property: 'code', type: 'string', minLength: 6, maxLength: 6),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Токены выданы'),
            new OA\Response(response: 422, description: 'Неверный код'),
        ]
    )]
    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $this->twoFactorService->verify((int) $data['user_id'], $data['code']);

        return $this->tokenResponse($user);
    }

    #[OA\Post(
        path: '/api/two-factor/resend',
        summary: 'Повторная отправка кода 2FA',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Новый код отправлен'),
        ]
    )]
    public function resendTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $ttlMinutes = $this->twoFactorService->challenge($user);

        return response()->json([
            'two_factor' => true,
            'user_id' => $user->id,
            'expires_in' => $ttlMinutes * 60,
            'message' => 'Новый код отправлен на почту.',
        ]);
    }

    #[OA\Post(
        path: '/api/forgot-password',
        summary: 'Запрос сброса пароля',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ссылка отправлена (если аккаунт существует)'),
        ]
    )]
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        PasswordBroker::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Если аккаунт существует, мы отправили ссылку для сброса пароля.',
        ]);
    }

    #[OA\Post(
        path: '/api/reset-password',
        summary: 'Сброс пароля',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Пароль обновлён'),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ]
    )]
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $status = PasswordBroker::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();
            }
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Пароль обновлён. Войдите с новым паролем.',
        ]);
    }

    #[OA\Post(
        path: '/api/refresh',
        summary: 'Обновление access-токена',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['refresh_token'],
                properties: [
                    new OA\Property(property: 'refresh_token', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Новая пара токенов'),
            new OA\Response(response: 401, description: 'Недействительный refresh-токен'),
        ]
    )]
    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        try {
            $payload = $this->authService->decodeRefreshToken($data['refresh_token']);
            $user = User::findOrFail($payload->sub);

            $this->authService->revokeRefreshToken($user->id, $payload->jti);

            return response()->json($this->authService->generateTokenPair($user));
        } catch (Throwable) {
            return response()->json(['error' => 'Недействительный или отозванный refresh-токен'], 401);
        }
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Выход',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'refresh_token', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Успешный выход'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->bearerToken();
        if ($accessToken) {
            $this->authService->blacklistAccessToken($accessToken);
        }

        if ($refreshToken = $request->input('refresh_token')) {
            try {
                $payload = $this->authService->decodeRefreshToken($refreshToken);
                $this->authService->revokeRefreshToken($payload->sub, $payload->jti);
            } catch (Throwable) {
                // Игнорируем ошибки при логауте
            }
        }

        return response()->json(['message' => 'Вы успешно вышли из системы']);
    }

    #[OA\Get(
        path: '/api/me',
        summary: 'Текущий пользователь',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Профиль пользователя'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
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

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        $tokens = $this->authService->generateTokenPair($user);

        return response()->json([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokens['expires_in'],
            'user' => $this->userPayload($user),
        ], $status);
    }
}
