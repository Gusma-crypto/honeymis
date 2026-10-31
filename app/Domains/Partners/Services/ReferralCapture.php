<?php

namespace App\Domains\Partners\Services;

use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Models\ReferralAttribution;
use Illuminate\Support\Str;

class ReferralCapture
{
    public function valid(?string $token, ?int $buyerId = null): ?ReferralAttribution
    {
        if ($token === null || strlen($token) > 128) {
            return null;
        }
        $attribution = ReferralAttribution::with('partner.user')->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
        if ($attribution === null || $attribution->partner->status !== 'active' || ! $attribution->partner->user->isActive() || $attribution->partner->user_id === $buyerId) {
            return null;
        }

        return $attribution;
    }

    public function capture(string $code, ?string $existingToken, ?int $buyerId): ?string
    {
        if ($this->valid($existingToken, $buyerId) !== null) {
            return $existingToken;
        }
        $partner = Partner::with('user')->where('referral_code', Str::upper($code))->where('status', 'active')->first();
        if ($partner === null || ! $partner->user->isActive() || $partner->user_id === $buyerId) {
            return null;
        }
        $token = Str::random(64);
        ReferralAttribution::create(['partner_id' => $partner->id, 'token_hash' => hash('sha256', $token), 'source' => 'referral_link', 'expires_at' => now()->addDays(config('partners.referral_days'))]);

        return $token;
    }
}
