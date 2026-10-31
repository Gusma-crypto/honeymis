<?php

namespace App\Domains\Partners\Actions;

use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\PartnerRankService;
use App\Domains\Shared\Services\AuditWriter;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageRankOverride
{
    public function __construct(private AuditWriter $audit, private PartnerRankService $ranks) {}

    public function handle(User $actor, Partner $partner, ?string $rank, string $reason): void
    {
        Gate::forUser($actor)->authorize('partners.manage');
        Gate::forUser($actor)->authorize('commission-rules.manage');
        if (($rank !== null && ! in_array($rank, ['Member', 'Bronze', 'Silver', 'Gold', 'Platinum'], true)) || mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Pilih peringkat yang valid dan tuliskan alasan minimal 10 karakter.']);
        }
        DB::transaction(function () use ($actor, $partner, $rank, $reason): void {
            Partner::whereKey($partner->id)->lockForUpdate()->firstOrFail();
            $before = DB::table('partner_rank_overrides')->where('partner_id', $partner->id)->first();
            if ($rank === null) {
                if ($before === null || $before->revoked_at !== null) {
                    throw ValidationException::withMessages(['rank' => 'Tidak ada override aktif untuk dicabut.']);
                }
                DB::table('partner_rank_overrides')->where('partner_id', $partner->id)->update([
                    'revoked_at' => now(), 'reason' => trim($reason), 'changed_by' => $actor->id, 'updated_at' => now(),
                ]);
            } else {
                DB::table('partner_rank_overrides')->updateOrInsert(['partner_id' => $partner->id], [
                    'rank' => $rank, 'reason' => trim($reason), 'changed_by' => $actor->id, 'revoked_at' => null,
                    'created_at' => $before->created_at ?? now(), 'updated_at' => now(),
                ]);
            }
            $this->audit->record($actor->id, $rank === null ? 'partner_rank_override_revoked' : 'partner_rank_overridden', 'partners', $partner->id, 'rank-override:'.Str::uuid(), [
                'previous' => $before === null ? null : (array) $before, 'rank' => $rank, 'reason' => trim($reason),
            ]);
            $this->ranks->currentSummary($partner);
        }, attempts: 3);
        Cache::forget('partners.performance.'.now('Asia/Jakarta')->format('Y-m'));
    }
}
