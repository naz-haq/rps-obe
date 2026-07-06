<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Manajemen Pengguna: CRUD + penetapan peran & unit (prodi/fakultas).
 */
class UserController extends Controller
{
    use AppliesSorting;

    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('institusi');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('nidn', 'like', "%{$q}%");
            });
        }
        if ($role = $request->query('role')) {
            $query->whereHas('roles', fn($r) => $r->where('name', $role));
        }
        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->query('institusi_id'));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOL));
        }

        $this->applySort($query, $request, ['name', 'nidn', 'email', 'created_at', 'is_active'], 'name');

        return response()->json(
            UserResource::collection($query->paginate((int) $request->query('per_page', 15)))
                ->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request, null);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'institusi_id' => $data['institusi_id'] ?? null,
            'nidn' => $data['nidn'],
            'jabatan' => $data['jabatan'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $user->syncRoles($this->filterPeran($data['roles'] ?? []));
        $user->load('institusi');

        return response()->json(['data' => new UserResource($user)], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $this->validasi($request, $user);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'institusi_id' => $data['institusi_id'] ?? null,
            'nidn' => $data['nidn'],
            'jabatan' => $data['jabatan'] ?? null,
            'is_active' => $data['is_active'] ?? $user->is_active,
        ]);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        $user->syncRoles($this->filterPeran($data['roles'] ?? []));
        $user->load('institusi');

        return response()->json(['data' => new UserResource($user)]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user() && $request->user()->id === $user->id) {
            return response()->json(['message' => 'Anda tidak dapat menghapus akun sendiri.'], 422);
        }

        // Cegah menghapus super-admin terakhir.
        if ($user->hasRole('super-admin')) {
            $jml = User::role('super-admin')->count();
            if ($jml <= 1) {
                return response()->json(['message' => 'Tidak boleh menghapus Super Admin terakhir.'], 422);
            }
        }

        $user->delete();

        return response()->json(['message' => 'Pengguna dihapus.']);
    }

    /**
     * @return array<string,mixed>
     */
    private function validasi(Request $request, ?User $user): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'nidn' => ['required', 'string', 'max:50', Rule::unique('users', 'nidn')->ignore($user?->id)],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'institusi_id' => ['nullable', 'integer', 'exists:institusi,id'],
            'jabatan' => ['nullable', 'string', 'max:150'],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string'],
        ], [
            'nidn.required' => 'NIDN wajib diisi (dipakai untuk login).',
            'nidn.unique' => 'NIDN ini sudah dipakai pengguna lain.',
            'email.unique' => 'Email ini sudah dipakai pengguna lain.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ]);
    }

    /**
     * @param  array<int,string>  $roles
     * @return array<int,string>
     */
    private function filterPeran(array $roles): array
    {
        $valid = Role::pluck('name')->all();

        return array_values(array_intersect($roles, $valid));
    }
}
