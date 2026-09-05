<?php

namespace App\Http\Middleware;

use App\Models\Institusi;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $institusiId = $request->user()?->institusi_id;
        if ($institusiId === null) {
            return $next($request);
        }

        // Cakupan tenant = institusi user + seluruh unit turunannya (fakultas melihat prodi anak).
        $allowed = Institusi::idsDalamSubtree((int) $institusiId);

        if ($request->filled('institusi_id') && ! in_array($request->integer('institusi_id'), $allowed, true)) {
            abort(403, 'Anda tidak memiliki akses ke institusi tersebut.');
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Institusi && ! in_array((int) $parameter->getKey(), $allowed, true)) {
                abort(403, 'Anda tidak memiliki akses ke institusi tersebut.');
            }

            if (
                $parameter instanceof Model
                && $parameter->getAttribute('institusi_id') !== null
                && ! in_array((int) $parameter->getAttribute('institusi_id'), $allowed, true)
            ) {
                abort(403, 'Anda tidak memiliki akses ke data institusi tersebut.');
            }
        }

        // Daftar tanpa filter eksplisit dibatasi whereIn cakupan ini oleh applyTenantScope().
        $request->attributes->set('tenant_institusi_ids', $allowed);

        // Penulisan tanpa institusi_id eksplisit default ke institusi user sendiri.
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD') && ! $request->filled('institusi_id')) {
            $request->merge(['institusi_id' => (int) $institusiId]);
        }

        return $next($request);
    }
}
