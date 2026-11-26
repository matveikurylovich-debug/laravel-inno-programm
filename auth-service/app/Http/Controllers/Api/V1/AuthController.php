<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\TokenService;
use App\Exceptions\InvalidRefreshTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RefreshTokenRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\ResendTwoFactorRequest;
use App\Http\Requests\Api\V1\Auth\VerifyTwoFactorRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\RegisterUser;
use App\Services\Auth\TwoFactorService;
use App\Support\TokenPair;
use Ecommerce\PlatformKit\Token\InvalidTokenException;
use Ecommerce\PlatformKit\Token\TokenInfrastructureException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        protected TokenService $authService,
        protected TwoFactorService $twoFactorService,
    ) {}

    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser->handle([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'phone' => $request->string('phone')->toString(),
            'password' => $request->string('password')->toString(),
        ]);
        $challenge = $this->twoFactorService->challenge($user);

        return response()->json([
            'two_factor' => true,
            'challenge_id' => $challenge['challenge_id'],
            'expires_in' => $challenge['expires_in'],
            'message' => __('Код отправлен. Если письмо не пришло, запросите ещё раз.'),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticate();
        $challenge = $this->twoFactorService->challenge($user);

        return response()->json([
            'two_factor' => true,
            'challenge_id' => $challenge['challenge_id'],
            'expires_in' => $challenge['expires_in'],
            'message' => __('Код отправлен. Если письмо не пришло, запросите ещё раз.'),
        ]);
    }

    public function verifyTwoFactor(VerifyTwoFactorRequest $request): JsonResponse
    {
        $user = $this->twoFactorService->verify(
            $request->string('challenge_id')->toString(),
            $request->string('code')->toString(),
        );

        return $this->tokenResponse($user);
    }

    public function resendTwoFactor(ResendTwoFactorRequest $request): JsonResponse
    {
        $challenge = $this->twoFactorService->resend($request->string('challenge_id')->toString());

        return response()->json([
            'two_factor' => true,
            'challenge_id' => $challenge['challenge_id'],
            'expires_in' => $challenge['expires_in'],
            'message' => __('Новый код отправлен на почту.'),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        if ($request->exists('email')) {
            $request->merge(['email' => User::normalizeEmail((string) $request->input('email'))]);
        }

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        PasswordBroker::sendResetLink($request->only('email'));

        return response()->json([
            'message' => __('Если аккаунт существует, мы отправили ссылку для сброса пароля.'),
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        if ($request->exists('email')) {
            $request->merge(['email' => User::normalizeEmail((string) $request->input('email'))]);
        }

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
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__((string) $status)],
            ]);
        }

        return response()->json([
            'message' => __('Пароль обновлён. Войдите с новым паролем.'),
        ]);
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $refreshToken = $request->string('refresh_token')->toString();

        try {
            $payload = $this->authService->readRefreshToken($refreshToken);
            $tokens = $this->authService->rotateRefreshToken($refreshToken);
            $user = User::query()->with('roles')->find((int) ($payload->sub ?? 0));
            if (! $user instanceof User) {
                throw new InvalidArgumentException(__('Пользователь не найден.'));
            }

            return $this->tokenResponse($user, tokens: $tokens);
        } catch (InvalidArgumentException|InvalidTokenException) {
            throw new InvalidRefreshTokenException;
        } catch (TokenInfrastructureException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            abort(500, __('Ошибка авторизации'));
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token');
        $refreshPayload = null;

        if (is_string($refreshToken) && $refreshToken !== '') {
            try {
                $refreshPayload = $this->authService->readRefreshToken($refreshToken);
            } catch (Throwable) {
                throw new InvalidRefreshTokenException(
                    status: 422,
                    message: __('Недействительный refresh-токен'),
                );
            }

            $current = $request->user();
            if ($current instanceof User && (int) ($refreshPayload->sub ?? 0) !== (int) $current->id) {
                throw new InvalidRefreshTokenException(
                    status: 422,
                    message: __('Refresh-токен принадлежит другому пользователю'),
                    codeName: 'refresh_token_mismatch',
                );
            }
        }

        $accessToken = $request->bearerToken();
        if (is_string($accessToken) && $accessToken !== '') {
            $this->authService->blacklistAccessToken($accessToken);
        }

        if ($refreshPayload !== null) {
            $this->authService->revokeRefreshToken(
                (int) ($refreshPayload->sub ?? 0),
                (string) ($refreshPayload->jti ?? ''),
            );
        }

        return response()->json(['message' => __('Вы успешно вышли из системы')]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $this->authService->revokeAllForUser((int) $user->id);

        $token = $request->bearerToken();
        if (is_string($token) && $token !== '') {
            $this->authService->blacklistAccessToken($token);
        }

        return response()->json(['message' => __('Все сессии завершены')]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    private function tokenResponse(User $user, int $status = 200, ?TokenPair $tokens = null): JsonResponse
    {
        $tokens ??= $this->authService->generateTokenPair($user);

        return response()->json([
            ...$tokens->toArray(),
            'user' => new UserResource($user),
        ], $status);
    }
}
