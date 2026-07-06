<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Manajemen Peran & Hak Akses (RBAC dinamis).
 * Matriks ceklist: setiap peran dapat diberi/dicabut izin apa pun.
 * Peran super-admin dikunci (selalu semua izin) agar tak mengunci diri.
 */
class RoleController extends Controller
{
    use AppliesSorting;

    private const PROTECTED_ROLE = 'super-admin';

    /** Daftar peran + izin. */
    public function index(Request $request): JsonResponse
    {
        $query = Role::query()->with('permissions');
        $this->applySort($query, $request, ['name', 'created_at'], 'name');

        return response()->json([
            'data' => RoleResource::collection($query->get()),
        ]);
    }

    /** Katalog izin (grup) untuk membangun matriks ceklist. */
    public function katalog(): JsonResponse
    {
        $groups = [];
        foreach (config('rbac.groups', []) as $key => $grup) {
            $items = [];
            foreach ($grup['permissions'] ?? [] as $perm => $label) {
                $items[] = ['name' => $perm, 'label' => $label];
            }
            $groups[] = [
                'key' => $key,
                'label' => $grup['label'] ?? $key,
                'permissions' => $items,
            ];
        }

        return response()->json(['data' => ['groups' => $groups]]);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');

        return response()->json(['data' => new RoleResource($role)]);
    }

    /** Buat peran baru (kustom). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $slug = Str::slug($data['name']);
        if ($slug === '') {
            $slug = 'peran-' . Str::random(6);
        }

        $guard = config('auth.defaults.guard', 'web');
        $role = Role::firstOrCreate(['name' => $slug, 'guard_name' => $guard]);
        $role->syncPermissions($this->filterIzin($data['permissions'] ?? []));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role->load('permissions');

        return response()->json(['data' => new RoleResource($role)], 201);
    }

    /** Simpan matriks ceklist izin untuk sebuah peran. */
    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ]);

        if ($role->name === self::PROTECTED_ROLE) {
            return response()->json([
                'message' => 'Peran Super Admin tidak dapat diubah (selalu memiliki semua izin).',
            ], 422);
        }

        $role->syncPermissions($this->filterIzin($data['permissions']));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role->load('permissions');

        return response()->json(['data' => new RoleResource($role)]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->name === self::PROTECTED_ROLE) {
            return response()->json(['message' => 'Peran Super Admin tidak dapat dihapus.'], 422);
        }
        if ($role->users()->count() > 0) {
            return response()->json(['message' => 'Peran masih dipakai pengguna. Pindahkan pengguna dulu.'], 422);
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => 'Peran dihapus.']);
    }

    /**
     * Sisakan hanya izin yang benar-benar terdaftar (anti nilai liar).
     *
     * @param  array<int,string>  $izin
     * @return array<int,string>
     */
    private function filterIzin(array $izin): array
    {
        $valid = Permission::pluck('name')->all();

        return array_values(array_intersect($izin, $valid));
    }
}
