<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventGallery;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class EventController extends Controller
{
    /**
     * Manually authenticate user from Authorization header
     */
    private function getAuthenticatedUser(Request $request)
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);
        $accessToken = PersonalAccessToken::findToken($token);

        return $accessToken ? $accessToken->tokenable : null;
    }

    public function index(Request $request): JsonResponse
    {
        // Manually check authentication
        $user = $this->getAuthenticatedUser($request);
        $isAdmin = $user && $user->is_admin;

        // Build cache key based on request parameters
        $cacheKey = 'events_index_' . md5(json_encode([
            'status' => $request->get('status'),
            'search' => $request->get('search'),
            'sort_by' => $request->get('sort_by', 'start_date'),
            'sort_order' => $request->get('sort_order', 'asc'),
            'per_page' => $request->get('per_page', 15),
            'page' => $request->get('page', 1),
            'is_admin' => $isAdmin,
        ]));

        // Only cache public requests (non-admin)
        $shouldCache = !$isAdmin && !$request->has('search');

        if ($shouldCache && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            $response = response()->json([
                'success' => true,
                'data' => $cached['data']
            ]);

            // Add HTTP caching headers
            return $this->addCacheHeaders($response, $cached['etag'], $cached['last_modified']);
        }

        $query = Event::with('galleries');

        // Filter by status for public access
        if (!$isAdmin) {
            $query->published();
        }

        // Filter by status if admin
        if ($request->has('status') && $isAdmin) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'start_date');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $events = $query->paginate($request->get('per_page', 15));

        // Add base URL to images for all events
        $events->getCollection()->each(function ($event) {
            $this->addImageUrls($event);
        });

        $responseData = [
            'success' => true,
            'data' => $events
        ];

        // Generate ETag and Last-Modified
        $etag = md5(json_encode($events));
        $lastModified = $events->getCollection()->max('updated_at') ?? now();

        // Cache for 10 minutes (600 seconds) for public requests
        if ($shouldCache) {
            Cache::put($cacheKey, [
                'data' => $events,
                'etag' => $etag,
                'last_modified' => $lastModified,
            ], now()->addMinutes(10));
        }

        $response = response()->json($responseData);
        return $this->addCacheHeaders($response, $etag, $lastModified);
    }

    public function show(Event $event, Request $request): JsonResponse
    {
        // Manually check authentication
        $user = $this->getAuthenticatedUser($request);
        $isAdmin = $user && $user->is_admin;

        // Always refresh event data from database to ensure we have the latest status
        $event->refresh();

        // Check if event is published for non-admin users
        if (!$event->is_published && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        // Check ETag for conditional requests
        $etag = md5($event->id . $event->updated_at->timestamp);
        if ($request->header('If-None-Match') === '"' . $etag . '"') {
            return response()->json([], 304)->withHeaders([
                'ETag' => '"' . $etag . '"',
                'Cache-Control' => 'public, max-age=600',
            ]);
        }

        // Cache key for public requests
        $cacheKey = 'event_' . $event->id . '_' . $event->updated_at->timestamp;
        $shouldCache = !$isAdmin && $event->is_published;

        if ($shouldCache && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            $response = response()->json([
                'success' => true,
                'data' => $cached['data']
            ]);
            return $this->addCacheHeaders($response, $cached['etag'], $cached['last_modified']);
        }

        $event->load('galleries');

        // Add base URL to images
        $this->addImageUrls($event);

        $lastModified = $event->updated_at;

        // Cache for 15 minutes for public requests
        if ($shouldCache) {
            Cache::put($cacheKey, [
                'data' => $event,
                'etag' => $etag,
                'last_modified' => $lastModified,
            ], now()->addMinutes(15));
        }

        $response = response()->json([
            'success' => true,
            'data' => $event
        ]);

        return $this->addCacheHeaders($response, $etag, $lastModified);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'short_description' => 'nullable|string|max:500',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'location' => 'nullable|string|max:255',
            'status' => 'in:draft,published,cancelled',
            'featured_image' => 'nullable|image|max:2048',
            'galleries' => 'nullable|array',
            'galleries.*.image' => 'required|image|max:2048',
            'galleries.*.alt_text' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['featured_image', 'galleries']);

        // Handle featured image upload to S3
        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('events/featured', 's3');
        }

        $event = Event::create($data);

        // Handle gallery images
        if ($request->has('galleries')) {
            foreach ($request->galleries as $index => $gallery) {
                if (isset($gallery['image'])) {
                    $imagePath = $gallery['image']->store('events/gallery', 's3');

                    EventGallery::create([
                        'event_id' => $event->id,
                        'image_path' => $imagePath,
                        'alt_text' => $gallery['alt_text'] ?? null,
                        'sort_order' => $index,
                    ]);
                }
            }
        }

        $event->load('galleries');

        // Add base URL to images
        $this->addImageUrls($event);

        // Clear events list cache
        Cache::flush();

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully',
            'data' => $event
        ], 201);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'short_description' => 'nullable|string|max:500',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date|after:start_date',
            'location' => 'nullable|string|max:255',
            'status' => 'in:draft,published,cancelled',
            'featured_image' => 'nullable|image|max:2048',
            'galleries' => 'nullable|array',
            'galleries.*.image' => 'required|image|max:2048',
            'galleries.*.alt_text' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Collect updateable fields - handle both JSON and form-data
        $data = [];

        // Define fields that can be updated
        $updateableFields = ['title', 'description', 'short_description', 'start_date', 'end_date', 'location', 'status', 'metadata'];

        // For PUT requests with multipart/form-data, Laravel doesn't parse it automatically
        // Parse it manually from php://input
        $contentType = $request->header('Content-Type', '');
        $isMultipart = str_contains($contentType, 'multipart/form-data');

        if ($isMultipart && in_array($request->method(), ['PUT', 'PATCH'])) {
            // Parse multipart/form-data manually
            $rawContent = file_get_contents('php://input');

            // Extract boundary from Content-Type header
            $boundary = null;
            if (preg_match('/boundary=(.+)$/i', $contentType, $matches)) {
                $boundary = trim($matches[1]);
            }

            $formData = [];

            if ($boundary && !empty($rawContent)) {
                // Split by boundary
                $parts = explode('--' . $boundary, $rawContent);

                foreach ($parts as $part) {
                    // Skip empty parts and the closing boundary
                    $part = trim($part);
                    if (empty($part) || $part === '--') {
                        continue;
                    }

                    // Extract field name and value
                    if (preg_match('/name="([^"]+)"\s*\r?\n\r?\n(.*?)(?=\r?\n--|$)/s', $part, $matches)) {
                        $fieldName = $matches[1];
                        $fieldValue = trim($matches[2]);

                        // Only include updateable fields
                        if (in_array($fieldName, $updateableFields)) {
                            $formData[$fieldName] = $fieldValue;
                        }
                    }
                }
            }

            // Use parsed form data
            foreach ($updateableFields as $field) {
                if (isset($formData[$field]) && $formData[$field] !== null && $formData[$field] !== '') {
                    $data[$field] = $formData[$field];
                }
            }
        } else {
            // For JSON or POST requests, use standard methods
            $jsonData = $request->all();
            $inputData = $request->input();
            $requestData = $request->request->all();

            $allRequestData = array_merge($jsonData, $requestData, $inputData);

            foreach ($updateableFields as $field) {
                if (isset($allRequestData[$field]) && $allRequestData[$field] !== null) {
                    $data[$field] = $allRequestData[$field];
                }
            }
        }

        // Handle featured image upload to S3
        if ($request->hasFile('featured_image')) {
            // Delete old image if exists
            if ($event->featured_image) {
                Storage::disk('s3')->delete($event->featured_image);
            }
            $data['featured_image'] = $request->file('featured_image')->store('events/featured', 's3');
        }

        // Update the event with the collected data
        $event->update($data);

        // Refresh event to get latest data from database
        $event->refresh();

        // Clear cache for this event
        $this->clearEventCache($event);

        // Handle gallery images
        if ($request->has('galleries')) {
            // Delete existing gallery images
            foreach ($event->galleries as $gallery) {
                Storage::disk('s3')->delete($gallery->image_path);
                $gallery->delete();
            }

            // Upload new gallery images
            foreach ($request->galleries as $index => $gallery) {
                if (isset($gallery['image'])) {
                    $imagePath = $gallery['image']->store('events/gallery', 's3');

                    EventGallery::create([
                        'event_id' => $event->id,
                        'image_path' => $imagePath,
                        'alt_text' => $gallery['alt_text'] ?? null,
                        'sort_order' => $index,
                    ]);
                }
            }
        }

        $event->load('galleries');

        // Add base URL to images
        $this->addImageUrls($event);

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'data' => $event
        ]);
    }

    public function destroy(Event $event): JsonResponse
    {
        // Delete featured image
        if ($event->featured_image) {
            Storage::disk('s3')->delete($event->featured_image);
        }

        // Delete gallery images
        foreach ($event->galleries as $gallery) {
            Storage::disk('s3')->delete($gallery->image_path);
        }

        // Clear cache before deletion
        $this->clearEventCache($event);

        $event->delete();

        // Clear events list cache
        Cache::flush(); // Or use a more targeted approach

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully'
        ]);
    }

    public function addGallery(Request $request, Event $event): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:2048',
            'alt_text' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $imagePath = $request->file('image')->store('events/gallery', 's3');

        $gallery = EventGallery::create([
            'event_id' => $event->id,
            'image_path' => $imagePath,
            'alt_text' => $request->alt_text,
            'sort_order' => $event->galleries()->max('sort_order') + 1,
        ]);

        // Clear event cache when gallery is added
        $event->refresh();
        $this->clearEventCache($event);

        return response()->json([
            'success' => true,
            'message' => 'Gallery image added successfully',
            'data' => $gallery
        ]);
    }

    public function removeGallery(Event $event, EventGallery $gallery): JsonResponse
    {
        if ($gallery->event_id !== $event->id) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery image not found for this event'
            ], 404);
        }

        Storage::disk('s3')->delete($gallery->image_path);
        $gallery->delete();

        // Clear event cache when gallery is removed
        $event->refresh();
        $this->clearEventCache($event);

        return response()->json([
            'success' => true,
            'message' => 'Gallery image removed successfully'
        ]);
    }

    public function updateStatus(Request $request, Event $event): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,published,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStatus = $event->status;
        $event->update(['status' => $request->status]);

        // Refresh event to get latest data from database
        $event->refresh();

        // Clear cache when status changes
        $this->clearEventCache($event);

        $statusMessages = [
            'draft' => 'Event moved to draft',
            'published' => 'Event published successfully',
            'cancelled' => 'Event cancelled'
        ];

        return response()->json([
            'success' => true,
            'message' => $statusMessages[$request->status],
            'data' => [
                'id' => $event->id,
                'title' => $event->title,
                'status' => $event->status,
                'previous_status' => $oldStatus,
                'updated_at' => $event->updated_at
            ]
        ]);
    }

    /**
     * Add base URL to event images
     */
    private function addImageUrls($event)
    {
        $baseUrl = rtrim(config('filesystems.disks.s3.url'), '/') . '/';

        // Add URL to featured image
        if ($event->featured_image) {
            $event->featured_image_url = $baseUrl . $event->featured_image;
        }

        // Add URLs to gallery images
        if ($event->galleries) {
            $event->galleries->each(function ($gallery) use ($baseUrl) {
                $gallery->image_url = $baseUrl . $gallery->image_path;
            });
        }
    }

    /**
     * Add HTTP caching headers to response
     */
    private function addCacheHeaders($response, $etag, $lastModified)
    {
        return $response->withHeaders([
            'ETag' => '"' . $etag . '"',
            'Last-Modified' => $lastModified->toRfc7231String(),
            'Cache-Control' => 'public, max-age=600', // 10 minutes
        ]);
    }

    /**
     * Clear cache for a specific event
     */
    private function clearEventCache($event)
    {
        // Clear individual event cache
        $cacheKey = 'event_' . $event->id . '_' . $event->updated_at->timestamp;
        Cache::forget($cacheKey);

        // Clear all events list caches (pattern matching)
        // Note: For better performance, use cache tags if Redis is available
        Cache::flush(); // Simple approach - clears all cache
    }
}
