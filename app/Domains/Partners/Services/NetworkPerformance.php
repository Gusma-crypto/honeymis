<?php

namespace App\Domains\Partners\Services;

use App\Domains\Partners\Models\PartnerMonthlyRank;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NetworkPerformance
{
    public function __construct(private PartnerRankService $ranks) {}

    /** @return array<int, array{rank: string, personal_sales: int, team_sales: int, commissions: int}>|null */
    public function forUser(User $user): ?array
    {
        if (! $user->can('reports.view') || ! $user->can('wallets.view')) {
            return null;
        }

        return Cache::remember('partners.performance.'.now('Asia/Jakarta')->format('Y-m'), 15, function (): array {
            $this->ranks->recalculateCurrentMonth();
            $from = now('Asia/Jakarta')->startOfMonth()->utc();
            $until = now('Asia/Jakarta')->startOfMonth()->addMonth()->utc();
            $commissions = DB::table('commission_entries')->where('created_at', '>=', $from)->where('created_at', '<', $until)
                ->select('beneficiary_partner_id')->selectRaw('SUM(amount - reversed_amount) as total')->groupBy('beneficiary_partner_id')->pluck('total', 'beneficiary_partner_id');

            return PartnerMonthlyRank::where('period_start', now('Asia/Jakarta')->startOfMonth()->toDateString())->get()
                ->mapWithKeys(fn (PartnerMonthlyRank $rank): array => [$rank->partner_id => [
                    'rank' => $rank->rank, 'personal_sales' => $rank->personal_sales, 'team_sales' => $rank->team_sales,
                    'commissions' => (int) ($commissions[$rank->partner_id] ?? 0),
                ]])->all();
        });
    }
}
