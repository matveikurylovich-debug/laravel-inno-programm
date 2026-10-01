<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\JwtService;
use App\Services\TwoFactorService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        protected JwtService $jwtService,
        protected TwoFactorService $twoFactorService,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-\(\)]+$/'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

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

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $this->twoFactorService->verify((int) $data['user_id'], $data['code']);

        return $this->tokenResponse($user);
    }

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

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        try {
            $payload = $this->jwtService->decodeRefreshToken($data['refresh_token']);
            $user = User::findOrFail($payload->sub);

            $this->jwtService->revokeRefreshToken($user->id, $payload->jti);

            return response()->json($this->jwtService->generateTokenPair($user));
        } catch (Throwable) {
            return response()->json(['error' => 'Недействительный или отозванный refresh-токен'], 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->bearerToken();
        if ($accessToken) {
            $this->jwtService->blacklistAccessToken($accessToken);
        }

        if ($refreshToken = $request->input('refresh_token')) {
            try {
                $payload = $this->jwtService->decodeRefreshToken($refreshToken);
                $this->jwtService->revokeRefreshToken($payload->sub, $payload->jti);
            } catch (Throwable) {
                // Игнорируем ошибки при логауте
            }
        }

        return response()->json(['message' => 'Вы успешно вышли из системы']);
    }

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
            'role' => $this->jwtService->primaryRole($user),
        ];
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        $tokens = $this->jwtService->generateTokenPair($user);

        return response()->json([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokens['expires_in'],
            'user' => $this->userPayload($user),
        ], $status);
    }
}
