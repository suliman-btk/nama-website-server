<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventGallery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventGallery>
 */
class EventGalleryFactory extends Factory
{
    protected $model = EventGallery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'image_path' => 'events/gallery/' . $this->faker->uuid() . '.jpg',
            'alt_text' => $this->faker->optional()->sentence(),
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }
}

