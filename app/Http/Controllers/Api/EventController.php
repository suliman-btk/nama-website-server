<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventGallery;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
        $query = Event::with('galleries');

        // Manually check authentication
        $user = $this->getAuthenticatedUser($request);
        $isAdmin = $user && $user->is_admin;

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

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    public function show(Event $event): JsonResponse
    {
        // Manually check authentication
        $user = $this->getAuthenticatedUser(request());
        $isAdmin = $user && $user->is_admin;

        // Check if event is published for non-admin users
        if (!$event->is_published && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        $event->load('galleries');

        // Add base URL to images
        $this->addImageUrls($event);

        return response()->json([
            'success' => true,
            'data' => $event
        ]);
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

        $data = $request->except(['featured_image', 'galleries']);

        // Handle featured image upload to S3
        if ($request->hasFile('featured_image')) {
            // Delete old image if exists
            if ($event->featured_image) {
                Storage::disk('s3')->delete($event->featured_image);
            }
            $data['featured_image'] = $request->file('featured_image')->store('events/featured', 's3');
        }

        $event->update($data);

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

        $event->delete();

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
}
