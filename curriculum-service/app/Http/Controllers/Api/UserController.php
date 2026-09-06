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
        $this->applyTenantScope($query, $request);
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

    /**
     * Impor massal akun pengguna dari baris Excel/CSV (2D: baris pertama = header).
     * Upsert per NIDN (kunci login). Kolom dikenali: nama, nidn, email, jabatan,
     * institusi_id, peran/roles (dipisah koma/titik-koma), password (opsional).
     * Password default = NIDN bila kolom kosong (dosen ganti saat login pertama).
     */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rows'         => ['present', 'array', 'max:2000'],
            'institusi_id' => ['nullable', 'integer', 'exists:institusi,id'],
        ]);

        $rows = $data['rows'];
        if (count($rows) < 2) {
            return response()->json(['message' => 'Berkas tidak memuat baris data (butuh header + minimal satu baris).'], 422);
        }

        // Pemetaan header -> field (deterministik via kata kunci, urut spesifik dulu).
        $keyword = [
            'nidn'         => ['nidn', 'nip', 'nik', 'no induk', 'username'],
            'name'         => ['nama', 'name'],
            'email'        => ['email', 'surel', 'e-mail'],
            'jabatan'      => ['jabatan', 'posisi'],
            'roles'        => ['peran', 'role'],
            'institusi_id' => ['institusi', 'unit', 'prodi'],
            'password'     => ['password', 'sandi', 'kata sandi'],
        ];
        $headers = array_map(fn($h) => $this->normalisasiHeader((string) $h), $rows[0]);
        $map = [];
        $terpakai = [];
        foreach ($headers as $i => $h) {
            foreach ($keyword as $field => $kunci) {
                if (in_array($field, $terpakai, true)) {
                    continue;
                }
                foreach ($kunci as $k) {
                    if (str_contains($h, $k)) {
                        $map[$i] = $field;
                        $terpakai[] = $field;
                        continue 3;
                    }
                }
            }
        }

        if (! in_array('name', $map, true) || ! in_array('nidn', $map, true)) {
            return response()->json([
                'message' => 'Header wajib memuat kolom Nama dan NIDN.',
            ], 422);
        }

        $validRoles = Role::pluck('name')->all();
        $dibuat = 0;
        $diperbarui = 0;
        $dilewati = 0;
        $galat = [];

        foreach (array_slice($rows, 1) as $idx => $row) {
            $nomor = $idx + 2; // baris ke-n di berkas (1 = header)
            if (! is_array($row)) {
                $dilewati++;
                continue;
            }
            $rec = [];
            foreach ($map as $i => $field) {
                $rec[$field] = trim((string) ($row[$i] ?? ''));
            }
            $nama = $rec['name'] ?? '';
            $nidn = $rec['nidn'] ?? '';
            if ($nama === '' || $nidn === '') {
                $galat[] = "Baris {$nomor}: Nama/NIDN kosong, dilewati.";
                $dilewati++;
                continue;
            }
            $email = ($rec['email'] ?? '') !== '' ? $rec['email'] : null;
            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $galat[] = "Baris {$nomor}: email '{$email}' tidak valid, diabaikan.";
                $email = null;
            }

            $peran = array_values(array_intersect(
                array_map('trim', preg_split('/[;,]/', $rec['roles'] ?? '') ?: []),
                $validRoles,
            ));
            $institusiId = ($rec['institusi_id'] ?? '') !== '' && is_numeric($rec['institusi_id'])
                ? (int) $rec['institusi_id']
                : ($data['institusi_id'] ?? null);

            $user = User::where('nidn', $nidn)->first();
            $baru = $user === null;

            // Email unik: bila dipakai user lain, abaikan agar tak menabrak.
            if ($email !== null && User::where('email', $email)->where('nidn', '!=', $nidn)->exists()) {
                $galat[] = "Baris {$nomor}: email '{$email}' sudah dipakai, diabaikan.";
                $email = null;
            }

            $user ??= new User();
            $user->name = $nama;
            $user->nidn = $nidn;
            if ($email !== null || $baru) {
                $user->email = $email;
            }
            $user->jabatan = ($rec['jabatan'] ?? '') !== '' ? $rec['jabatan'] : $user->jabatan;
            if ($institusiId !== null) {
                $user->institusi_id = $institusiId;
            }
            if ($baru) {
                $pass = ($rec['password'] ?? '') !== '' ? $rec['password'] : $nidn;
                $user->password = Hash::make($pass);
                $user->is_active = true;
            } elseif (($rec['password'] ?? '') !== '') {
                $user->password = Hash::make($rec['password']);
            }
            $user->save();

            if ($peran !== []) {
                $user->syncRoles($peran);
            }

            $baru ? $dibuat++ : $diperbarui++;
        }

        return response()->json([
            'data' => compact('dibuat', 'diperbarui', 'dilewati', 'galat'),
        ], 201);
    }

    private function normalisasiHeader(string $h): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower($h)) ?? '');
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
