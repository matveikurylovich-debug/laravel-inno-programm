<?php

namespace App\Http\Controllers\Auth;

use App\Events\TwoFactorCodeRequested;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengeController extends Controller
{
    /**
     * Генерация и отправка кода в Kafka.
     */
    public static function initiate(User $user): void
    {
        $code = (string) random_int(100000, 999999);
        $ttlMinutes = 5;

        Cache::put("2fa_code_{$user->id}", $code, now()->addMinutes($ttlMinutes));

        event(new TwoFactorCodeRequested($user, $code, $ttlMinutes));
    }

    /**
     * Проверка введенного кода.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $userId = $request->input('user_id');
        $cachedCode = Cache::get("2fa_code_{$userId}");

        if (! $cachedCode || $cachedCode !== $request->input('code')) {
            throw ValidationException::withMessages([
                'code' => ['Неверный или просроченный код безопасности.'],
            ]);
        }

        Cache::forget("2fa_code_{$userId}");

        $user = User::findOrFail($userId);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Аутентификация успешно завершена.',
            'user' => $user,
        ]);
    }
}
