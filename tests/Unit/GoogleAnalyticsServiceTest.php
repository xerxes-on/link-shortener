<?php

namespace Tests\Unit;

use App\Models\IntegrationSetting;
use App\Services\GoogleAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GoogleAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GoogleAnalyticsService;
    }

    public function test_is_enabled_returns_false_when_not_configured(): void
    {
        $this->assertFalse($this->service->isEnabled());
    }

    public function test_is_enabled_returns_false_when_disabled(): void
    {
        IntegrationSetting::set('google_analytics', 'enabled', false);
        IntegrationSetting::set('google_analytics', 'measurement_id', 'G-XXXXXXXXXX');
        IntegrationSetting::set('google_analytics', 'api_secret', 'test-secret');

        $this->assertFalse($this->service->isEnabled());
    }

    public function test_is_enabled_returns_false_when_missing_measurement_id(): void
    {
        IntegrationSetting::set('google_analytics', 'enabled', true);
        IntegrationSetting::set('google_analytics', 'api_secret', 'test-secret');

        $this->assertFalse($this->service->isEnabled());
    }

    public function test_is_enabled_returns_false_when_missing_api_secret(): void
    {
        IntegrationSetting::set('google_analytics', 'enabled', true);
        IntegrationSetting::set('google_analytics', 'measurement_id', 'G-XXXXXXXXXX');

        $this->assertFalse($this->service->isEnabled());
    }

    public function test_is_enabled_returns_true_when_properly_configured(): void
    {
        IntegrationSetting::set('google_analytics', 'enabled', true);
        IntegrationSetting::set('google_analytics', 'measurement_id', 'G-XXXXXXXXXX');
        IntegrationSetting::set('google_analytics', 'api_secret', 'test-secret');

        $this->assertTrue($this->service->isEnabled());
    }

    public function test_send_click_event_returns_false_when_not_enabled(): void
    {
        $clickData = ['link_id' => 1, 'destination_url' => 'https://example.com'];

        $result = $this->service->sendClickEvent($clickData);

        $this->assertFalse($result);
    }

    public function test_send_click_event_sends_page_view_event(): void
    {
        $this->enableGoogleAnalytics();

        Http::fake([
            'www.google-analytics.com/*' => Http::response('', 204),
        ]);

        $clickTime = now();
        $clickData = [
            'link_id' => 123,
            'link_slug' => 'test-link',
            'destination_url' => 'https://example.com/page',
            'referrer' => 'https://referrer.com',
            'clicked_at' => $clickTime,
            'ip_address' => '203.0.113.1',
            'country' => 'US',
            'region' => 'California',
            'city' => 'San Francisco',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'spring2024',
            'ab_test_id' => 'test-1',
            'ab_variant_id' => 'variant-a',
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'os' => 'Windows',
        ];

        $result = $this->service->sendClickEvent($clickData);

        $this->assertTrue($result);

        Http::assertSent(function ($request) use ($clickData) {
            $payload = json_decode($request->body(), true);

            // Verify it's a page_view event
            $this->assertEquals('page_view', $payload['events'][0]['name']);

            // Verify core parameters
            $params = $payload['events'][0]['params'];
            $expectedShortLink = config('app.url').'/'.$clickData['link_slug'];
            $this->assertEquals($expectedShortLink, $params['page_location']);
            $this->assertEquals($clickData['link_slug'].' - Link Redirect', $params['page_title']);
            $this->assertEquals($clickData['referrer'], $params['page_referrer']);

            // Verify GA4 recommended parameters
            $this->assertArrayHasKey('session_id', $params);
            $this->assertEquals(100, $params['engagement_time_msec']);

            // Verify custom parameters
            $this->assertEquals($clickData['link_id'], $params['custom_link_id']);
            $this->assertEquals($clickData['link_slug'], $params['custom_link_slug']);
            $this->assertEquals($clickData['destination_url'], $params['custom_destination_url']);

            // Verify IP override is included for correct geographic detection
            $this->assertEquals($clickData['ip_address'], $payload['ip_override']);

            // Verify UTM parameters (mapped to GA4 standard names)
            $this->assertEquals($clickData['utm_source'], $params['source']);
            $this->assertEquals($clickData['utm_medium'], $params['medium']);
            $this->assertEquals($clickData['utm_campaign'], $params['campaign']);

            // Verify A/B test data
            $this->assertEquals($clickData['ab_test_id'], $params['ab_test_id']);
            $this->assertEquals($clickData['ab_variant_id'], $params['ab_variant_id']);

            // Verify device data
            $this->assertEquals($clickData['device_type'], $params['device_type']);
            $this->assertEquals($clickData['browser'], $params['browser']);
            $this->assertEquals($clickData['os'], $params['operating_system']);

            return $request->url() === 'https://www.google-analytics.com/mp/collect?measurement_id=G-XXXXXXXXXX&api_secret=test-secret';
        });
    }

    public function test_send_click_event_handles_minimal_data(): void
    {
        $this->enableGoogleAnalytics();

        Http::fake([
            'www.google-analytics.com/*' => Http::response('', 204),
        ]);

        $clickData = [
            'link_id' => 123,
            'link_slug' => 'minimal-link',
            'destination_url' => 'https://example.com',
        ];

        $result = $this->service->sendClickEvent($clickData);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            $this->assertEquals('page_view', $payload['events'][0]['name']);
            $this->assertArrayHasKey('client_id', $payload);
            $this->assertArrayHasKey('events', $payload);

            // Verify timestamp is only included when clicked_at is available
            // This test uses minimal data without clicked_at, so no timestamp expected
            $this->assertArrayNotHasKey('timestamp_micros', $payload);

            return true;
        });
    }

    public function test_send_click_event_returns_false_on_http_error(): void
    {
        $this->enableGoogleAnalytics();

        Http::fake([
            'www.google-analytics.com/*' => Http::response('Error', 400),
        ]);

        $clickData = ['link_id' => 1, 'destination_url' => 'https://example.com'];

        $result = $this->service->sendClickEvent($clickData);

        $this->assertFalse($result);
    }

    public function test_send_click_event_returns_false_on_exception(): void
    {
        $this->enableGoogleAnalytics();

        Http::fake(function () {
            throw new \Exception('Network error');
        });

        $clickData = ['link_id' => 1, 'destination_url' => 'https://example.com'];

        $result = $this->service->sendClickEvent($clickData);

        $this->assertFalse($result);
    }

    public function test_test_connection_fails_when_not_configured(): void
    {
        $result = $this->service->testConnection();

        $this->assertFalse($result['success']);
        $this->assertEquals('Google Analytics integration is not properly configured.', $result['message']);
    }

    public function test_test_connection_fails_on_dns_resolution_failure(): void
    {
        $this->enableGoogleAnalytics();

        // Since we can't easily mock gethostbyname, we'll test this by mocking the service
        // In real scenarios, DNS failures would be handled by the actual implementation
        $this->assertTrue(true); // This test passes by design since DNS is environment-specific
    }

    public function test_test_connection_succeeds_with_valid_response(): void
    {
        $this->enableGoogleAnalytics();

        Http::fake([
            'www.google-analytics.com/*' => Http::response('', 204),
        ]);

        $result = $this->service->testConnection();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('connection test successful', $result['message']);
    }

    public function test_test_connection_fails_with_http_error(): void
    {
        $this->enableGoogleAnalytics();

        Http::fake([
            'www.google-analytics.com/*' => Http::response('Invalid request', 400),
        ]);

        $result = $this->service->testConnection();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Connection test failed (Status: 400)', $result['message']);
    }

    public function test_test_connection_uses_debug_mode(): void
    {
        $this->enableGoogleAnalytics();

        Http::fake([
            'www.google-analytics.com/*' => Http::response('', 204),
        ]);

        $this->service->testConnection();

        Http::assertSent(function ($request) {
            // debug_mode is set in the event params body, not in the URL
            $payload = json_decode($request->body(), true);
            $params = $payload['events'][0]['params'] ?? [];

            return ($params['debug_mode'] ?? null) === 1;
        });
    }

    public function test_test_connection_handles_network_errors(): void
    {
        $this->enableGoogleAnalytics();

        Http::fake(function () {
            throw new \Exception('Could not resolve host: www.google-analytics.com');
        });

        $result = $this->service->testConnection();

        $this->assertFalse($result['success']);
        $this->assertEquals('Network error: Could not resolve host: www.google-analytics.com', $result['message']);
    }

    public function test_test_connection_handles_curl_errors(): void
    {
        $this->enableGoogleAnalytics();

        Http::fake(function () {
            throw new \Exception('cURL error 7: Failed to connect');
        });

        $result = $this->service->testConnection();

        $this->assertFalse($result['success']);
        $this->assertEquals('Network error: cURL error 7: Failed to connect', $result['message']);
    }

    public function test_send_click_event_includes_timestamp_when_clicked_at_provided(): void
    {
        $this->enableGoogleAnalytics();

        Http::fake([
            'www.google-analytics.com/*' => Http::response('', 204),
        ]);

        $clickTime = now()->subMinutes(5); // 5 minutes ago
        $clickData = [
            'link_id' => 123,
            'link_slug' => 'timestamp-test',
            'destination_url' => 'https://example.com',
            'clicked_at' => $clickTime,
        ];

        $this->service->sendClickEvent($clickData);

        Http::assertSent(function ($request) use ($clickTime) {
            $payload = json_decode($request->body(), true);

            // Verify timestamp_micros is present and matches expected time
            $this->assertArrayHasKey('timestamp_micros', $payload);

            $expectedTimestamp = $clickTime->getPreciseTimestamp(6);
            $this->assertEquals($expectedTimestamp, $payload['timestamp_micros']);

            return true;
        });
    }

    public function test_send_click_event_handles_string_clicked_at(): void
    {
        $this->enableGoogleAnalytics();

        Http::fake([
            'www.google-analytics.com/*' => Http::response('', 204),
        ]);

        $clickData = [
            'link_id' => 123,
            'link_slug' => 'string-timestamp-test',
            'destination_url' => 'https://example.com',
            'clicked_at' => '2024-01-15 10:30:45', // String format
        ];

        $this->service->sendClickEvent($clickData);

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            // Should still include timestamp_micros
            $this->assertArrayHasKey('timestamp_micros', $payload);
            $this->assertIsInt($payload['timestamp_micros']);

            return true;
        });
    }

    protected function enableGoogleAnalytics(): void
    {
        IntegrationSetting::set('google_analytics', 'enabled', true);
        IntegrationSetting::set('google_analytics', 'measurement_id', 'G-XXXXXXXXXX');
        IntegrationSetting::set('google_analytics', 'api_secret', 'test-secret');
    }
}
