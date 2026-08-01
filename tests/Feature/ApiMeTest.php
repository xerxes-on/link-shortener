<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ApiMeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ApiKey $apiKey;

    protected string $plainTextKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Jane Broker',
            'email' => 'jane@example.com',
        ]);

        $this->plainTextKey = 'sk_'.str_repeat('test123', 5).'abcdef';
        $this->apiKey = ApiKey::create([
            'name' => 'Intranet key',
            'key_hash' => hash('sha256', $this->plainTextKey),
            'permissions' => null,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_me_returns_the_user_and_key_for_a_valid_key(): void
    {
        $response = $this->getJson('/api/me', [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'key' => ['name', 'permissions', 'expires_at', 'last_used_at'],
                ],
            ])
            ->assertJson([
                'data' => [
                    'user' => [
                        'id' => $this->user->id,
                        'name' => 'Jane Broker',
                        'email' => 'jane@example.com',
                    ],
                    'key' => [
                        'name' => 'Intranet key',
                        'permissions' => null,
                        'expires_at' => null,
                    ],
                ],
            ]);
    }

    public function test_me_never_echoes_the_key_back(): void
    {
        $response = $this->getJson('/api/me', [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ]);

        $response->assertStatus(200);
        $this->assertStringNotContainsString($this->plainTextKey, $response->getContent());
        $this->assertStringNotContainsString($this->apiKey->key_hash, $response->getContent());
    }

    public function test_me_works_for_a_permission_restricted_key(): void
    {
        $restrictedKey = 'sk_'.str_repeat('limited', 5).'abcdef';
        ApiKey::create([
            'name' => 'Create only key',
            'key_hash' => hash('sha256', $restrictedKey),
            'permissions' => ['links:create'],
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/me', [
            'Authorization' => 'Bearer '.$restrictedKey,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'key' => [
                        'name' => 'Create only key',
                        'permissions' => ['links:create'],
                    ],
                ],
            ]);
    }

    public function test_me_rejects_an_expired_key(): void
    {
        $expiredKey = 'sk_'.str_repeat('expired', 5).'abcdef';
        ApiKey::create([
            'name' => 'Expired key',
            'key_hash' => hash('sha256', $expiredKey),
            'permissions' => null,
            'expires_at' => now()->subDay(),
            'created_by' => $this->user->id,
        ]);

        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$expiredKey])
            ->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'API key has expired',
            ]);
    }

    public function test_me_rejects_an_invalid_key(): void
    {
        $this->getJson('/api/me', ['Authorization' => 'Bearer nonsense'])
            ->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'Invalid API key',
            ]);
    }

    public function test_me_requires_a_key(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'API key is required',
            ]);
    }

    public function test_me_reports_the_previous_use_not_the_current_request(): void
    {
        $firstUse = Carbon::parse('2026-06-15 09:00:00');

        // Never used before: nothing to report
        $this->travelTo($firstUse);
        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$this->plainTextKey])
            ->assertStatus(200)
            ->assertJson(['data' => ['key' => ['last_used_at' => null]]]);

        $this->travelTo($firstUse->copy()->addHour());
        $response = $this->getJson('/api/me', ['Authorization' => 'Bearer '.$this->plainTextKey]);

        $response->assertStatus(200);
        $this->assertSame(
            $firstUse->timestamp,
            Carbon::parse($response->json('data.key.last_used_at'))->timestamp
        );
    }
}
