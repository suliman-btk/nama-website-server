<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Application::query();

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

        // Add base URL to resume files for all applications
        $applications->getCollection()->each(function ($application) {
            $this->addResumeUrl($application);
        });

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }

    public function show(Application $application): JsonResponse
    {
        // Add base URL to resume file
        $this->addResumeUrl($application);

        return response()->json([
            'success' => true,
            'data' => $application
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // Basic Information
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'application_type' => 'required|in:volunteer,intern',

            // Personal Information
            'nationality' => 'nullable|string|max:255',
            'date_of_birth' => 'required|date|before:today',

            // Address Information
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:255',

            // Education & Languages
            'education_level' => 'nullable|string|max:255',
            'program_major' => 'nullable|string|max:255',
            'languages' => 'nullable|string|max:1000',

            // Availability
            'available_days' => 'required|string',
            'available_times' => 'required|string',
            'interests' => 'required|string',

            // Skills & Motivation
            'skills_experience' => 'nullable|string|max:2000',
            'motivation' => 'nullable|string|max:2000',

            // Emergency Contact
            'emergency_name' => 'nullable|string|max:255',
            'emergency_relationship' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',

            // Reference
            'reference_name' => 'nullable|string|max:255',
            'reference_contact' => 'nullable|string|max:255',

            // Health & Consent
            'has_medical_condition' => 'nullable|in:true,false,1,0',
            'agrees_to_terms' => 'required|in:true,false,1,0',

            // File Upload
            'resume' => 'required|file|mimes:pdf|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['resume']);

        // Process array fields from form-data
        if (isset($data['available_days']) && is_string($data['available_days'])) {
            $data['available_days'] = json_decode($data['available_days'], true);
        }
        if (isset($data['available_times']) && is_string($data['available_times'])) {
            $data['available_times'] = json_decode($data['available_times'], true);
        }
        if (isset($data['interests']) && is_string($data['interests'])) {
            $data['interests'] = json_decode($data['interests'], true);
        }

        // Process boolean fields
        if (isset($data['has_medical_condition'])) {
            $data['has_medical_condition'] = in_array($data['has_medical_condition'], ['true', '1', 1, true]);
        }
        if (isset($data['agrees_to_terms'])) {
            $data['agrees_to_terms'] = in_array($data['agrees_to_terms'], ['true', '1', 1, true]);
        }

        // Handle resume upload to S3
        if ($request->hasFile('resume')) {
            $data['resume_path'] = $request->file('resume')->store('applications/resumes', 's3');
        }

        $application = Application::create($data);

        // Add base URL to resume file
        $this->addResumeUrl($application);

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully',
            'data' => $application
        ], 201);
    }

    public function update(Request $request, Application $application): JsonResponse
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

        $application->update($request->only(['status', 'admin_notes']));

        // Add base URL to resume file
        $this->addResumeUrl($application);

        return response()->json([
            'success' => true,
            'message' => 'Application updated successfully',
            'data' => $application
        ]);
    }

    public function destroy(Application $application): JsonResponse
    {
        // Delete resume if exists
        if ($application->resume_path) {
            Storage::disk('s3')->delete($application->resume_path);
        }

        $application->delete();

        return response()->json([
            'success' => true,
            'message' => 'Application deleted successfully'
        ]);
    }

    public function approve(Application $application): JsonResponse
    {
        $application->update([
            'status' => 'approved',
            'admin_notes' => $application->admin_notes . "\n\nApproved on " . now()->format('Y-m-d H:i:s')
        ]);

        // Add base URL to resume file
        $this->addResumeUrl($application);

        return response()->json([
            'success' => true,
            'message' => 'Application approved successfully',
            'data' => $application
        ]);
    }

    public function reject(Application $application): JsonResponse
    {
        $application->update([
            'status' => 'rejected',
            'admin_notes' => $application->admin_notes . "\n\nRejected on " . now()->format('Y-m-d H:i:s')
        ]);

        // Add base URL to resume file
        $this->addResumeUrl($application);

        return response()->json([
            'success' => true,
            'message' => 'Application rejected successfully',
            'data' => $application
        ]);
    }

    /**
     * Add base URL to resume file
     */
    private function addResumeUrl($application)
    {
        $baseUrl = rtrim(config('filesystems.disks.s3.url'), '/') . '/';

        // Add URL to resume file
        if ($application->resume_path) {
            $application->resume_url = $baseUrl . $application->resume_path;
        }
    }
}







