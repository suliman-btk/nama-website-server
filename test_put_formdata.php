<?php

/**
 * Test PUT request with form-data
 * Run: php test_put_formdata.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

echo "=== Testing PUT Request with Form-Data ===\n\n";

// Get or create a test event
$event = Event::first();
if (!$event) {
    echo "Creating test event...\n";
    $event = Event::create([
        'title' => 'Original Title',
        'description' => 'Original Description',
        'short_description' => 'Original Short',
        'start_date' => now()->addDays(7),
        'end_date' => now()->addDays(8),
        'location' => 'Original Location',
        'status' => 'draft',
    ]);
    echo "Created event with ID: {$event->id}\n\n";
} else {
    echo "Using existing event with ID: {$event->id}\n";
    echo "Current title: {$event->title}\n";
    echo "Current description: {$event->description}\n\n";
}

// Get or create admin user for authentication
$admin = User::where('is_admin', true)->first();
if (!$admin) {
    echo "Creating admin user...\n";
    $admin = User::create([
        'name' => 'Test Admin',
        'email' => 'test@admin.com',
        'password' => Hash::make('password'),
        'is_admin' => true,
    ]);
    echo "Created admin user\n\n";
}

// Create a token for the admin
$token = $admin->createToken('test-token')->plainTextToken;
echo "Created auth token\n\n";

// Simulate form-data in a PUT request
echo "Simulating PUT request with form-data...\n\n";

// Create form-data array
$formData = [
    'title' => 'Updated Title from Test',
    'description' => 'Updated Description from Test',
    'short_description' => 'Updated Short Description',
    'start_date' => '2024-12-20 10:00:00',
    'end_date' => '2024-12-21 18:00:00',
    'location' => 'Updated Location',
    'status' => 'published',
];

echo "Form-data to send:\n";
foreach ($formData as $key => $value) {
    echo "  {$key}: {$value}\n";
}
echo "\n";

// Create a request that simulates form-data
// For PUT requests with form-data, we need to use _method=PUT or set method override
$request = Request::create(
    "/api/v1/admin/events/{$event->id}",
    'POST', // Use POST and override method
    array_merge($formData, ['_method' => 'PUT']),
    [],
    [],
    [
        'CONTENT_TYPE' => 'multipart/form-data',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ]
);

// Override the method to PUT
$request->setMethod('PUT');

echo "Testing what the controller receives:\n";
echo "------------------------------------\n";
echo "request->all(): ";
$all = $request->all();
print_r($all);
echo "\n";

echo "request->request->all(): ";
$requestAll = $request->request->all();
print_r($requestAll);
echo "\n";

echo "request->input(): ";
$input = $request->input();
print_r($input);
echo "\n";

// Test the merged approach
$allRequestData = array_merge(
    $request->all(),
    $request->request->all(),
    $request->input()
);
echo "Merged data: ";
print_r($allRequestData);
echo "\n";

// Test field extraction (same as in controller)
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
    echo "❌ ERROR: No data extracted from form-data!\n";
    echo "\nTrying alternative approach...\n";
    
    // Try getting each field directly
    $data2 = [];
    foreach ($updateableFields as $field) {
        $value = $request->get($field) ?? $request->input($field);
        if ($value !== null) {
            $data2[$field] = $value;
        }
    }
    
    echo "Alternative extraction:\n";
    print_r($data2);
    
    if (!empty($data2)) {
        echo "\n✅ Alternative method works! Using this data.\n";
        $data = $data2;
    }
}

if (!empty($data)) {
    echo "✅ Data extracted successfully!\n";
    echo "\nUpdating event with extracted data...\n";
    
    // Save original values
    $originalTitle = $event->title;
    $originalDescription = $event->description;
    
    // Update the event
    $event->update($data);
    $event->refresh();
    
    echo "\nUpdate Results:\n";
    echo "---------------\n";
    echo "Original title: {$originalTitle}\n";
    echo "New title: {$event->title}\n";
    echo "Original description: {$originalDescription}\n";
    echo "New description: {$event->description}\n";
    echo "New status: {$event->status}\n";
    
    if ($event->title === $formData['title']) {
        echo "\n✅ SUCCESS: Title updated correctly!\n";
    } else {
        echo "\n❌ FAILED: Title was not updated correctly.\n";
        echo "Expected: {$formData['title']}\n";
        echo "Got: {$event->title}\n";
    }
    
    if ($event->description === $formData['description']) {
        echo "✅ SUCCESS: Description updated correctly!\n";
    } else {
        echo "❌ FAILED: Description was not updated correctly.\n";
        echo "Expected: {$formData['description']}\n";
        echo "Got: {$event->description}\n";
    }
} else {
    echo "❌ CRITICAL: No data could be extracted from form-data!\n";
    echo "This means the update method will not work with form-data.\n";
}

echo "\n=== Test Complete ===\n";

