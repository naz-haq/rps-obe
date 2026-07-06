<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Autentikasi berbasis token (Laravel Sanctum).
 * login → token + profil + izin; me → profil terkini; logout → cabut token aktif.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // NIDN sebagai identitas utama; email tetap diterima sebagai alternatif.
        $login = trim($data['login']);
        $user = User::where('nidn', $login)
            ->orWhere('email', $login)
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['NIDN/email atau kata sandi salah.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Akun ini dinonaktifkan. Hubungi administrator.'],
            ]);
        }

        // Cabut token lama agar sesi bersih (opsional; single active token).
        $token = $user->createToken('web')->plainTextToken;

        $user->load('institusi');

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('institusi');

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update profil diri sendiri (nama & email). NIDN & peran tidak diubah di sini.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update([
            'name'  => $data['name'],
            'email' => $data['email'] ?? null,
        ]);

        $user->load('institusi');

        return response()->json([
            'data'    => new UserResource($user),
            'message' => 'Profil berhasil diperbarui.',
        ]);
    }

    /**
     * Ubah kata sandi diri sendiri; wajib verifikasi kata sandi saat ini.
     * Token sesi lain dicabut demi keamanan, token aktif dipertahankan.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Kata sandi saat ini salah.'],
            ]);
        }

        $user->update(['password' => $data['password']]);

        // Cabut token sesi lain; sesi saat ini tetap berlaku.
        $currentId = $request->user()->currentAccessToken()?->id;
        if ($currentId) {
            $user->tokens()->where('id', '!=', $currentId)->delete();
        }

        return response()->json(['message' => 'Kata sandi berhasil diperbarui.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'Berhasil keluar.']);
    }
}
