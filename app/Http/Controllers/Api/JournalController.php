<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class JournalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Journal::query();

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

        return response()->json([
            'success' => true,
            'data' => $journals
        ]);
    }

    public function show(Journal $journal): JsonResponse
    {
        // Check if journal is published for non-admin users
        if (!$journal->is_published && (!request()->user() || !request()->user()->is_admin)) {
            return response()->json([
                'success' => false,
                'message' => 'Journal not found'
            ], 404);
        }

        // Add base URL to image
        $this->addImageUrls($journal);

        return response()->json([
            'success' => true,
            'data' => $journal
        ]);
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
            'journal_pdf' => 'required|file|mimes:pdf|max:10240', // 10MB max
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
        if ($data['status'] === 'published' && !$data['published_at']) {
            $data['published_at'] = now();
        }

        $journal = Journal::create($data);

        // Add base URL to images and PDF
        $this->addImageUrls($journal);

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

        $data = $request->except(['journal_pdf', 'cover_image', 'featured_image']);

        // Set default content if not provided
        if (isset($data['content']) && empty($data['content'])) {
            $data['content'] = $data['description']; // Use description as content if content is empty
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
        if ($data['status'] === 'published' && !$journal->published_at) {
            $data['published_at'] = now();
        }

        $journal->update($data);

        // Add base URL to images and PDF
        $this->addImageUrls($journal);

        return response()->json([
            'success' => true,
            'message' => 'Journal updated successfully',
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

        $journal->delete();

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
}
