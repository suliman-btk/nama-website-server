<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_implicit_flush(true);

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "=== Testing PUT API with Form-Data ===\n";

// Get or create event
$event = Event::first();
if (!$event) {
    $event = Event::create([
        'title' => 'Test Event',
        'description' => 'Test Description',
        'short_description' => 'Test',
        'start_date' => now()->addDays(7),
        'end_date' => now()->addDays(8),
        'location' => 'Test Location',
        'status' => 'draft',
    ]);
}
echo "Event ID: {$event->id}\n";
echo "Current Title: {$event->title}\n\n";

// Get or create admin
$admin = User::where('is_admin', true)->first();
if (!$admin) {
    $admin = User::create([
        'name' => 'Test Admin',
        'email' => 'testadmin@test.com',
        'password' => Hash::make('password'),
        'is_admin' => true,
    ]);
}

$token = $admin->createToken('test')->plainTextToken;
echo "Token created\n\n";

// Test the update method directly
echo "Testing update method logic...\n";

// Simulate request data
$requestData = [
    'title' => 'NEW TITLE FROM TEST',
    'description' => 'NEW DESCRIPTION FROM TEST',
    'short_description' => 'NEW SHORT',
    'start_date' => '2024-12-25 10:00:00',
    'end_date' => '2024-12-26 18:00:00',
    'location' => 'NEW LOCATION',
    'status' => 'published',
];

// Create request object
$request = \Illuminate\Http\Request::create(
    "/api/v1/admin/events/{$event->id}",
    'PUT',
    $requestData
);

echo "Request data:\n";
print_r($request->all());
echo "\n";

// Test data extraction (same as controller)
$updateableFields = ['title', 'description', 'short_description', 'start_date', 'end_date', 'location', 'status', 'metadata'];
$allRequestData = array_merge(
    $request->all(),
    $request->request->all(),
    $request->input()
);

$data = [];
foreach ($updateableFields as $field) {
    if (isset($allRequestData[$field]) && $allRequestData[$field] !== null) {
        $data[$field] = $allRequestData[$field];
    }
}

echo "Extracted data:\n";
print_r($data);
echo "\n";

if (!empty($data)) {
    echo "✅ Data extraction works!\n";
    $event->update($data);
    $event->refresh();
    echo "Updated title: {$event->title}\n";
    echo "Updated description: {$event->description}\n";
    
    if ($event->title === 'NEW TITLE FROM TEST') {
        echo "✅ SUCCESS: Update works!\n";
    } else {
        echo "❌ FAILED: Title not updated\n";
    }
} else {
    echo "❌ FAILED: No data extracted\n";
}

echo "\nDone.\n";

