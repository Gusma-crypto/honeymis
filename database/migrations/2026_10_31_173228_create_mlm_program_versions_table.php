<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mlm_program_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->json('configuration');
            $table->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
        });

        foreach (DB::table('mlm_program_settings')->get() as $settings) {
            DB::table('mlm_program_versions')->insert([
                'version' => $settings->version,
                'configuration' => json_encode([
                    'referral_first_order_bps' => (int) $settings->referral_first_order_bps,
                    'generation_rates' => json_decode($settings->generation_rates, true, flags: JSON_THROW_ON_ERROR),
                    'rank_requirements' => json_decode($settings->rank_requirements, true, flags: JSON_THROW_ON_ERROR),
                ], JSON_THROW_ON_ERROR),
                'changed_by' => $settings->updated_by,
                'recorded_at' => now(),
            ]);
        }

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::unprepared("CREATE TRIGGER mlm_versions_no_{$operation} BEFORE {$operation} ON mlm_program_versions BEGIN SELECT RAISE(ABORT, 'MLM configuration history is append-only'); END");
            }
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::unprepared("CREATE TRIGGER mlm_versions_no_{$operation} BEFORE {$operation} ON mlm_program_versions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'MLM configuration history is append-only'");
            }
        } elseif ($driver === 'pgsql') {
            DB::unprepared("CREATE FUNCTION honey_protect_mlm_versions() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'MLM configuration history is append-only'; END; $$ LANGUAGE plpgsql");
            DB::unprepared('CREATE TRIGGER mlm_versions_immutable BEFORE UPDATE OR DELETE ON mlm_program_versions FOR EACH ROW EXECUTE FUNCTION honey_protect_mlm_versions()');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('mlm_program_versions')->exists() && ! app()->runningUnitTests()) {
            throw new RuntimeException('Cannot remove recorded MLM configuration history. Use a forward migration.');
        }

        Schema::dropIfExists('mlm_program_versions');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS honey_protect_mlm_versions()');
        }
    }
};
