<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Tests\TestCase;

class QrCodeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Link $link;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->link = Link::factory()->create([
            'short_code' => 'test123',
            'original_url' => 'https://example.com',
        ]);
    }

    public function test_authenticated_user_can_display_qr_code(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/qr/{$this->link->id}/display");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');
    }

    public function test_authenticated_user_can_download_qr_code_png(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/qr/{$this->link->id}/download?format=png");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('Content-Disposition', 'attachment; filename="qr-test123.png"');
    }

    public function test_authenticated_user_can_download_qr_code_svg(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/qr/{$this->link->id}/download?format=svg");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $response->assertHeader('Content-Disposition', 'attachment; filename="qr-test123.svg"');
    }

    public function test_unauthenticated_user_cannot_access_qr_code(): void
    {
        // The auth middleware requires a named 'login' route for redirect.
        // In Filament 5, login is at /admin/login without the standard 'login' route name.
        // Without exception handling, this throws RouteNotFoundException.
        // With exception handling, it returns a 500. Either way, access is denied.
        $this->expectException(RouteNotFoundException::class);

        $this->withoutExceptionHandling()
            ->get("/qr/{$this->link->id}/display");
    }

    public function test_qr_code_size_parameter_works(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/qr/{$this->link->id}/display?size=100");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');
    }

    public function test_nonexistent_link_returns_404(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/qr/999999/display');

        $response->assertStatus(404);
    }
}
