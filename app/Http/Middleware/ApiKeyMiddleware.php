<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $apiKey = $this->getApiKeyFromRequest($request);

        if (! $apiKey) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'API key is required',
            ], 401);
        }

        // Find the API key in database - try direct match first, then hash
        $keyRecord = ApiKey::where('api_key', $apiKey)->first();

        // Fallback to hash lookup for backward compatibility
        if (! $keyRecord) {
            $keyRecord = ApiKey::where('key_hash', hash('sha256', $apiKey))->first();
        }

        if (! $keyRecord) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API key',
            ], 401);
        }

        // Check if key is expired
        if ($keyRecord->isExpired()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'API key has expired',
            ], 401);
        }

        // Check permission if specified
        if ($permission && ! $keyRecord->hasPermission($permission)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Insufficient permissions',
            ], 403);
        }

        // Preserve the previous value before it is overwritten, so endpoints such as
        // /api/me can report when the key was last used rather than "just now"
        $request->attributes->set('api_key_previous_last_used_at', $keyRecord->last_used_at);

        // Update last used timestamp
        $keyRecord->update(['last_used_at' => now()]);

        // Set the API key user for the request
        $request->merge(['api_key' => $keyRecord]);
        auth()->setUser($keyRecord->user);

        return $next($request);
    }

    private function getApiKeyFromRequest(Request $request): ?string
    {
        // Try Authorization header first (Bearer token format)
        $authorization = $request->header('Authorization');
        if ($authorization && str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7);
        }

        // Try X-API-Key header
        $apiKey = $request->header('X-API-Key');
        if ($apiKey) {
            return $apiKey;
        }

        // Try query parameter as fallback
        return $request->query('api_key');
    }
}
