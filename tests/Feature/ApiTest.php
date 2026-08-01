<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ApiKey $apiKey;

    protected string $plainTextKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Create API key
        $this->plainTextKey = 'sk_'.str_repeat('test123', 5).'abcdef';
        $this->apiKey = ApiKey::create([
            'name' => 'Test API Key',
            'key_hash' => hash('sha256', $this->plainTextKey),
            'permissions' => ['links:create', 'links:read', 'stats:read'],
            'created_by' => $this->user->id,
        ]);
    }

    public function test_api_requires_authentication(): void
    {
        $response = $this->getJson('/api/links');

        // Debug: see what we actually get
        $this->assertNotEquals(404, $response->getStatusCode(), 'API route not found: '.$response->getContent());

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'API key is required',
            ]);
    }

    public function test_api_rejects_invalid_key(): void
    {
        $response = $this->getJson('/api/links', [
            'Authorization' => 'Bearer invalid_key',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'Invalid API key',
            ]);
    }

    public function test_api_accepts_valid_key_via_header(): void
    {
        $response = $this->getJson('/api/links', [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ]);

        $response->assertStatus(200);
    }

    public function test_api_accepts_valid_key_via_x_api_key_header(): void
    {
        $response = $this->getJson('/api/links', [
            'X-API-Key' => $this->plainTextKey,
        ]);

        $response->assertStatus(200);
    }

    public function test_api_can_create_link(): void
    {
        $linkData = [
            'original_url' => 'https://example.com/test',
            'custom_slug' => 'test-link',
        ];

        $response = $this->postJson('/api/links', $linkData, [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'short_code',
                    'original_url',
                    'is_active',
                    'created_at',
                ],
                'short_url',
            ])
            ->assertJson([
                'data' => [
                    'short_code' => 'test-link',
                    'original_url' => 'https://example.com/test',
                ],
            ]);

        $this->assertDatabaseHas('links', [
            'short_code' => 'test-link',
            'original_url' => 'https://example.com/test',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_api_can_list_links(): void
    {
        // Create some test links
        Link::factory()->create([
            'short_code' => 'link1',
            'original_url' => 'https://example.com/1',
            'created_by' => $this->user->id,
        ]);

        Link::factory()->create([
            'short_code' => 'link2',
            'original_url' => 'https://example.com/2',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/links', [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'short_code',
                        'original_url',
                        'is_active',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_api_list_returns_click_counts_from_the_link_column(): void
    {
        Link::factory()->create([
            'short_code' => 'counted',
            'created_by' => $this->user->id,
            'click_count' => 42,
        ]);

        $response = $this->getJson('/api/links', [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.click_count', 42)
            ->assertJsonMissingPath('data.0.clicks');
    }

    public function test_api_list_can_be_searched(): void
    {
        Link::factory()->create([
            'short_code' => 'alpha',
            'original_url' => 'https://example.com/holiday-rentals',
            'created_by' => $this->user->id,
        ]);

        Link::factory()->create([
            'short_code' => 'beta',
            'custom_slug' => 'summer-promo',
            'original_url' => 'https://example.com/other',
            'created_by' => $this->user->id,
        ]);

        Link::factory()->create([
            'short_code' => 'gamma',
            'original_url' => 'https://example.com/unrelated',
            'created_by' => $this->user->id,
        ]);

        // Matches original_url
        $response = $this->getJson('/api/links?search=holiday', [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ]);
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.short_code', 'alpha');

        // Matches custom_slug
        $response = $this->getJson('/api/links?search=summer', [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ]);
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.short_code', 'beta');

        // Matches short_code
        $response = $this->getJson('/api/links?search=gam', [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ]);
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.short_code', 'gamma');
    }

    public function test_api_list_search_stays_scoped_to_the_key_owner(): void
    {
        $otherUser = User::factory()->create();

        Link::factory()->create([
            'short_code' => 'theirs',
            'original_url' => 'https://example.com/holiday-rentals',
            'created_by' => $otherUser->id,
        ]);

        $this->getJson('/api/links?search=holiday', [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ])->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_api_list_can_be_sorted_by_click_count(): void
    {
        Link::factory()->create([
            'short_code' => 'low',
            'created_by' => $this->user->id,
            'click_count' => 5,
        ]);

        Link::factory()->create([
            'short_code' => 'high',
            'created_by' => $this->user->id,
            'click_count' => 90,
        ]);

        Link::factory()->create([
            'short_code' => 'mid',
            'created_by' => $this->user->id,
            'click_count' => 40,
        ]);

        $response = $this->getJson('/api/links?sort=click_count', [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.0.short_code', 'high')
            ->assertJsonPath('data.2.short_code', 'low');

        $response = $this->getJson('/api/links?sort=click_count&direction=asc', [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.0.short_code', 'low')
            ->assertJsonPath('data.2.short_code', 'high');
    }

    public function test_api_list_ignores_an_unknown_sort_column(): void
    {
        Link::factory()->create([
            'short_code' => 'older',
            'created_by' => $this->user->id,
            'created_at' => now()->subDay(),
        ]);

        Link::factory()->create([
            'short_code' => 'newer',
            'created_by' => $this->user->id,
            'created_at' => now(),
        ]);

        // Falls back to created_at desc rather than erroring or injecting the column
        $this->getJson('/api/links?sort=password&direction=sideways', [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.0.short_code', 'newer');
    }

    public function test_api_enforces_permissions(): void
    {
        // Create API key with only read permissions
        $readOnlyKey = 'sk_'.str_repeat('readonly', 5).'abcdef';
        ApiKey::create([
            'name' => 'Read Only Key',
            'key_hash' => hash('sha256', $readOnlyKey),
            'permissions' => ['links:read'],
            'created_by' => $this->user->id,
        ]);

        // Try to create a link with read-only key
        $response = $this->postJson('/api/links', [
            'original_url' => 'https://example.com/test',
        ], [
            'Authorization' => 'Bearer '.$readOnlyKey,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Forbidden',
                'message' => 'Insufficient permissions',
            ]);
    }

    public function test_api_validates_link_creation(): void
    {
        $response = $this->postJson('/api/links', [
            'original_url' => 'not-a-valid-url',
        ], [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'errors' => [
                    'original_url',
                ],
            ]);
    }

    public function test_api_updates_last_used_timestamp(): void
    {
        $this->assertNull($this->apiKey->last_used_at);

        $this->getJson('/api/links', [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ]);

        $this->apiKey->refresh();
        $this->assertNotNull($this->apiKey->last_used_at);
    }

    public function test_simple_api_format(): void
    {
        $linkData = [
            'url' => 'https://example.com/simple-test',
            'keyword' => 'simple-test',
            'title' => 'Simple API Test Page',
        ];

        $response = $this->postJson('/api/simple', $linkData, [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'url' => [
                    'keyword',
                    'url',
                    'title',
                    'date',
                    'ip',
                ],
                'status',
                'message',
                'title',
                'shorturl',
                'statusCode',
            ])
            ->assertJson([
                'url' => [
                    'keyword' => 'simple-test',
                    'url' => 'https://example.com/simple-test',
                    'title' => 'Simple API Test Page',
                ],
                'status' => 'success',
                'statusCode' => 200,
            ]);

        // Verify the shorturl field contains the full URL
        $responseData = $response->json();
        $this->assertStringContainsString('simple-test', $responseData['shorturl']);

        // Verify database
        $this->assertDatabaseHas('links', [
            'short_code' => 'simple-test',
            'original_url' => 'https://example.com/simple-test',
            'created_by' => $this->user->id,
        ]);
    }
}
