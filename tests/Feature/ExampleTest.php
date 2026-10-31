<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        $response = $this->get(route('home'));

        $response->assertOk();
    }

    public function test_home_renders_with_a_bounded_timeout_when_ssr_is_unavailable(): void
    {
        config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false]);
        Http::preventStrayRequests();
        $ssrAttempts = 0;
        Http::fake(function (Request $request, array $options) use (&$ssrAttempts): never {
            $ssrAttempts++;
            $this->assertSame(3, $options['timeout']);

            throw new ConnectionException('SSR server timed out.');
        });

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('data-page=', false);
        $this->assertSame(1, $ssrAttempts);
    }
}
