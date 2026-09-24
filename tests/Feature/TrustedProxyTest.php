<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_forwarded_https_requests_generate_secure_livewire_urls(): void
    {
        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '100.64.0.39',
                'HTTP_X_FORWARDED_HOST' => 'a.ufarmer.uz',
                'HTTP_X_FORWARDED_PORT' => '443',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ])
            ->get('http://a.ufarmer.uz/admin/login');

        $response
            ->assertOk()
            ->assertSee('https://a.ufarmer.uz/livewire', false)
            ->assertDontSee('http://a.ufarmer.uz/livewire', false);
    }

    public function test_forwarded_headers_from_untrusted_sources_are_ignored(): void
    {
        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTP_X_FORWARDED_HOST' => 'spoofed.example.com',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ])
            ->get('http://a.ufarmer.uz/admin/login');

        $response
            ->assertOk()
            ->assertSee('http://a.ufarmer.uz/livewire', false)
            ->assertDontSee('spoofed.example.com', false);
    }

    public function test_production_forces_https_for_generated_urls(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        URL::forceScheme(null);

        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', URL::to('/admin/login'));
    }
}
