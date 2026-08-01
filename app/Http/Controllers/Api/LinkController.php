<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Models\LinkGroup;
use App\Services\LinkShortenerService;
use App\Services\LinkStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LinkController extends Controller
{
    protected LinkShortenerService $linkService;

    public function __construct(LinkShortenerService $linkService)
    {
        $this->linkService = $linkService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Click totals come from the denormalised links.click_count column, which is
        // maintained on redirect - no per-link aggregate over the clicks table needed
        $query = Link::where('created_by', $user->id)
            ->with('group:id,name,color');

        // Apply filters
        if ($request->has('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search') && is_string($request->get('search'))) {
            $search = '%'.$request->get('search').'%';

            $query->where(function ($query) use ($search) {
                $query->where('short_code', 'like', $search)
                    ->orWhere('custom_slug', 'like', $search)
                    ->orWhere('original_url', 'like', $search);
            });
        }

        // Sorting - anything outside the whitelist falls back to the default
        $sort = in_array($request->get('sort'), ['created_at', 'click_count'], true)
            ? $request->get('sort')
            : 'created_at';
        $directionInput = $request->get('direction');
        $direction = is_string($directionInput) && strtolower($directionInput) === 'asc' ? 'asc' : 'desc';

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $links = $query->orderBy($sort, $direction)
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $links->items(),
            'meta' => [
                'current_page' => $links->currentPage(),
                'last_page' => $links->lastPage(),
                'per_page' => $links->perPage(),
                'total' => $links->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'original_url' => 'required|url|max:2048',
            'custom_slug' => 'nullable|string|max:255|regex:/^[a-z0-9\-_]*$/|unique:links,short_code',
            'group_id' => 'nullable|exists:link_groups,id',
            'redirect_type' => 'nullable|in:301,302,307,308',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $shortCode = $this->linkService->generateUniqueCode($request->custom_slug);

            // Use default group if no group_id provided
            $groupId = $request->group_id;
            if (! $groupId) {
                $defaultGroup = LinkGroup::getDefault();
                if ($defaultGroup) {
                    $groupId = $defaultGroup->id;
                }
            }

            $link = Link::create([
                'short_code' => $shortCode,
                'original_url' => $request->original_url,
                'group_id' => $groupId,
                'redirect_type' => $request->redirect_type ?? 302,
                'is_active' => true,
                'expires_at' => $request->expires_at,
                'created_by' => auth()->id(),
                'custom_slug' => $request->custom_slug,
                'click_count' => 0,
            ]);

            $link->load('group:id,name,color');

            return response()->json([
                'data' => $link,
                'short_url' => url($link->short_code),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create link',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $link = Link::where('id', $id)
            ->where('created_by', auth()->id())
            ->with(['group:id,name,color'])
            ->first();

        if (! $link) {
            return response()->json([
                'error' => 'Not found',
                'message' => 'Link not found',
            ], 404);
        }

        return response()->json([
            'data' => $link,
            'short_url' => url($link->short_code),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $link = Link::where('id', $id)
            ->where('created_by', auth()->id())
            ->first();

        if (! $link) {
            return response()->json([
                'error' => 'Not found',
                'message' => 'Link not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'original_url' => 'sometimes|required|url|max:2048',
            'group_id' => 'nullable|exists:link_groups,id',
            'redirect_type' => 'sometimes|in:301,302,307,308',
            'is_active' => 'sometimes|boolean',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $link->update($request->only([
            'original_url', 'group_id', 'redirect_type', 'is_active', 'expires_at',
        ]));

        $link->load('group:id,name,color');

        return response()->json([
            'data' => $link,
            'short_url' => url($link->short_code),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $link = Link::where('id', $id)
            ->where('created_by', auth()->id())
            ->first();

        if (! $link) {
            return response()->json([
                'error' => 'Not found',
                'message' => 'Link not found',
            ], 404);
        }

        $link->delete();

        return response()->json([
            'message' => 'Link deleted successfully',
        ]);
    }

    /**
     * Simple link creation endpoint with minimal response
     *
     * This endpoint provides a streamlined response format
     * for applications that only need the shorturl field
     */
    public function simpleStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
            'keyword' => 'nullable|string|max:255|regex:/^[a-z0-9\-_]*$/|unique:links,short_code',
            'title' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'fail',
                'code' => 'error:validation',
                'message' => 'Validation failed: '.$validator->errors()->first(),
                'statusCode' => 422,
            ], 422);
        }

        try {
            $shortCode = $this->linkService->generateUniqueCode($request->keyword);

            // Use default group for simple API
            $defaultGroup = LinkGroup::getDefault();
            $groupId = $defaultGroup ? $defaultGroup->id : null;

            $link = Link::create([
                'short_code' => $shortCode,
                'original_url' => $request->url,
                'group_id' => $groupId,
                'redirect_type' => 302,
                'is_active' => true,
                'created_by' => auth()->id(),
                'custom_slug' => $request->keyword,
                'click_count' => 0,
            ]);

            $shortUrl = url($link->short_code);

            // Simple response format
            return response()->json([
                'url' => [
                    'keyword' => $link->short_code,
                    'url' => $link->original_url,
                    'title' => $request->title ?? '',
                    'date' => $link->created_at->format('Y-m-d H:i:s'),
                    'ip' => $request->ip(),
                ],
                'status' => 'success',
                'message' => $link->original_url.' added to database',
                'title' => $request->title ?? '',
                'shorturl' => $shortUrl,
                'statusCode' => 200,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'code' => 'error:creation',
                'message' => 'Failed to create short URL: '.$e->getMessage(),
                'statusCode' => 400,
            ], 400);
        }
    }

    /**
     * Get link statistics
     *
     * Accepts a `days` query parameter (default 30, clamped to 1-365) controlling
     * the width of the daily time series.
     */
    public function stats(Request $request, string $id, LinkStatsService $linkStats)
    {
        $link = Link::where('id', $id)
            ->where('created_by', auth()->id())
            ->first();

        if (! $link) {
            return response()->json([
                'error' => 'Not found',
                'message' => 'Link not found',
            ], 404);
        }

        $days = (int) $request->get('days', LinkStatsService::DEFAULT_DAYS);

        return response()->json([
            'data' => [
                'link' => $link->only([
                    'id', 'short_code', 'original_url', 'click_count', 'is_active', 'created_at',
                ]),
                'short_url' => url($link->short_code),
                'stats' => $linkStats->forLink($link, $days),
            ],
        ]);
    }
}
