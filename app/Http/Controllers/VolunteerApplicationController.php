<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VolunteerApplication;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VolunteerApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = VolunteerApplication::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by application type
        if ($request->has('application_type')) {
            $query->where('application_type', $request->application_type);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $applications = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }

    public function show(VolunteerApplication $volunteerApplication): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $volunteerApplication
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'application_type' => 'required|in:volunteer,intern',
            'message' => 'required|string|max:2000',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120', // 5MB max
            'additional_info' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['resume']);

        // Handle resume upload to S3
        if ($request->hasFile('resume')) {
            $data['resume_path'] = $request->file('resume')->store('applications/resumes', 's3');
        }

        $application = VolunteerApplication::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully',
            'data' => $application
        ], 201);
    }

    public function update(Request $request, VolunteerApplication $volunteerApplication): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,approved,rejected',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $volunteerApplication->update($request->only(['status', 'admin_notes']));

        return response()->json([
            'success' => true,
            'message' => 'Application updated successfully',
            'data' => $volunteerApplication
        ]);
    }

    public function destroy(VolunteerApplication $volunteerApplication): JsonResponse
    {
        // Delete resume if exists
        if ($volunteerApplication->resume_path) {
            Storage::disk('s3')->delete($volunteerApplication->resume_path);
        }

        $volunteerApplication->delete();

        return response()->json([
            'success' => true,
            'message' => 'Application deleted successfully'
        ]);
    }

    public function approve(VolunteerApplication $volunteerApplication): JsonResponse
    {
        $volunteerApplication->update([
            'status' => 'approved',
            'admin_notes' => $volunteerApplication->admin_notes . "\n\nApproved on " . now()->format('Y-m-d H:i:s')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application approved successfully',
            'data' => $volunteerApplication
        ]);
    }

    public function reject(VolunteerApplication $volunteerApplication): JsonResponse
    {
        $volunteerApplication->update([
            'status' => 'rejected',
            'admin_notes' => $volunteerApplication->admin_notes . "\n\nRejected on " . now()->format('Y-m-d H:i:s')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application rejected successfully',
            'data' => $volunteerApplication
        ]);
    }
}
