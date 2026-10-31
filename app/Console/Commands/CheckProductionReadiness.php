<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckProductionReadiness extends Command
{
    protected $signature = 'honey:check-production {--local : Validate production mode bound to this computer only}';

    protected $description = 'Read-only checks for runtime, cached configuration, assets and database readiness';

    public function handle(): int
    {
        $local = (bool) $this->option('local');
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);
        $checks = [
            'Environment production' => app()->isProduction(),
            'Debug nonaktif' => config('app.debug') === false,
            'APP_KEY tersedia' => is_string(config('app.key')) && config('app.key') !== '',
            'Asset build tersedia' => is_file(public_path('build/manifest.json')),
            'Asset build dipakai tanpa Vite' => (bool) config('commerce.use_built_assets'),
            'Config cache tersedia' => app()->configurationIsCached(),
            'Route cache tersedia' => app()->routesAreCached(),
            'Boost nonaktif' => ! config('boost.enabled', true),
            'Database MySQL' => in_array(config('database.default'), ['mysql', 'mariadb'], true),
            'Queue persisten' => in_array(config('queue.default'), ['database', 'redis'], true),
            'Cache persisten' => ! in_array(config('cache.default'), ['array', 'null'], true),
            'Storage dapat ditulis' => is_writable(storage_path('framework')) && is_writable(storage_path('logs')),
            'URL sesuai target' => $local ? in_array($host, ['127.0.0.1', 'localhost', '::1'], true) : $scheme === 'https' && (bool) config('session.secure'),
        ];
        try {
            DB::connection()->getPdo();
            $migrator = app('migrator');
            $checks['Seluruh migration diterapkan'] = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan()) === [];
        } catch (Throwable) {
            $checks['Koneksi dan migration database'] = false;
        }
        if (! $local) {
            $checks['Bypass verifikasi uji coba nonaktif'] = ! config('auth.local_testing_bypass', false);
            $checks['Email memakai transport pengiriman'] = ! in_array(config('mail.default'), ['log', 'array'], true);
        }
        foreach ($checks as $label => $passed) {
            $this->line(($passed ? '<info>OK</info>' : '<error>GAGAL</error>').' '.$label);
        }
        if ($local) {
            $this->warn('Target lokal saja. Email log tidak dikirim ke internet; ini bukan persetujuan deployment publik atau transaksi riil.');
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
