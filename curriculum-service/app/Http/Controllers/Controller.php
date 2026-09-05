<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Batasi query pada institusi yang diminta, atau — bila filter tidak dikirim —
     * pada cakupan tenant user (institusi sendiri + seluruh turunannya).
     */
    protected function applyTenantScope($query, Request $request, bool $sertakanGlobal = false)
    {
        if ($request->filled('institusi_id')) {
            $id = $request->integer('institusi_id');

            return $sertakanGlobal
                ? $query->where(fn($w) => $w->whereNull('institusi_id')->orWhere('institusi_id', $id))
                : $query->where('institusi_id', $id);
        }

        $ids = $request->attributes->get('tenant_institusi_ids');
        if (is_array($ids)) {
            return $sertakanGlobal
                ? $query->where(fn($w) => $w->whereNull('institusi_id')->orWhereIn('institusi_id', $ids))
                : $query->whereIn('institusi_id', $ids);
        }

        return $query;
    }
}
