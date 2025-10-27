<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventGallery;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Event::with('galleries');

        // Filter by status for public access
        if (!$request->user() || !$request->user()->is_admin) {
            $query->published();
        }

        // Filter by status if admin
        if ($request->has('status') && $request->user()?->is_admin) {
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

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    public function show(Event $event): JsonResponse
    {
        // Check if event is published for non-admin users
        if (!$event->is_published && (!request()->user() || !request()->user()->is_admin)) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        $event->load('galleries');

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
}
