<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Reports\Services\CommerceReport;
use App\Domains\Reports\Services\ReportWorkspace;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, CommerceReport $reports, ReportWorkspace $workspace): Response
    {
        $request->validate([
            'period' => ['nullable', 'date_format:Y-m'], 'from' => ['nullable', 'required_with:until', 'date_format:Y-m-d'],
            'until' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
            'tab' => ['nullable', 'in:summary,orders,commissions,network,members,products,payouts,activity'],
            'trend' => ['nullable', 'in:6,12,year'], 'top_period' => ['nullable', 'in:selected,previous,year'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $period = $request->string('period')->toString() ?: now('Asia/Jakarta')->format('Y-m');
        $month = CarbonImmutable::createFromFormat('!Y-m', $period, 'Asia/Jakarta');
        $from = $request->string('from')->toString() ?: $month->startOfMonth()->toDateString();
        $until = $request->string('until')->toString() ?: $month->endOfMonth()->toDateString();
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($until)) > 366) {
            throw ValidationException::withMessages(['until' => 'Periode laporan maksimal 366 hari.']);
        }
        $tab = $request->string('tab')->toString() ?: 'summary';
        $trend = $request->string('trend')->toString() ?: '6';
        $topPeriod = $request->string('top_period')->toString() ?: 'selected';

        return Inertia::render('admin/reports', ['report' => $reports->summary($request->user(), $from, $until),
            'workspace' => $workspace->present($request->user(), $from, $until, $tab, $trend, $topPeriod),
            'filters' => ['from' => $from, 'until' => $until, 'period' => $period, 'tab' => $tab, 'trend' => $trend, 'top_period' => $topPeriod],
            'financialAccess' => $request->user()->can('wallets.view'), 'auditAccess' => $request->user()->can('audit.view')]);
    }

    public function export(Request $request, CommerceReport $reports, ReportWorkspace $workspace): StreamedResponse
    {
        $request->validate(['type' => ['required', 'in:summary,orders,commissions,wallet,payouts,network,members,products,activity'], 'from' => ['required', 'date_format:Y-m-d'], 'until' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);

        return $request->input('type') === 'wallet'
            ? $reports->csv($request->user(), 'wallet', $request->string('from')->toString(), $request->string('until')->toString())
            : $workspace->csv($request->user(), $request->string('type')->toString(), $request->string('from')->toString(), $request->string('until')->toString());
    }
}
