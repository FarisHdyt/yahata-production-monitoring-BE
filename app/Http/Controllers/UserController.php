<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $like = '%' . addcslashes($search, '%_\\') . '%';

        $users = User::query()
            ->when($search !== '', fn ($q) => $q->where(
                fn ($w) => $w->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(username) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(nik) LIKE ?', [$like])
            ))
            ->when($request->query('role'), fn ($q, $v) => $q->where('role', $v))
            ->when($request->filled('isActive'), fn ($q) => $q->where('is_active', $request->boolean('isActive')))
            ->orderBy('name')
            ->get();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'nik' => $request->input('nik'),
            'name' => $request->input('name'),
            'username' => $request->input('username'),
            'email' => $request->input('email'),
            'role' => $request->input('role'),
            'is_active' => $request->boolean('isActive', true),
            'password' => $request->input('password'), // di-hash oleh cast
        ]);

        return (new UserResource($user))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse|UserResource
    {
        if ($request->user()->is($user)
            && ($request->input('role') !== $user->role || ! $request->boolean('isActive'))) {
            return response()->json([
                'message' => 'Anda tidak dapat mengubah role atau menonaktifkan akun Anda sendiri.',
            ], 422);
        }

        // NIK = master ID. Boleh diisi bila masih kosong, tapi tidak boleh diubah/dikosongkan setelah terisi.
        $nik = $request->input('nik');

        if ($user->nik !== null && $nik !== null && $nik !== $user->nik) {
            return response()->json([
                'message' => 'NIK sudah terisi dan tidak dapat diubah.',
                'errors' => ['nik' => ['NIK sudah terisi dan tidak dapat diubah.']],
            ], 422);
        }

        $user->update([
            'nik' => $user->nik ?? $nik,
            'name' => $request->input('name'),
            'username' => $request->input('username'),
            'email' => $request->input('email'),
            'role' => $request->input('role'),
            'is_active' => $request->boolean('isActive'),
        ]);

        // akun dinonaktifkan -> langsung putuskan semua sesi
        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return new UserResource($user);
    }

    // Reset password oleh admin
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'newPassword' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ]);

        $user->update(['password' => $data['newPassword']]); // di-hash oleh cast
        $user->tokens()->delete();

        return response()->json(['message' => 'Password berhasil direset.']);
    }

    public function destroy(Request $request, User $user): JsonResponse|Response
    {
        if ($request->user()->is($user)) {
            return response()->json(['message' => 'Anda tidak dapat menghapus akun Anda sendiri.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }
}