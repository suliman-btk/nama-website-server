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
                    ->orWhere('author', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'published_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $journals = $query->paginate($request->get('per_page', 15));

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

        return response()->json([
            'success' => true,
            'data' => $journal
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:1000',
            'author' => 'required|string|max:255',
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

        $data = $request->except(['featured_image']);

        // Handle featured image upload to S3
        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('journals', 's3');
        }

        // Set published_at if status is published
        if ($data['status'] === 'published' && !$data['published_at']) {
            $data['published_at'] = now();
        }

        $journal = Journal::create($data);

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
            'content' => 'sometimes|required|string',
            'excerpt' => 'nullable|string|max:1000',
            'author' => 'sometimes|required|string|max:255',
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

        $data = $request->except(['featured_image']);

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

        return response()->json([
            'success' => true,
            'message' => 'Journal updated successfully',
            'data' => $journal
        ]);
    }

    public function destroy(Journal $journal): JsonResponse
    {
        // Delete featured image
        if ($journal->featured_image) {
            Storage::disk('s3')->delete($journal->featured_image);
        }

        $journal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Journal deleted successfully'
        ]);
    }
}
