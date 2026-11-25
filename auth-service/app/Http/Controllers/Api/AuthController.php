<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AuthService;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendTwoFactorRequest;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
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

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
            ]);

            $user->assignRole(RoleName::Customer);

            event(new Registered($user));

            return $user;
        });

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

        $challenge = $this->twoFactorService->challenge($user);

        return response()->json([
            'two_factor' => true,
            'challenge_id' => $challenge['challenge_id'],
            'expires_in' => $challenge['expires_in'],
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
                required: ['challenge_id', 'code'],
                properties: [
                    new OA\Property(property: 'challenge_id', type: 'string', minLength: 40, maxLength: 40),
                    new OA\Property(property: 'code', type: 'string', minLength: 6, maxLength: 6),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Токены выданы'),
            new OA\Response(response: 422, description: 'Неверный код'),
            new OA\Response(response: 429, description: 'Слишком много попыток'),
        ]
    )]
    public function verifyTwoFactor(VerifyTwoFactorRequest $request): JsonResponse
    {
        $user = $this->twoFactorService->verify($request->string('challenge_id')->toString(), $request->string('code')->toString());

        return $this->tokenResponse($user);
    }

    #[OA\Post(
        path: '/api/two-factor/resend',
        summary: 'Повторная отправка кода 2FA',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['challenge_id'],
                properties: [
                    new OA\Property(property: 'challenge_id', type: 'string', minLength: 40, maxLength: 40),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Новый код отправлен'),
            new OA\Response(response: 422, description: 'Нет активной проверки пароля'),
            new OA\Response(response: 429, description: 'Слишком много повторных отправок'),
        ]
    )]
    public function resendTwoFactor(ResendTwoFactorRequest $request): JsonResponse
    {
        $challenge = $this->twoFactorService->resend($request->string('challenge_id')->toString());

        return response()->json([
            'two_factor' => true,
            'challenge_id' => $challenge['challenge_id'],
            'expires_in' => $challenge['expires_in'],
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
                event(new PasswordReset($user));
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
            $payload = $this->authService->consumeRefreshToken($data['refresh_token']);
            $user = User::findOrFail($payload->sub);

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
        $refreshToken = $request->input('refresh_token');
        $refreshPayload = null;
        if (is_string($refreshToken) && $refreshToken !== '') {
            try {
                $refreshPayload = $this->authService->readRefreshToken($refreshToken);
            } catch (Throwable) {
                return response()->json(['error' => 'Недействительный refresh-токен'], 422);
            }

            $current = $request->user();
            if ($current !== null && (int) $refreshPayload->sub !== (int) $current->id) {
                return response()->json(['error' => 'Refresh-токен принадлежит другому пользователю'], 422);
            }
        }

        if ($accessToken = $request->bearerToken()) {
            $this->authService->blacklistAccessToken($accessToken);
        }

        if ($refreshPayload !== null) {
            $this->authService->revokeRefreshToken((int) $refreshPayload->sub, (string) $refreshPayload->jti);
        }

        return response()->json(['message' => 'Вы успешно вышли из системы']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authService->revokeAllForUser((int) $user->id);
        if ($token = $request->bearerToken()) {
            $this->authService->blacklistAccessToken($token);
        }

        return response()->json(['message' => 'Все сессии завершены']);
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
