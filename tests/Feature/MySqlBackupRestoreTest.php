<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\FinanceTestCase;

class MySqlBackupRestoreTest extends FinanceTestCase
{
    use DatabaseMigrations;

    public function refreshDatabase(): void
    {
        /** Fixtures must be committed for the backup client connection. */
    }

    public function test_backup_restores_financial_rows_and_immutable_ledger_trigger_in_isolated_database(): void
    {
        $this->financeActors();
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('A MySQL server and client are required for restore testing.');
        }
        $finder = new ExecutableFinder;
        $dump = $finder->find('mysqldump') ?? (is_file('C:/xampp/mysql/bin/mysqldump.exe') ? 'C:/xampp/mysql/bin/mysqldump.exe' : null);
        $client = $finder->find('mysql') ?? (is_file('C:/xampp/mysql/bin/mysql.exe') ? 'C:/xampp/mysql/bin/mysql.exe' : null);
        if ($dump === null || $client === null) {
            $this->markTestSkipped('Install MySQL client tools to run the isolated backup/restore acceptance test.');
        }
        $this->commissionOrder(true);
        $connection = config('database.connections.'.DB::getDefaultConnection());
        $name = 'honey_restore_'.bin2hex(random_bytes(4)).'_test';
        $prefix = storage_path('framework/testing/'.$name);
        if (! is_dir(dirname($prefix))) {
            mkdir(dirname($prefix), 0700, true);
        }
        $created = false;
        $credentials = "[client]\n";
        foreach (['host' => 'host', 'port' => 'port', 'user' => 'username', 'password' => 'password'] as $option => $field) {
            $value = str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) ($connection[$field] ?? ''));
            $credentials .= $option.'="'.$value."\"\n";
        }
        file_put_contents($prefix.'.cnf', $credentials);
        chmod($prefix.'.cnf', 0600);
        try {
            DB::statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $created = true;
            $backup = new Process([$dump, '--defaults-extra-file='.$prefix.'.cnf', '--single-transaction', '--triggers', '--routines', '--skip-comments', '--result-file='.$prefix.'.sql', $connection['database']], timeout: 30);
            $backup->mustRun();
            $restore = new Process([$client, '--defaults-extra-file='.$prefix.'.cnf', $name], timeout: 30);
            $restore->setInput(fopen($prefix.'.sql', 'r'));
            $restore->mustRun();
            config(['database.connections.restore_acceptance' => [...$connection, 'database' => $name]]);
            DB::purge('restore_acceptance');
            foreach (['orders', 'order_items', 'commission_rules', 'commission_entries', 'wallet_ledger_entries', 'audit_logs', 'outbox_messages'] as $table) {
                $this->assertSame(DB::table($table)->orderBy('id')->get()->toJson(), DB::connection('restore_acceptance')->table($table)->orderBy('id')->get()->toJson(), 'Backup contents must match for '.$table);
            }
            $this->expectException(QueryException::class);
            DB::connection('restore_acceptance')->table('wallet_ledger_entries')->update(['amount_signed' => 1]);
        } finally {
            DB::disconnect('restore_acceptance');
            if ($created) {
                DB::statement('DROP DATABASE `'.$name.'`');
            }
            foreach (['.cnf', '.sql'] as $suffix) {
                if (is_file($prefix.$suffix)) {
                    unlink($prefix.$suffix);
                }
            }
        }
    }
}
