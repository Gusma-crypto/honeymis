<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        if (in_array($connection, ['mysql', 'mariadb'], true)) {
            $database = $app['config']->get('database.connections.'.$connection.'.database');
            if (! is_string($database) || ! str_ends_with($database, '_test')) {
                throw new \RuntimeException('MySQL tests require a dedicated database whose name ends in _test.');
            }
        }

        return $app;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
