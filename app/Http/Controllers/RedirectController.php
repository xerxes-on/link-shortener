<?php

namespace App\Http\Controllers;

use App\Events\LinkClicked;
use App\Events\LinkNotFound;
use App\Models\Link;
use App\Services\AppDeepLinkHandoff;
use App\Services\ClickTrackingService;
use App\Services\GeolocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RedirectController extends Controller
{
    public function redirect(
        string $shortCode,
        Request $request,
        GeolocationService $geoService,
        ClickTrackingService $clickTracking,
        AppDeepLinkHandoff $appDeepLinkHandoff,
    )
    {
        // Cache the link data, but not the redirect decision
        $cacheKey = "link_data_{$shortCode}";
        $link = Cache::remember($cacheKey, 3600, function () use ($shortCode) {
            return Link::with([
                'geoRules' => function ($query) {
                    $query->where('is_active', true)->orderBy('priority');
                },
                'abTest.variants' => function ($query) {
                    $query->orderBy('weight', 'desc');
                },
            ])
                ->select(['*']) // Ensure all fields including password and click_limit are loaded
                ->where('short_code', $shortCode)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->first();
        });

        if (! $link) {
            // Dispatch event for plugins/listeners to handle
            event(new LinkNotFound($shortCode, request()));

            // Track 404 attempts if configured
            if (config('shortener.not_found.track_attempts', true)) {
                // Could log to analytics or database here
                logger('Link not found: '.$shortCode, [
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'referer' => request()->header('referer'),
                ]);
            }

            // Custom redirect instead of 404
            if ($redirectUrl = config('shortener.not_found.redirect_url')) {
                return redirect($redirectUrl);
            }

            // Custom 404 view
            if ($customView = config('shortener.not_found.view')) {
                return response()->view($customView, [
                    'short_code' => $shortCode,
                ], 404);
            }

            abort(404);
        }

        // Only fetch real-time data if link has security features (password or click limit)
        $needsSecurityCheck = $link->click_limit !== null || ! empty($link->password);
        $currentLinkData = null;

        if ($needsSecurityCheck) {
            $currentLinkData = DB::table('links')->where('id', $link->id)->first(['click_count', 'click_limit', 'password']);

            // Check if click limit has been exceeded
            if ($currentLinkData && $currentLinkData->click_limit && $currentLinkData->click_count >= $currentLinkData->click_limit) {
                return response()->view('link.click-limit-exceeded', [
                    'link' => $link,
                ], 403);
            }

            // Check password protection (use real-time password data)
            if ($currentLinkData && ! empty($currentLinkData->password)) {
                $sessionKey = "link_password_{$link->id}";

                // Check if password was submitted
                if (request()->isMethod('post') && request()->has('password')) {
                    if (request('password') === $currentLinkData->password) {
                        // Store in session for future access
                        session([$sessionKey => true]);
                    } else {
                        return response()->view('link.password-form', [
                            'link' => $link,
                            'error' => 'Incorrect password. Please try again.',
                        ]);
                    }
                }

                // Check if password already provided in session
                if (! session($sessionKey)) {
                    return response()->view('link.password-form', [
                        'link' => $link,
                    ]);
                }
            }
        }

        // Determine target URL (A/B testing first, then geo rules)
        $targetUrl = $link->original_url;
        $selectedVariant = null;

        // Check A/B test if one exists and is active
        if ($link->abTest && $link->abTest->isActiveNow()) {
            $selectedVariant = $link->abTest->selectVariant();
            if ($selectedVariant) {
                $targetUrl = $selectedVariant->url;
            }
        }

        // Check geo rules if any exist (can override A/B test URL)
        if ($link->geoRules->isNotEmpty() && $geoService->isAvailable()) {
            $location = $geoService->getFullLocation(request()->ip());

            // Find the first matching rule (already sorted by priority)
            foreach ($link->geoRules as $rule) {
                if ($rule->matchesLocation($location)) {
                    $targetUrl = $rule->redirect_url;
                    break;
                }
            }
        }

        // Extract UTM parameters from the request
        $utmParams = $this->extractUtmParameters(request());

        // Append UTM parameters to target URL
        if (! empty($utmParams)) {
            $targetUrl = $this->appendUtmParameters($targetUrl, $utmParams);
        }

        // Dispatch click event for analytics/plugins
        event(new LinkClicked($link, request()));

        // Increment A/B test variant counter if applicable
        if ($selectedVariant) {
            $selectedVariant->incrementClicks();
        }

        // Prepare click data
        $clickData = [
            'link_id' => $link->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'referer' => request()->header('referer'),
            'clicked_at' => now()->format('Y-m-d H:i:s'),
            'utm_source' => $utmParams['utm_source'] ?? null,
            'utm_medium' => $utmParams['utm_medium'] ?? null,
            'utm_campaign' => $utmParams['utm_campaign'] ?? null,
            'utm_term' => $utmParams['utm_term'] ?? null,
            'utm_content' => $utmParams['utm_content'] ?? null,
            'ab_test_variant_id' => $selectedVariant?->id,
            'increment_click_count' => $link->click_limit === null, // Only increment async if no click limit
        ];

        // Track click using configured method
        $clickTracking->trackClick($link, $clickData);

        // For links with click limits using queue method, increment synchronously for accurate enforcement
        $trackingMethod = config('shortener.analytics.click_tracking_method', 'queue');
        if ($link->click_limit !== null && $trackingMethod === 'queue') {
            DB::table('links')->where('id', $link->id)->increment('click_count');
        }

        $handoffUrl = $appDeepLinkHandoff->resolve($targetUrl, $request->userAgent());

        if ($handoffUrl !== null) {
            return redirect()->away($handoffUrl, 302)->withHeaders([
                'Cache-Control' => 'no-store, private',
                'Vary' => 'User-Agent',
            ]);
        }

        return redirect($targetUrl, $link->redirect_type);
    }

    /**
     * Extract UTM parameters from the request
     */
    private function extractUtmParameters($request): array
    {
        $utmParams = [];

        // Valid UTM parameter names
        $validUtmParams = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
        ];

        foreach ($validUtmParams as $param) {
            if ($request->has($param) && ! empty($request->get($param))) {
                $utmParams[$param] = $request->get($param);
            }
        }

        return $utmParams;
    }

    /**
     * Append UTM parameters to the target URL
     */
    private function appendUtmParameters(string $url, array $utmParams): string
    {
        if (empty($utmParams)) {
            return $url;
        }

        // Parse the URL to handle existing query parameters
        $parsedUrl = parse_url($url);

        // Get existing query parameters
        $existingParams = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $existingParams);
        }

        // Merge UTM parameters (UTM parameters take precedence)
        $allParams = array_merge($existingParams, $utmParams);

        // Rebuild the URL
        $newUrl = $parsedUrl['scheme'].'://'.$parsedUrl['host'];

        if (isset($parsedUrl['port'])) {
            $newUrl .= ':'.$parsedUrl['port'];
        }

        if (isset($parsedUrl['path'])) {
            $newUrl .= $parsedUrl['path'];
        }

        if (! empty($allParams)) {
            $newUrl .= '?'.http_build_query($allParams);
        }

        if (isset($parsedUrl['fragment'])) {
            $newUrl .= '#'.$parsedUrl['fragment'];
        }

        return $newUrl;
    }
}
