<?php

use App\Models\Event;
use App\Models\EventGallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create an admin user
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
    ]);

    // Create a Sanctum token for the admin
    $this->token = $this->admin->createToken('test-token')->plainTextToken;

    // Fake S3 storage for testing
    Storage::fake('s3');
});

test('admin can create an event', function () {
    $eventData = [
        'title' => 'Test Event',
        'description' => 'This is a test event description',
        'short_description' => 'Short description',
        'start_date' => now()->addDays(7)->toDateString(),
        'end_date' => now()->addDays(8)->toDateString(),
        'location' => 'Test Location',
        'status' => 'draft',
    ];

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson('/api/v1/admin/events', $eventData);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Event created successfully',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'title',
                'description',
                'start_date',
                'end_date',
                'location',
                'status',
            ],
        ]);

    $this->assertDatabaseHas('events', [
        'title' => 'Test Event',
        'status' => 'draft',
    ]);
});

test('admin can create an event with featured image', function () {
    $image = UploadedFile::fake()->image('event.jpg', 800, 600);

    $eventData = [
        'title' => 'Test Event with Image',
        'description' => 'This is a test event with image',
        'start_date' => now()->addDays(7)->toDateString(),
        'status' => 'published',
        'featured_image' => $image,
    ];

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson('/api/v1/admin/events', $eventData);

    $response->assertStatus(201);

    $event = Event::where('title', 'Test Event with Image')->first();
    expect($event->featured_image)->not->toBeNull();
    Storage::disk('s3')->assertExists($event->featured_image);
});

test('admin can create an event with galleries', function () {
    $image1 = UploadedFile::fake()->image('gallery1.jpg');
    $image2 = UploadedFile::fake()->image('gallery2.jpg');

    $eventData = [
        'title' => 'Test Event with Galleries',
        'description' => 'This is a test event with galleries',
        'start_date' => now()->addDays(7)->toDateString(),
        'status' => 'published',
        'galleries' => [
            [
                'image' => $image1,
                'alt_text' => 'Gallery Image 1',
            ],
            [
                'image' => $image2,
                'alt_text' => 'Gallery Image 2',
            ],
        ],
    ];

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson('/api/v1/admin/events', $eventData);

    $response->assertStatus(201);

    $event = Event::where('title', 'Test Event with Galleries')->first();
    expect($event->galleries)->toHaveCount(2);
    expect($event->galleries->first()->alt_text)->toBe('Gallery Image 1');
});

test('admin can get list of events', function () {
    Event::factory()->count(3)->create([
        'status' => 'published',
    ]);

    Event::factory()->create([
        'status' => 'draft',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson('/api/v1/admin/events');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonStructure([
            'success',
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'status',
                    ],
                ],
            ],
        ]);

    // Admin should see all events including drafts
    $responseData = $response->json('data.data');
    expect(count($responseData))->toBe(4);
});

test('admin can get a specific event', function () {
    $event = Event::factory()->create([
        'title' => 'Specific Event',
        'status' => 'draft',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->getJson("/api/v1/admin/events/{$event->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $event->id,
                'title' => 'Specific Event',
            ],
        ]);
});

test('admin can update an event', function () {
    $event = Event::factory()->create([
        'title' => 'Original Title',
        'status' => 'draft',
    ]);

    $updateData = [
        'title' => 'Updated Title',
        'description' => 'Updated description',
        'status' => 'published',
    ];

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->putJson("/api/v1/admin/events/{$event->id}", $updateData);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Event updated successfully',
        ]);

    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'title' => 'Updated Title',
        'status' => 'published',
    ]);
});

test('admin can delete an event', function () {
    $event = Event::factory()->create();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->deleteJson("/api/v1/admin/events/{$event->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Event deleted successfully',
        ]);

    $this->assertDatabaseMissing('events', [
        'id' => $event->id,
    ]);
});

test('admin can add gallery image to an event', function () {
    $event = Event::factory()->create();
    $image = UploadedFile::fake()->image('gallery.jpg');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson("/api/v1/admin/events/{$event->id}/galleries", [
        'image' => $image,
        'alt_text' => 'Gallery Image',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Gallery image added successfully',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'event_id',
                'image_path',
                'alt_text',
            ],
        ]);

    $event->refresh();
    expect($event->galleries)->toHaveCount(1);
    expect($event->galleries->first()->alt_text)->toBe('Gallery Image');
});

test('admin can remove gallery image from an event', function () {
    $event = Event::factory()->create();
    $gallery = EventGallery::factory()->create([
        'event_id' => $event->id,
        'image_path' => 'events/gallery/test.jpg',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->deleteJson("/api/v1/admin/events/{$event->id}/galleries/{$gallery->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Gallery image removed successfully',
        ]);

    $this->assertDatabaseMissing('event_galleries', [
        'id' => $gallery->id,
    ]);
});

test('admin cannot remove gallery from different event', function () {
    $event1 = Event::factory()->create();
    $event2 = Event::factory()->create();
    $gallery = EventGallery::factory()->create([
        'event_id' => $event1->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->deleteJson("/api/v1/admin/events/{$event2->id}/galleries/{$gallery->id}");

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Gallery image not found for this event',
        ]);
});

test('admin can update event status', function () {
    $event = Event::factory()->create([
        'status' => 'draft',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->patchJson("/api/v1/admin/events/{$event->id}/status", [
        'status' => 'published',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Event published successfully',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'title',
                'status',
                'previous_status',
                'updated_at',
            ],
        ]);

    $event->refresh();
    expect($event->status)->toBe('published');
});

test('admin can update event status to cancelled', function () {
    $event = Event::factory()->create([
        'status' => 'published',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->patchJson("/api/v1/admin/events/{$event->id}/status", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Event cancelled',
        ]);

    $event->refresh();
    expect($event->status)->toBe('cancelled');
});

test('admin can update event status to draft', function () {
    $event = Event::factory()->create([
        'status' => 'published',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->patchJson("/api/v1/admin/events/{$event->id}/status", [
        'status' => 'draft',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Event moved to draft',
        ]);

    $event->refresh();
    expect($event->status)->toBe('draft');
});

test('non-admin user cannot access admin routes', function () {
    $nonAdmin = User::factory()->create([
        'is_admin' => false,
    ]);

    $token = $nonAdmin->createToken('test-token')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ])->getJson('/api/v1/admin/events');

    $response->assertStatus(403);
});

test('unauthenticated user cannot access admin routes', function () {
    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->getJson('/api/v1/admin/events');

    $response->assertStatus(401);
});

test('event creation requires validation', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson('/api/v1/admin/events', []);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Validation failed',
        ])
        ->assertJsonValidationErrors(['title', 'description', 'start_date']);
});

test('event status update requires valid status', function () {
    $event = Event::factory()->create();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->patchJson("/api/v1/admin/events/{$event->id}/status", [
        'status' => 'invalid_status',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('gallery image upload requires image file', function () {
    $event = Event::factory()->create();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson("/api/v1/admin/events/{$event->id}/galleries", [
        'alt_text' => 'Test',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

test('gallery image upload validates file size', function () {
    $event = Event::factory()->create();
    // Create a fake file larger than 2MB (2048 KB)
    $largeImage = UploadedFile::fake()->image('large.jpg')->size(3000);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
    ])->postJson("/api/v1/admin/events/{$event->id}/galleries", [
        'image' => $largeImage,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

