<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'Berhasil keluar.']);
    }
}
