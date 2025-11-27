<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class   JournalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $isAdmin = $request->user() && $request->user()->is_admin;

        // Build cache key based on request parameters
        $cacheKey = 'journals_index_' . md5(json_encode([
            'status' => $request->get('status'),
            'search' => $request->get('search'),
            'sort_by' => $request->get('sort_by', 'published_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
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

        $query = Journal::query();

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
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'published_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $journals = $query->paginate($request->get('per_page', 15));

        // Add base URL to images for all journals
        $journals->getCollection()->each(function ($journal) {
            $this->addImageUrls($journal);
        });

        // Generate ETag and Last-Modified
        $etag = md5(json_encode($journals));
        $lastModified = $journals->getCollection()->max('updated_at') ?? now();

        // Cache for 10 minutes (600 seconds) for public requests
        if ($shouldCache) {
            Cache::put($cacheKey, [
                'data' => $journals,
                'etag' => $etag,
                'last_modified' => $lastModified,
            ], now()->addMinutes(10));
        }

        $response = response()->json([
            'success' => true,
            'data' => $journals
        ]);

        return $this->addCacheHeaders($response, $etag, $lastModified);
    }

    public function show(Journal $journal, Request $request): JsonResponse
    {
        $isAdmin = $request->user() && $request->user()->is_admin;

        // Check if journal is published for non-admin users
        if (!$journal->is_published && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Journal not found'
            ], 404);
        }

        // Check ETag for conditional requests
        $etag = md5($journal->id . $journal->updated_at->timestamp);
        if ($request->header('If-None-Match') === '"' . $etag . '"') {
            return response()->json([], 304)->withHeaders([
                'ETag' => '"' . $etag . '"',
                'Cache-Control' => 'public, max-age=900',
            ]);
        }

        // Cache key for public requests
        $cacheKey = 'journal_' . $journal->id . '_' . $journal->updated_at->timestamp;
        $shouldCache = !$isAdmin && $journal->is_published;

        if ($shouldCache && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            $response = response()->json([
                'success' => true,
                'data' => $cached['data']
            ]);
            return $this->addCacheHeaders($response, $cached['etag'], $cached['last_modified']);
        }

        // Add base URL to image
        $this->addImageUrls($journal);

        $lastModified = $journal->updated_at;

        // Cache for 15 minutes for public requests
        if ($shouldCache) {
            Cache::put($cacheKey, [
                'data' => $journal,
                'etag' => $etag,
                'last_modified' => $lastModified,
            ], now()->addMinutes(15));
        }

        $response = response()->json([
            'success' => true,
            'data' => $journal
        ]);

        return $this->addCacheHeaders($response, $etag, $lastModified);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string|max:1000',
            'publication_date' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'required|string',
            'journal_pdf' => 'required|file|mimes:pdf|max:20240', // 10MB max
            'cover_image' => 'nullable|image|max:2048',
            'featured_image' => 'nullable|image|max:2048',
            'status' => 'in:draft,published',
            'published_at' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['journal_pdf', 'cover_image', 'featured_image']);

        // Set default content if not provided
        if (!isset($data['content']) || empty($data['content'])) {
            $data['content'] = $data['description']; // Use description as content if content is empty
        }

        // Handle journal PDF upload to S3
        if ($request->hasFile('journal_pdf')) {
            $data['journal_pdf'] = $request->file('journal_pdf')->store('journals/pdfs', 's3');
        }

        // Handle cover image upload to S3
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('journals/covers', 's3');
        }

        // Handle featured image upload to S3
        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('journals', 's3');
        }

        // Set published_at if status is published
        if (isset($data['status']) && $data['status'] === 'published' && empty($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        $journal = Journal::create($data);

        // Add base URL to images and PDF
        $this->addImageUrls($journal);

        // Clear journals list cache
        Cache::flush();

        return response()->json([
            'success' => true,
            'message' => 'Journal created successfully',
            'data' => $journal
        ], 201);
    }

    public function update(Request $request, Journal $journal): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string|max:1000',
            'publication_date' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'sometimes|required|string',
            'journal_pdf' => 'nullable|file|mimes:pdf|max:10240', // 10MB max
            'cover_image' => 'nullable|image|max:2048',
            'featured_image' => 'nullable|image|max:2048',
            'status' => 'in:draft,published',
            'published_at' => 'nullable|date',
            'metadata' => 'nullable|array',
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
        $updateableFields = ['title', 'content', 'excerpt', 'publication_date', 'category', 'description', 'status', 'published_at', 'metadata'];
        
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
                        
                        // Only include updateable fields (skip file fields)
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
            $data = $request->except(['journal_pdf', 'cover_image', 'featured_image']);
        }

        // Set default content if not provided
        if (isset($data['content']) && empty($data['content'])) {
            $data['content'] = $data['description'] ?? ''; // Use description as content if content is empty
        }

        // Handle journal PDF upload to S3
        if ($request->hasFile('journal_pdf')) {
            // Delete old PDF if exists
            if ($journal->journal_pdf) {
                Storage::disk('s3')->delete($journal->journal_pdf);
            }
            $data['journal_pdf'] = $request->file('journal_pdf')->store('journals/pdfs', 's3');
        }

        // Handle cover image upload to S3
        if ($request->hasFile('cover_image')) {
            // Delete old cover image if exists
            if ($journal->cover_image) {
                Storage::disk('s3')->delete($journal->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('journals/covers', 's3');
        }

        // Handle featured image upload to S3
        if ($request->hasFile('featured_image')) {
            // Delete old image if exists
            if ($journal->featured_image) {
                Storage::disk('s3')->delete($journal->featured_image);
            }
            $data['featured_image'] = $request->file('featured_image')->store('journals', 's3');
        }

        // Set published_at if status is published and not already set
        if (isset($data['status']) && $data['status'] === 'published' && !$journal->published_at) {
            $data['published_at'] = now();
        }

        $journal->update($data);

        // Clear cache for this journal
        $this->clearJournalCache($journal);

        // Add base URL to images and PDF
        $this->addImageUrls($journal);

        return response()->json([
            'success' => true,
            'message' => 'Journal updated successfully',
            'data' => $journal
        ]);
    }

    public function updateStatus(Journal $journal): JsonResponse
    {
        // Toggle status between published and draft
        $newStatus = $journal->status === 'published' ? 'draft' : 'published';

        $data = ['status' => $newStatus];

        // Set published_at when publishing
        if ($newStatus === 'published') {
            $data['published_at'] = now();
        }

        $journal->update($data);

        // Refresh the model to get updated data
        $journal->refresh();

        // Clear cache for this journal
        $this->clearJournalCache($journal);

        // Add base URL to images and PDF
        $this->addImageUrls($journal);

        return response()->json([
            'success' => true,
            'message' => "Journal status changed to {$newStatus} successfully",
            'data' => $journal
        ]);
    }

    public function destroy(Journal $journal): JsonResponse
    {
        // Delete all files from S3
        if ($journal->featured_image) {
            Storage::disk('s3')->delete($journal->featured_image);
        }
        if ($journal->cover_image) {
            Storage::disk('s3')->delete($journal->cover_image);
        }
        if ($journal->journal_pdf) {
            Storage::disk('s3')->delete($journal->journal_pdf);
        }

        // Clear cache before deletion
        $this->clearJournalCache($journal);

        $journal->delete();

        // Clear journals list cache
        Cache::flush();

        return response()->json([
            'success' => true,
            'message' => 'Journal deleted successfully'
        ]);
    }

    /**
     * Add base URL to journal images and PDF
     */
    private function addImageUrls($journal)
    {
        $baseUrl = rtrim(config('filesystems.disks.s3.url'), '/') . '/';

        // Add URL to featured image
        if ($journal->featured_image) {
            $journal->featured_image_url = $baseUrl . $journal->featured_image;
        }

        // Add URL to cover image
        if ($journal->cover_image) {
            $journal->cover_image_url = $baseUrl . $journal->cover_image;
        }

        // Add URL to journal PDF
        if ($journal->journal_pdf) {
            $journal->journal_pdf_url = $baseUrl . $journal->journal_pdf;
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
            'Cache-Control' => 'public, max-age=900', // 15 minutes
        ]);
    }

    /**
     * Clear cache for a specific journal
     */
    private function clearJournalCache($journal)
    {
        // Clear individual journal cache
        $cacheKey = 'journal_' . $journal->id . '_' . $journal->updated_at->timestamp;
        Cache::forget($cacheKey);

        // Clear all journals list caches
        Cache::flush(); // Simple approach - clears all cache
    }
}
