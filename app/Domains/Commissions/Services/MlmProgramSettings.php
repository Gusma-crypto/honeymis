<?php

namespace App\Domains\Commissions\Services;

use App\Domains\Commissions\Models\MlmProgramSetting;
use App\Domains\Shared\Services\AuditWriter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MlmProgramSettings
{
    public function __construct(private MlmCommissionBudget $budget, private AuditWriter $audit) {}

    public function current(): MlmProgramSetting
    {
        return MlmProgramSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'version' => 1,
                'referral_first_order_bps' => config('mlm.referral_first_order_bps'),
                'generation_rates' => config('mlm.generation_rates'),
                'rank_requirements' => config('mlm.rank_requirements'),
            ],
        );
    }

    public function lockCurrent(): MlmProgramSetting
    {
        $this->current();

        return MlmProgramSetting::whereKey(1)->lockForUpdate()->firstOrFail();
    }

    /** @param array{referral_first_order_bps: int, generation_rates: list<int>, rank_requirements: array<string, array{personal_sales: int, team_sales: int}>} $data */
    public function update(User $actor, array $data): MlmProgramSetting
    {
        return DB::transaction(function () use ($actor, $data): MlmProgramSetting {
            $settings = $this->lockCurrent();
            $networkRate = max($data['generation_rates'][0], $data['referral_first_order_bps']) + array_sum(array_slice($data['generation_rates'], 1));
            if (2000 + $networkRate > (int) config('mlm.maximum_total_commission_bps')) {
                throw ValidationException::withMessages([
                    'generation_rates' => 'Total komisi langsung maksimum dan bonus jaringan tidak boleh melebihi 60% nilai penjualan produk.',
                ]);
            }
            $violations = $this->budget->violations($data['generation_rates'], $data['referral_first_order_bps']);
            if ($violations > 0) {
                throw ValidationException::withMessages([
                    'generation_rates' => 'Tarif komisi melampaui margin produk pada '.$violations.' varian aktif. Sesuaikan tarif langsung atau margin produknya sebelum menyimpan.',
                ]);
            }
            $beforeVersion = $settings->version;
            if (! DB::table('mlm_program_versions')->where('version', $beforeVersion)->exists()) {
                $this->archive($settings);
            }
            $settings->update([
                'version' => $beforeVersion + 1,
                'referral_first_order_bps' => $data['referral_first_order_bps'],
                'generation_rates' => $data['generation_rates'],
                'rank_requirements' => $data['rank_requirements'],
                'updated_by' => $actor->id,
            ]);
            $this->archive($settings);
            $this->audit->record($actor->id, 'mlm_program_settings_updated', 'mlm_program_settings', 1, 'mlm-settings:version:'.($beforeVersion + 1), [
                'version' => $beforeVersion + 1,
                'referral_first_order_bps' => $data['referral_first_order_bps'],
                'generation_rates' => $data['generation_rates'],
                'rank_requirements' => $data['rank_requirements'],
            ]);

            return $settings->refresh();
        }, attempts: 3);
    }

    private function archive(MlmProgramSetting $settings): void
    {
        DB::table('mlm_program_versions')->insert([
            'version' => $settings->version,
            'configuration' => json_encode([
                'referral_first_order_bps' => $settings->referral_first_order_bps,
                'generation_rates' => $settings->generation_rates,
                'rank_requirements' => $settings->rank_requirements,
            ], JSON_THROW_ON_ERROR),
            'changed_by' => $settings->updated_by,
            'recorded_at' => now(),
        ]);
    }
}
