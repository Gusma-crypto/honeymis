<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Reports\Services\CommerceReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, CommerceReport $reports): Response
    {
        $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $from = $request->string('from')->toString() ?: now()->startOfMonth()->format('Y-m-d');
        $until = $request->string('until')->toString() ?: now()->format('Y-m-d');

        return Inertia::render('admin/reports', ['report' => $reports->summary($request->user(), $from, $until), 'filters' => ['from' => $from, 'until' => $until], 'financialAccess' => $request->user()->can('wallets.view')]);
    }

    public function export(Request $request, CommerceReport $reports): StreamedResponse
    {
        $request->validate(['type' => ['required', 'in:orders,commissions,wallet,payouts'], 'from' => ['required', 'date_format:Y-m-d'], 'until' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);

        return $reports->csv($request->user(), $request->string('type')->toString(), $request->string('from')->toString(), $request->string('until')->toString());
    }
}
