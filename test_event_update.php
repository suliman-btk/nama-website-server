<?php

/**
 * Test script for Event Update API with form-data
 * Run: php test_event_update.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

echo "=== Testing Event Update with Form-Data ===\n\n";

// Get or create a test event
$event = Event::first();
if (!$event) {
    echo "Creating test event...\n";
    $event = Event::create([
        'title' => 'Test Event',
        'description' => 'Test Description',
        'short_description' => 'Test Short',
        'start_date' => now()->addDays(7),
        'end_date' => now()->addDays(8),
        'location' => 'Test Location',
        'status' => 'draft',
    ]);
    echo "Created event with ID: {$event->id}\n\n";
} else {
    echo "Using existing event with ID: {$event->id}\n";
    echo "Current title: {$event->title}\n";
    echo "Current description: {$event->description}\n\n";
}

// Create a test request with form-data
echo "Simulating PUT request with form-data...\n";

// Simulate form-data by creating a request with the data
$requestData = [
    'title' => 'Updated Title from Test',
    'description' => 'Updated Description from Test',
    'short_description' => 'Updated Short Description',
    'start_date' => '2024-12-20 10:00:00',
    'end_date' => '2024-12-21 18:00:00',
    'location' => 'Updated Location',
    'status' => 'published',
];

// Create a mock request
$request = Request::create(
    "/api/v1/admin/events/{$event->id}",
    'PUT',
    $requestData,
    [],
    [],
    [
        'CONTENT_TYPE' => 'multipart/form-data',
        'HTTP_ACCEPT' => 'application/json',
    ]
);

echo "Request data being sent:\n";
print_r($requestData);
echo "\n";

// Test what the controller would receive
echo "Testing what request methods return:\n";
echo "request->all(): ";
print_r($request->all());
echo "\n";

echo "request->request->all(): ";
print_r($request->request->all());
echo "\n";

echo "request->input(): ";
print_r($request->input());
echo "\n";

// Test the merged data approach
$allRequestData = array_merge(
    $request->all(),
    $request->request->all(),
    $request->input()
);
echo "Merged data: ";
print_r($allRequestData);
echo "\n";

// Test field extraction
$updateableFields = ['title', 'description', 'short_description', 'start_date', 'end_date', 'location', 'status', 'metadata'];
$data = [];
foreach ($updateableFields as $field) {
    if (isset($allRequestData[$field]) && $allRequestData[$field] !== null) {
        $data[$field] = $allRequestData[$field];
    }
}

echo "Extracted data for update:\n";
print_r($data);
echo "\n";

if (empty($data)) {
    echo "❌ ERROR: No data extracted! This is the problem.\n";
} else {
    echo "✅ Data extracted successfully. Would update with:\n";
    foreach ($data as $key => $value) {
        echo "  - {$key}: {$value}\n";
    }
    
    // Actually update the event
    echo "\nUpdating event...\n";
    $event->update($data);
    $event->refresh();
    
    echo "✅ Event updated!\n";
    echo "New title: {$event->title}\n";
    echo "New description: {$event->description}\n";
    echo "New status: {$event->status}\n";
}

echo "\n=== Test Complete ===\n";

