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

        if ($request->filled('institusi_id') && $request->integer('institusi_id') !== (int) $institusiId) {
            abort(403, 'Anda tidak memiliki akses ke institusi tersebut.');
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Institusi && (int) $parameter->getKey() !== (int) $institusiId) {
                abort(403, 'Anda tidak memiliki akses ke institusi tersebut.');
            }

            if (
                $parameter instanceof Model
                && $parameter->getAttribute('institusi_id') !== null
                && (int) $parameter->getAttribute('institusi_id') !== (int) $institusiId
            ) {
                abort(403, 'Anda tidak memiliki akses ke data institusi tersebut.');
            }
        }

        $request->merge(['institusi_id' => (int) $institusiId]);
        $request->query->set('institusi_id', (int) $institusiId);

        return $next($request);
    }
}
