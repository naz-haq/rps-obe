<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Pengurutan aman berbasis whitelist kolom (anti SQL-injection, OWASP A03).
 * Kolom di luar whitelist / sort kosong => fallback ke default.
 */
trait AppliesSorting
{
    /**
     * @param  array<int,string>  $allowed  kolom yang boleh diurut
     */
    protected function applySort(Builder $query, Request $request, array $allowed, string $default, string $defaultDir = 'asc'): Builder
    {
        $sort = (string) $request->query('sort', '');
        $dir = strtolower((string) $request->query('dir', '')) === 'desc' ? 'desc' : 'asc';

        if ($sort === '' || ! in_array($sort, $allowed, true)) {
            $sort = $default;
            $dir = strtolower($defaultDir) === 'desc' ? 'desc' : 'asc';
        }

        return $query->orderBy($sort, $dir);
    }
}
