<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;

class ContactRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContactRequest::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $requests = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    public function show(ContactRequest $contactRequest): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $contactRequest
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $contactRequest = ContactRequest::create($request->all());

        // Send notification email to admin (optional)
        // Mail::to(config('mail.admin_email'))->send(new ContactRequestNotification($contactRequest));

        return response()->json([
            'success' => true,
            'message' => 'Contact request submitted successfully',
            'data' => $contactRequest
        ], 201);
    }

    public function update(Request $request, ContactRequest $contactRequest): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:new,replied,closed',
            'admin_reply' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['status', 'admin_reply']);

        // Set replied_at if status is replied
        if ($data['status'] === 'replied' && !$contactRequest->replied_at) {
            $data['replied_at'] = now();
        }

        $contactRequest->update($data);

        // Send reply email to user (optional)
        if ($data['status'] === 'replied' && $data['admin_reply']) {
            // Mail::to($contactRequest->email)->send(new ContactRequestReply($contactRequest));
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact request updated successfully',
            'data' => $contactRequest
        ]);
    }

    public function destroy(ContactRequest $contactRequest): JsonResponse
    {
        $contactRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact request deleted successfully'
        ]);
    }

    public function reply(Request $request, ContactRequest $contactRequest): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'admin_reply' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $contactRequest->update([
            'status' => 'replied',
            'admin_reply' => $request->admin_reply,
            'replied_at' => now(),
        ]);

        // Send reply email to user (optional)
        // Mail::to($contactRequest->email)->send(new ContactRequestReply($contactRequest));

        return response()->json([
            'success' => true,
            'message' => 'Reply sent successfully',
            'data' => $contactRequest
        ]);
    }
}
