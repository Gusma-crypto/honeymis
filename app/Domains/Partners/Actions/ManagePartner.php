<?php

namespace App\Domains\Partners\Actions;

use App\Domains\Partners\Models\Partner;
use App\Domains\Shared\Services\AuditWriter;
use App\Domains\Shared\Services\CommerceNotifier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class ManagePartner
{
    public function __construct(private AuditWriter $audit) {}

    public function addToNetwork(User $actor, User $member, string $placement, ?int $relativePartnerId): Partner
    {
        Gate::forUser($actor)->authorize('partners.manage');

        return DB::transaction(function () use ($actor, $member, $placement, $relativePartnerId): Partner {
            $member = User::whereKey($member->id)->lockForUpdate()->firstOrFail();
            if (! $member->isActive() || $member->email_verified_at === null) {
                throw ValidationException::withMessages(['email' => 'Akun harus aktif dan email sudah diverifikasi sebelum ditambahkan ke jaringan.']);
            }
            if (Partner::where('user_id', $member->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'Akun ini sudah menjadi mitra. Penempatan mitra lama tidak dapat diubah melalui form ini.']);
            }
            $relative = $placement === 'root' ? null : Partner::whereKey($relativePartnerId)->lockForUpdate()->firstOrFail();
            $sponsorId = match ($placement) {
                'below' => $relative->id,
                'sibling' => $relative->sponsor_partner_id,
                'root' => null,
                default => throw ValidationException::withMessages(['placement' => 'Penempatan tidak valid.']),
            };
            $sponsor = $sponsorId === null ? null : Partner::whereKey($sponsorId)->lockForUpdate()->firstOrFail();
            if ($sponsor !== null && ($sponsor->status !== 'active' || ! $sponsor->user->isActive())) {
                throw ValidationException::withMessages(['placement' => 'Sponsor tujuan harus aktif. Aktifkan sponsor terlebih dahulu.']);
            }
            $partner = $this->apply($member, $sponsor?->referral_code);
            $this->audit->record($actor->id, 'partner_placed_by_admin', 'partners', $partner->id, 'partner:'.$partner->id.':admin-placement', [
                'placement' => $placement, 'relative_partner_id' => $relativePartnerId, 'sponsor_partner_id' => $sponsorId,
            ]);

            return $partner;
        }, attempts: 3);
    }

    public function apply(User $user, ?string $sponsorCode): Partner
    {
        abort_unless($user->isActive() && $user->hasVerifiedEmail(), 403);

        return DB::transaction(function () use ($user, $sponsorCode): Partner {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = Partner::where('user_id', $user->id)->first();
            if ($existing !== null) {
                return $existing;
            }
            $sponsor = empty($sponsorCode) ? null : Partner::where('referral_code', Str::upper($sponsorCode))->where('status', 'active')->first();
            if (! empty($sponsorCode) && ($sponsor === null || $sponsor->user_id === $user->id || ! $sponsor->user->isActive())) {
                throw ValidationException::withMessages(['sponsor_code' => 'Kode sponsor tidak valid.']);
            }
            $partner = Partner::create(['user_id' => $user->id, 'referral_code' => Str::upper(Str::random(12)), 'sponsor_partner_id' => $sponsor?->id, 'status' => 'pending']);
            $this->audit->record($user->id, 'partner_applied', 'partners', $partner->id, 'partner:'.$partner->id.':applied', ['sponsor_partner_id' => $sponsor?->id]);

            return $partner;
        }, attempts: 3);
    }

    public function decide(User $actor, Partner $partner, string $status, string $key): void
    {
        Gate::forUser($actor)->authorize('partners.manage');
        if (! in_array($status, ['active', 'suspended', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Status mitra tidak valid.']);
        }
        DB::transaction(function () use ($actor, $partner, $status, $key): void {
            $locked = Partner::whereKey($partner->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('audit_logs')->where('idempotency_key', 'partner-decision:'.$key)->first();
            if ($existing !== null) {
                $state = json_decode($existing->after_json, true, flags: JSON_THROW_ON_ERROR);
                if ((int) $existing->subject_id !== $partner->id || (int) $existing->actor_id !== $actor->id || $state['status'] !== $status) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Key telah digunakan untuk keputusan lain.']);
                }

                return;
            }
            if ($status === 'active' && ! $locked->user->isActive()) {
                throw ValidationException::withMessages(['status' => 'Akun mitra dinonaktifkan.']);
            }
            $locked->update(['status' => $status, 'approved_at' => $status === 'active' ? ($locked->approved_at ?? now()) : $locked->approved_at]);
            if ($status === 'active') {
                $locked->user->assignRole(Role::findOrCreate('partner', 'web'));
            }
            app(CommerceNotifier::class)->queue($locked->user_id, 'partner-decision:'.$key, 'Status kemitraan', 'Pengajuan kemitraan Anda: '.$status, route('partner.dashboard', absolute: false));
            $this->audit->record($actor->id, 'partner_status_changed', 'partners', $locked->id, 'partner-decision:'.$key, ['status' => $status]);
        }, attempts: 3);
    }
}
