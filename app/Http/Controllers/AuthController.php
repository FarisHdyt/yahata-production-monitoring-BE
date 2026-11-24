<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const TOKEN_HOURS = 12;

    public function login(LoginRequest $request): JsonResponse
    {
        $username = mb_strtolower(trim($request->input('username')));
        $key = 'login:' . $username . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $user = User::where('username', $username)->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Akun Anda dinonaktifkan. Hubungi administrator.',
            ], Response::HTTP_FORBIDDEN);
        }

        RateLimiter::clear($key);

        $deviceName = $request->input('deviceName', 'web');
        $user->tokens()->where('name', $deviceName)->delete(); // satu token aktif per perangkat

        $expiresAt = now()->addHours(self::TOKEN_HOURS);
        $token = $user->createToken($deviceName, ['*'], $expiresAt);

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'tokenType' => 'Bearer',
            'expiresAt' => $expiresAt->toIso8601String(),
            'user' => (new UserResource($user))->resolve(),
        ]]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->input('currentPassword'), $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['Password saat ini salah.'],
            ]);
        }

        $user->update(['password' => $request->input('newPassword')]); // di-hash oleh cast

        // logout dari perangkat lain, token yang sedang dipakai tetap hidup
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password berhasil diubah.']);
    }
}