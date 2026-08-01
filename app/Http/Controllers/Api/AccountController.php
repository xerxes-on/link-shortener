<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Identify the API key making the request and the user it belongs to.
     *
     * Requires a valid, unexpired key but no particular permission, so a key
     * restricted to a single scope can still verify itself.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var ApiKey $apiKey */
        $apiKey = $request->input('api_key');

        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'key' => [
                    'name' => $apiKey->name,
                    'permissions' => $apiKey->permissions,
                    'expires_at' => $apiKey->expires_at,
                    'last_used_at' => $request->attributes->get('api_key_previous_last_used_at'),
                ],
            ],
        ]);
    }
}
