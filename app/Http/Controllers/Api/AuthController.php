<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        protected JwtService $jwtService
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Роль Spatie по умолчанию
        $user->assignRole('customer');

        $tokens = $this->jwtService->generateTokenPair($user);

        return response()->json([
            'message' => 'Пользователь успешно зарегистрирован',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ],
            'tokens' => $tokens,
        ], 201);
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

        $tokens = $this->jwtService->generateTokenPair($user);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ],
            'tokens' => $tokens,
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

            // Удаляем старый refresh-токен из Redis (защита от повторного использования)
            $this->jwtService->revokeRefreshToken($user->id, $payload->jti);

            // Выдаем свежую пару
            $tokens = $this->jwtService->generateTokenPair($user);

            return response()->json($tokens);
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
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }
}