<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Shared\Services\ListFilters;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request, ListFilters $lists): Response
    {
        Gate::authorize('audit.view');
        $filters = $request->validate(['action' => ['nullable', 'string', 'max:100'], 'search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', Rule::in(['orders', 'payout_requests', 'partners', 'users', 'commission_entries', 'commission_rules', 'customers', 'products', 'categories', 'product_variants', 'refunds'])]]);
        $query = DB::table('audit_logs')->orderByDesc('id');
        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if ($request->filled('search')) {
            $pattern = '%'.addcslashes($request->string('search')->toString(), '%_\\').'%';
            $query->where(fn ($query) => $query->where('action', 'like', $pattern)->orWhere('subject_id', 'like', $pattern)->orWhere('actor_id', 'like', $pattern));
        }
        if ($request->filled('status')) {
            $query->where('subject_type', $request->string('status')->toString());
        }

        return Inertia::render('admin/audit', ['listFilters' => $lists->values($request), 'logs' => $query->paginate(30)->withQueryString(), 'action' => $filters['action'] ?? '']);
    }
}
