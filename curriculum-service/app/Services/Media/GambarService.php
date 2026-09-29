<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Berkas gambar kecil (logo institusi, spesimen tanda tangan) di disk privat
 * `local`: nama acak, diakses lewat endpoint, bukan folder publik.
 */
class GambarService
{
    /** Aturan validasi baku unggahan gambar (isi berkas diperiksa, SVG ditolak). */
    public const RULES = ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

    public function simpan(UploadedFile $file, string $folder, ?string $lama = null): string
    {
        $ext = $file->guessExtension() ?: 'png';
        $path = $file->storeAs($folder, Str::uuid() . '.' . $ext, 'local');
        $this->hapus($lama);

        return $path;
    }

    public function hapus(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    public function ada(?string $path): bool
    {
        return $path !== null && $path !== '' && Storage::disk('local')->exists($path);
    }

    public function absolut(?string $path): ?string
    {
        return $this->ada($path) ? Storage::disk('local')->path($path) : null;
    }

    public function respons(?string $path): Response
    {
        abort_unless($this->ada($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control'          => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Path yang aman untuk PhpWord (tak mendukung WEBP): WEBP dikonversi ke PNG sementara. */
    public function untukDocx(?string $absolut): ?string
    {
        if (! $absolut || ! is_file($absolut)) {
            return null;
        }
        if (mime_content_type($absolut) !== 'image/webp') {
            return $absolut;
        }
        if (! function_exists('imagecreatefromwebp') || ! ($img = @imagecreatefromwebp($absolut))) {
            return null;
        }
        $base = tempnam(sys_get_temp_dir(), 'img_');
        $tmp = $base . '.png';
        imagesavealpha($img, true);
        imagepng($img, $tmp);
        imagedestroy($img);
        register_shutdown_function(fn() => array_map(fn($f) => @unlink($f), [$base, $tmp]));

        return $tmp;
    }

    /** Penanda versi untuk cache-busting URL pratinjau. */
    public function versi(?string $path): ?string
    {
        return $path ? substr(md5($path), 0, 8) : null;
    }
}
