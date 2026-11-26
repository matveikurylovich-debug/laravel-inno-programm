<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\TokenService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\ProfileDestroyRequest;
use App\Http\Requests\Api\V1\Profile\ProfileUpdatePasswordRequest;
use App\Http\Requests\Api\V1\Profile\ProfileUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    private const string PENDING_EMAIL_CACHE_PREFIX = 'email-change:';

    private const int PENDING_EMAIL_TTL_MINUTES = 60;

    public function __construct(
        protected TokenService $authService,
    ) {}

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        if ($request->validated('email') !== $user->email) {
            throw ValidationException::withMessages([
                'email' => __('Смена email требует текущий пароль и подтверждение по ссылке.'),
            ]);
        }

        $user->fill($request->safe()->only(['name', 'phone']));
        $user->save();

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function updatePassword(ProfileUpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $user->update([
            'password' => $request->string('password')->toString(),
        ]);
        $this->authService->revokeAllForUser((int) $user->id);

        return response()->json([
            'message' => __('Пароль обновлён.'),
        ]);
    }

    public function destroy(ProfileDestroyRequest $request): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $this->authService->revokeAllForUser((int) $user->id);

        $token = $request->bearerToken();
        if (is_string($token) && $token !== '') {
            $this->authService->blacklistAccessToken($token);
        }

        $user->delete();

        return response()->json([
            'message' => __('Аккаунт удалён.'),
        ]);
    }

    public function requestEmailChange(Request $request): JsonResponse
    {
        if ($request->exists('email')) {
            $request->merge(['email' => User::normalizeEmail((string) $request->input('email'))]);
        }

        /** @var array{email: string, current_password: string} $data */
        $data = $request->validate([
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->withoutTrashed(),
            ],
            'current_password' => ['required', 'string'],
        ]);

        $user = $request->user();
        assert($user instanceof User);

        if (! Hash::check($data['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('Неверный текущий пароль.'),
            ]);
        }

        $expiresAt = now()->addMinutes(self::PENDING_EMAIL_TTL_MINUTES);
        Cache::put($this->pendingEmailKey((int) $user->id), $data['email'], $expiresAt);

        $confirm = URL::temporarySignedRoute('api.v1.email-change.confirm', $expiresAt, [
            'user' => $user->id,
            'email' => $data['email'],
        ]);
        $cancel = URL::temporarySignedRoute('api.v1.email-change.cancel', $expiresAt, [
            'user' => $user->id,
        ]);

        $newEmail = $data['email'];
        Mail::raw('Подтвердите новый адрес: '.$confirm, function (Message $message) use ($newEmail): void {
            $message->to($newEmail)->subject('Подтверждение email');
        });
        Mail::raw('Если это были не вы, отмените смену: '.$cancel, function (Message $message) use ($user): void {
            $message->to($user->email)->subject('Запрос смены email');
        });

        return response()->json([
            'message' => __('Ждём подтверждения нового адреса.'),
        ]);
    }

    public function confirmEmail(Request $request): JsonResponse
    {
        $user = User::query()->findOrFail($request->integer('user'));
        $email = User::normalizeEmail((string) $request->query('email'));
        $pending = Cache::get($this->pendingEmailKey((int) $user->id));
        abort_unless(is_string($pending) && hash_equals($pending, $email), 404);

        $user->forceFill([
            'email' => $email,
            'email_verified_at' => now(),
        ])->save();
        Cache::forget($this->pendingEmailKey((int) $user->id));
        $this->authService->revokeAllForUser((int) $user->id);

        return response()->json(['message' => __('Email обновлён.')]);
    }

    public function cancelEmailChange(Request $request): JsonResponse
    {
        $user = User::query()->findOrFail($request->integer('user'));
        Cache::forget($this->pendingEmailKey((int) $user->id));

        return response()->json(['message' => __('Смена email отменена.')]);
    }

    private function pendingEmailKey(int $userId): string
    {
        return self::PENDING_EMAIL_CACHE_PREFIX.$userId;
    }
}
