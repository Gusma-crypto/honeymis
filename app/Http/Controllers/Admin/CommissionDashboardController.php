<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Commissions\Services\GenerationCommissionCalculator;
use App\Domains\Commissions\Services\MlmProgramSettings;
use App\Domains\Wallet\Services\PayoutAvailabilitySummary;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CommissionDashboardController extends Controller
{
    public function index(Request $request, MlmProgramSettings $settings, GenerationCommissionCalculator $calculator, PayoutAvailabilitySummary $availability): Response
    {
        abort_unless($request->user()->can('wallets.view') || $request->user()->can('commission-rules.manage'), 403);
        $request->validate(['period' => ['nullable', 'date_format:Y-m'], 'type' => ['nullable', 'in:direct,network,referral'], 'status' => ['nullable', 'in:pending,available,reserved,paid_out,cancelled,reversed'], 'level' => ['nullable', 'integer', 'between:0,5'], 'base' => ['nullable', 'integer', 'between:1,1000000000'], 'first_order' => ['nullable', 'boolean']]);
        $month = $request->input('period') ?: now('Asia/Jakarta')->format('Y-m');
        $from = CarbonImmutable::createFromFormat('!Y-m', $month, 'Asia/Jakarta')->startOfMonth();
        $until = $from->addMonth();
        $net = 'entries.amount - entries.reversed_amount';
        $query = $this->entries()->where('entries.created_at', '>=', $from->utc())->where('entries.created_at', '<', $until->utc());
        foreach (['type' => 'entries.commission_type', 'status' => 'entries.status', 'level' => 'entries.level'] as $filter => $column) {
            if ($request->filled($filter)) {
                $query->where($column, $request->input($filter));
            }
        }
        $history = $query->orderByDesc('entries.id')->paginate(5)->withQueryString()->through(static fn ($entry): array => [
            'id' => $entry->id, 'order_id' => $entry->order_id, 'order_number' => $entry->order_number, 'created_at' => $entry->created_at,
            'type' => $entry->commission_type, 'level' => (int) $entry->level, 'base' => (int) $entry->base_amount,
            'amount' => max(0, (int) $entry->amount - (int) $entry->reversed_amount), 'original_amount' => (int) $entry->amount, 'reversed_amount' => (int) $entry->reversed_amount,
            'status' => $entry->status, 'source' => $entry->source_name, 'recipient' => $entry->recipient_name,
        ]);
        $periodEntries = DB::table('commission_entries as entries')->where('entries.created_at', '>=', $from->utc())->where('entries.created_at', '<', $until->utc())->whereNotIn('entries.status', ['cancelled', 'reversed']);
        $distribution = (clone $periodEntries)->select('entries.commission_type')->selectRaw('SUM('.$net.') as amount')->groupBy('entries.commission_type')->get()->map(static fn ($row): array => ['type' => $row->commission_type, 'amount' => (int) $row->amount])->all();
        $levels = (clone $periodEntries)->select('entries.level')->selectRaw('SUM('.$net.') as amount, COUNT(DISTINCT beneficiary_partner_id) as members')->groupBy('entries.level')->get()->keyBy('level');
        $trend = [];
        for ($offset = 5; $offset >= 0; $offset--) {
            $start = $from->subMonths($offset);
            $start->locale('id');
            $totals = DB::table('commission_entries')->where('created_at', '>=', $start->utc())->where('created_at', '<', $start->addMonth()->utc())->whereNotIn('status', ['cancelled', 'reversed'])->selectRaw('COALESCE(SUM(amount - reversed_amount), 0) as amount, COUNT(*) as transactions')->first();
            $trend[] = ['month' => $start->translatedFormat('M'), 'amount' => (int) $totals->amount, 'transactions' => (int) $totals->transactions];
        }
        $program = $settings->current();
        $simulation = array_values($calculator->calculate($request->integer('base', 250000), $program->generation_rates, $program->referral_first_order_bps, $request->boolean('first_order')));
        $pending = DB::table('wallet_ledger_entries')->where('bucket', 'pending')->sum('amount_signed');
        $monthNet = array_sum(array_column($distribution, 'amount'));

        return Inertia::render('admin/commissions', [
            'filters' => ['period' => $month, 'type' => $request->input('type', ''), 'status' => $request->input('status', ''), 'level' => $request->input('level', ''), 'base' => $request->integer('base', 250000), 'first_order' => $request->boolean('first_order')],
            'stats' => ['total' => (int) CommissionEntry::whereNotIn('status', ['cancelled', 'reversed'])->selectRaw('COALESCE(SUM(amount - reversed_amount), 0) as amount')->value('amount'), 'month' => $monthNet, 'pending' => (int) $pending, 'pending_count' => CommissionEntry::where('status', 'pending')->count(), 'available' => $availability->total()],
            'history' => $history, 'distribution' => $distribution, 'trend' => $trend,
            'levels' => collect(range(1, 5))->map(static fn (int $level): array => ['level' => $level, 'rate_bps' => $program->generation_rates[$level - 1], 'members' => (int) ($levels[$level]->members ?? 0), 'amount' => (int) ($levels[$level]->amount ?? 0)])->all(),
            'simulation' => $simulation, 'minimumPayout' => (int) config('partners.minimum_payout_idr'),
        ]);
    }

    private function entries(): Builder
    {
        return DB::table('commission_entries as entries')->join('order_items', 'order_items.id', '=', 'entries.order_item_id')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('partners as recipient', 'recipient.id', '=', 'entries.beneficiary_partner_id')->join('users as recipient_user', 'recipient_user.id', '=', 'recipient.user_id')
            ->leftJoin('partners as source', 'source.id', '=', 'orders.partner_referral_id')->leftJoin('users as source_user', 'source_user.id', '=', 'source.user_id')
            ->select('entries.*', 'orders.id as order_id', 'orders.order_number', 'recipient_user.name as recipient_name', 'source_user.name as source_name');
    }
}
