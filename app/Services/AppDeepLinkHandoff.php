<?php

namespace App\Services;

final class AppDeepLinkHandoff
{
    public function resolve(string $targetUrl, ?string $userAgent): ?string
    {
        $route = $this->extractRoute($targetUrl);

        if ($route === null) {
            return null;
        }

        $userAgent ??= '';

        if (stripos($userAgent, 'android') !== false) {
            return $this->androidIntent($route['app_path'], $targetUrl);
        }

        if ($this->isAppleDevice($userAgent)) {
            return 'https://'.config('shortener.app_handoff.universal_link_host').'/'.$route['web_path'];
        }

        return null;
    }

    /**
     * @return array{web_path: string, app_path: string}|null
     */
    private function extractRoute(string $targetUrl): ?array
    {
        $url = parse_url($targetUrl);

        if (! is_array($url)
            || strtolower($url['scheme'] ?? '') !== 'https'
            || ! isset($url['host'], $url['path'])
            || ! preg_match('/^\d+\.redirect\.appmetrica\.yandex\.com$/i', $url['host'])) {
            return null;
        }

        $segments = explode('/', trim($url['path'], '/'));
        $universalLinkHost = (string) config('shortener.app_handoff.universal_link_host');

        if (count($segments) !== 3 || strcasecmp(rawurldecode($segments[0]), $universalLinkHost) !== 0) {
            return null;
        }

        $routeSegment = rawurldecode($segments[1]);
        $appRoutes = config('shortener.app_handoff.routes', []);

        if (! is_array($appRoutes) || ! isset($appRoutes[$routeSegment])) {
            return null;
        }

        $identifier = rawurldecode($segments[2]);

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9-]*$/', $identifier) !== 1) {
            return null;
        }

        $encodedIdentifier = rawurlencode($identifier);

        return [
            'web_path' => rawurlencode($routeSegment).'/'.$encodedIdentifier,
            'app_path' => trim((string) $appRoutes[$routeSegment], '/').'/'.$encodedIdentifier,
        ];
    }

    private function androidIntent(string $appPath, string $fallbackUrl): string
    {
        return 'intent://'.$appPath
            .'#Intent;scheme='.config('shortener.app_handoff.scheme')
            .';package='.config('shortener.app_handoff.android_package')
            .';S.browser_fallback_url='.rawurlencode($fallbackUrl)
            .';end';
    }

    private function isAppleDevice(string $userAgent): bool
    {
        return preg_match('/iPhone|iPad|iPod/i', $userAgent) === 1
            || stripos($userAgent, 'Macintosh') !== false;
    }
}
