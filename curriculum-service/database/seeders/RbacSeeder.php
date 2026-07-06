<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder RBAC: izin + peran bawaan dari config/rbac.php, plus akun Super Admin.
 * Idempoten — aman dijalankan berulang. Peran yang izinnya sudah diubah manual
 * TIDAK ditimpa (hanya membuat yang belum ada).
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        // 1) Buat semua izin dari katalog.
        $semuaIzin = [];
        foreach (config('rbac.groups', []) as $grup) {
            foreach (array_keys($grup['permissions'] ?? []) as $key) {
                $semuaIzin[] = $key;
                Permission::firstOrCreate(['name' => $key, 'guard_name' => $guard]);
            }
        }

        // 2) Buat peran + tetapkan izin default (hanya bila peran baru).
        foreach (config('rbac.roles', []) as $key => $def) {
            $role = Role::firstOrCreate(['name' => $key, 'guard_name' => $guard]);

            $penuh = ! empty($def['all']);

            // Super Admin selalu disinkronkan ke SELURUH izin (agar izin baru
            // yang ditambahkan ke katalog langsung dimiliki). Peran lain yang
            // sudah ada TIDAK ditimpa (mungkin sudah diubah admin).
            if (! $role->wasRecentlyCreated && ! $penuh) {
                continue;
            }

            $izin = $penuh ? $semuaIzin : ($def['permissions'] ?? []);

            $role->syncPermissions($izin);
        }

        // 3) Akun Super Admin default.
        $admin = User::firstOrCreate(
            ['email' => 'superadmin@rps.local'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('Admin#1234'),
                'is_active' => true,
            ],
        );
        if (! $admin->hasRole('super-admin')) {
            $admin->assignRole('super-admin');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
