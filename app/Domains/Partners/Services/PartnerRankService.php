<?php

namespace App\Domains\Partners\Services;

use App\Domains\Commissions\Services\MlmProgramSettings;
use App\Domains\Orders\Models\Order;
use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Models\PartnerMonthlyRank;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PartnerRankService
{
    private const array RankNames = ['Member', 'Bronze', 'Silver', 'Gold', 'Platinum'];

    public function __construct(private MlmProgramSettings $settings) {}

    public function recalculateForOrder(Order $order): void
    {
        if ($order->partner_referral_id === null) {
            return;
        }

        $payment = DB::table('payments')
            ->where('order_id', $order->id)
            ->whereIn('status', ['verified', 'refunded'])
            ->whereNotNull('verified_at')
            ->orderBy('verified_at')
            ->first(['verified_at']);
        if ($payment === null) {
            return;
        }

        $periodStart = CarbonImmutable::parse($payment->verified_at)
            ->setTimezone('Asia/Jakarta')
            ->startOfMonth()
            ->toDateString();
        $this->recalculateForPartnerIds([$order->partner_referral_id], $periodStart);
    }

    /**
     * @return array{period_start: string, rank: string, personal_sales: int, team_sales: int, next_rank: string|null, personal_target: int|null, team_target: int|null, personal_progress: int, team_progress: int}
     */
    public function currentSummary(Partner $partner): array
    {
        $periodStart = now('Asia/Jakarta')->startOfMonth()->toDateString();
        $this->recalculateForPartnerIds([$partner->id], $periodStart);

        $monthlyRank = PartnerMonthlyRank::where('partner_id', $partner->id)
            ->where('period_start', $periodStart)
            ->firstOrFail();
        $requirements = $this->settings->current()->rank_requirements;
        $rankNames = self::RankNames;
        $nextRank = $rankNames[array_search($monthlyRank->rank, $rankNames, true) + 1] ?? null;
        $personalTarget = $nextRank === null ? null : $requirements[$nextRank]['personal_sales'];
        $teamTarget = $nextRank === null ? null : $requirements[$nextRank]['team_sales'];

        return [
            'period_start' => $periodStart,
            'rank' => $monthlyRank->rank,
            'personal_sales' => $monthlyRank->personal_sales,
            'team_sales' => $monthlyRank->team_sales,
            'next_rank' => $nextRank,
            'personal_target' => $personalTarget,
            'team_target' => $teamTarget,
            'personal_progress' => $personalTarget === null || $personalTarget === 0 ? 100 : min(100, (int) floor($monthlyRank->personal_sales * 100 / $personalTarget)),
            'team_progress' => $teamTarget === null || $teamTarget === 0 ? 100 : min(100, (int) floor($monthlyRank->team_sales * 100 / $teamTarget)),
        ];
    }

    public function recalculateCurrentMonth(): void
    {
        $partnerIds = array_values(DB::table('partners')->orderBy('id')->pluck('id')->map(static fn (int|string $id): int => (int) $id)->all());
        $this->recalculateForPartnerIds($partnerIds, now('Asia/Jakarta')->startOfMonth()->toDateString());
    }

    /** @param list<int> $changedPartnerIds */
    private function recalculateForPartnerIds(array $changedPartnerIds, string $periodStart): void
    {
        DB::transaction(function () use ($changedPartnerIds, $periodStart): void {
            $sponsorByPartner = DB::table('partners')->pluck('sponsor_partner_id', 'id')->all();
            $childrenBySponsor = [];
            foreach ($sponsorByPartner as $partnerId => $sponsorId) {
                if ($sponsorId !== null) {
                    $childrenBySponsor[(int) $sponsorId][] = (int) $partnerId;
                }
            }

            $targets = [];
            foreach ($changedPartnerIds as $partnerId) {
                $currentId = $partnerId;
                $seen = [];
                while ($currentId > 0 && ! isset($seen[$currentId])) {
                    $seen[$currentId] = true;
                    $targets[$currentId] = true;
                    $currentId = (int) ($sponsorByPartner[$currentId] ?? 0);
                }
            }
            if ($targets === []) {
                return;
            }

            Partner::whereIn('id', array_keys($targets))->orderBy('id')->lockForUpdate()->get(['id']);
            $salesByPartner = $this->monthlySalesByPartner($periodStart);
            $settings = $this->settings->current();
            $overrides = DB::table('partner_rank_overrides')->whereNull('revoked_at')->pluck('rank', 'partner_id');
            foreach (array_keys($targets) as $partnerId) {
                $descendants = [];
                $frontier = $childrenBySponsor[$partnerId] ?? [];
                while ($frontier !== []) {
                    $childId = array_pop($frontier);
                    if (isset($descendants[$childId]) || $childId === $partnerId) {
                        continue;
                    }
                    $descendants[$childId] = true;
                    array_push($frontier, ...($childrenBySponsor[$childId] ?? []));
                }

                $teamSales = 0;
                foreach (array_keys($descendants) as $descendantId) {
                    $teamSales += $salesByPartner[$descendantId] ?? 0;
                }
                $personalSales = $salesByPartner[$partnerId] ?? 0;
                $rank = 'Member';
                foreach (self::RankNames as $rankName) {
                    $targetsForRank = $settings->rank_requirements[$rankName];
                    if ($personalSales >= $targetsForRank['personal_sales'] && $teamSales >= $targetsForRank['team_sales']) {
                        $rank = $rankName;
                    }
                }

                PartnerMonthlyRank::updateOrCreate(
                    ['partner_id' => $partnerId, 'period_start' => $periodStart],
                    ['rank' => $overrides[$partnerId] ?? $rank, 'personal_sales' => $personalSales, 'team_sales' => $teamSales, 'calculated_at' => now()],
                );
            }
        }, attempts: 3);
    }

    /** @return array<int, int> */
    private function monthlySalesByPartner(string $periodStart): array
    {
        $localStart = CarbonImmutable::parse($periodStart, 'Asia/Jakarta');
        $periodFrom = $localStart->utc();
        $periodUntil = $localStart->addMonth()->utc();
        $verifiedOrders = DB::table('payments')
            ->select('order_id')
            ->selectRaw('MIN(verified_at) as verified_at')
            ->selectRaw('SUM(amount) as paid_amount')
            ->where('status', 'verified')
            ->whereNotNull('verified_at')
            ->groupBy('order_id')
            ->havingRaw('MIN(verified_at) >= ? AND MIN(verified_at) < ?', [$periodFrom, $periodUntil]);
        $refunds = DB::table('refunds')
            ->select('order_item_id')
            ->selectRaw('SUM(amount) as refunded_amount')
            ->where('status', 'processed')
            ->whereNotNull('order_item_id')
            ->groupBy('order_item_id');
        $netLineAmount = 'order_items.unit_price_snapshot * order_items.quantity - order_items.discount_snapshot - COALESCE(refund_totals.refunded_amount, 0)';

        return DB::table('orders')
            ->joinSub($verifiedOrders, 'verified_orders', fn ($join) => $join->on('verified_orders.order_id', '=', 'orders.id'))
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoinSub($refunds, 'refund_totals', fn ($join) => $join->on('refund_totals.order_item_id', '=', 'order_items.id'))
            ->whereNotNull('orders.partner_referral_id')
            ->where('orders.fraud_hold', false)
            ->whereColumn('verified_orders.paid_amount', 'orders.grand_total')
            ->whereIn('orders.status', ['paid', 'processing', 'shipped', 'delivered', 'completed'])
            ->groupBy('orders.partner_referral_id')
            ->selectRaw('orders.partner_referral_id, SUM(CASE WHEN '.$netLineAmount.' > 0 THEN '.$netLineAmount.' ELSE 0 END) as sales')
            ->pluck('sales', 'partner_referral_id')
            ->map(static fn (string|int $sales): int => (int) $sales)
            ->all();
    }
}
