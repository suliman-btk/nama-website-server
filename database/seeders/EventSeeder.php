<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = [
            // Published Events - Upcoming
            [
                'title' => 'Community Health Fair 2024',
                'description' => 'Join us for our annual Community Health Fair featuring free health screenings, wellness workshops, and health education sessions. This event brings together healthcare professionals, community organizations, and residents to promote health and wellness in our community.',
                'short_description' => 'Annual health fair with free screenings and wellness workshops.',
                'start_date' => Carbon::now()->addDays(30)->setTime(9, 0),
                'end_date' => Carbon::now()->addDays(30)->setTime(17, 0),
                'location' => 'Community Center, 123 Main Street',
                'status' => 'published',
                'featured_image' => 'events/featured/community-health-fair-2024.jpg',
                'metadata' => [
                    'registration_required' => true,
                    'max_attendees' => 500,
                    'contact_email' => 'events@foundation.com',
                ],
            ],
            [
                'title' => 'Youth Leadership Workshop',
                'description' => 'A comprehensive workshop designed to empower young leaders in our community. Participants will learn essential leadership skills, team building, and community engagement strategies. Open to ages 16-25.',
                'short_description' => 'Empowering workshop for young community leaders aged 16-25.',
                'start_date' => Carbon::now()->addDays(45)->setTime(10, 0),
                'end_date' => Carbon::now()->addDays(45)->setTime(16, 0),
                'location' => 'Youth Center, 456 Oak Avenue',
                'status' => 'published',
                'featured_image' => 'events/featured/youth-leadership-workshop.jpg',
                'metadata' => [
                    'registration_required' => true,
                    'max_attendees' => 50,
                    'age_range' => '16-25',
                ],
            ],
            [
                'title' => 'Annual Fundraising Gala',
                'description' => 'Our prestigious annual fundraising gala featuring dinner, entertainment, and silent auction. All proceeds support our community programs and initiatives. Black tie optional.',
                'short_description' => 'Elegant fundraising event with dinner and entertainment.',
                'start_date' => Carbon::now()->addDays(60)->setTime(18, 30),
                'end_date' => Carbon::now()->addDays(60)->setTime(23, 0),
                'location' => 'Grand Ballroom, Downtown Hotel',
                'status' => 'published',
                'featured_image' => 'events/featured/annual-fundraising-gala.jpg',
                'metadata' => [
                    'registration_required' => true,
                    'ticket_price' => 150,
                    'dress_code' => 'Black tie optional',
                ],
            ],
            // Published Events - Past
            [
                'title' => 'Summer Community Picnic',
                'description' => 'A fun-filled family event with games, food, music, and activities for all ages. Join us for a day of community bonding and celebration.',
                'short_description' => 'Family-friendly picnic with games, food, and music.',
                'start_date' => Carbon::now()->subDays(30)->setTime(12, 0),
                'end_date' => Carbon::now()->subDays(30)->setTime(18, 0),
                'location' => 'Central Park, Main Pavilion',
                'status' => 'published',
                'featured_image' => 'events/featured/summer-community-picnic.jpg',
                'metadata' => [
                    'registration_required' => false,
                    'attendance' => 350,
                ],
            ],
            [
                'title' => 'Educational Seminar: Financial Literacy',
                'description' => 'Learn essential financial management skills including budgeting, saving, and investment basics. Expert speakers will provide practical advice for personal financial planning.',
                'short_description' => 'Learn essential financial management and planning skills.',
                'start_date' => Carbon::now()->subDays(15)->setTime(14, 0),
                'end_date' => Carbon::now()->subDays(15)->setTime(17, 0),
                'location' => 'Community Library, Conference Room A',
                'status' => 'published',
                'featured_image' => 'events/featured/financial-literacy-seminar.jpg',
                'metadata' => [
                    'registration_required' => true,
                    'attendance' => 75,
                ],
            ],
            // Draft Events
            [
                'title' => 'Winter Holiday Celebration',
                'description' => 'Join us for our annual winter holiday celebration featuring festive activities, music, and community gathering. More details to be announced.',
                'short_description' => 'Annual winter holiday community celebration.',
                'start_date' => Carbon::now()->addDays(90)->setTime(16, 0),
                'end_date' => Carbon::now()->addDays(90)->setTime(20, 0),
                'location' => 'TBD',
                'status' => 'draft',
                'featured_image' => 'events/featured/winter-holiday-celebration.jpg',
                'metadata' => [
                    'registration_required' => false,
                    'notes' => 'Event details still being finalized',
                ],
            ],
            [
                'title' => 'Volunteer Appreciation Day',
                'description' => 'A special event to recognize and thank all our dedicated volunteers for their invaluable contributions throughout the year.',
                'short_description' => 'Celebrating and thanking our dedicated volunteers.',
                'start_date' => Carbon::now()->addDays(120)->setTime(11, 0),
                'end_date' => Carbon::now()->addDays(120)->setTime(15, 0),
                'location' => 'Community Center, Main Hall',
                'status' => 'draft',
                'featured_image' => 'events/featured/volunteer-appreciation-day.jpg',
                'metadata' => [
                    'registration_required' => true,
                    'invite_only' => true,
                ],
            ],
            // Cancelled Event
            [
                'title' => 'Spring Festival 2024',
                'description' => 'Cancelled due to unforeseen circumstances. We apologize for any inconvenience.',
                'short_description' => 'Cancelled event.',
                'start_date' => Carbon::now()->subDays(10)->setTime(10, 0),
                'end_date' => Carbon::now()->subDays(10)->setTime(18, 0),
                'location' => 'City Park',
                'status' => 'cancelled',
                'featured_image' => 'events/featured/spring-festival-2024.jpg',
                'metadata' => [
                    'cancellation_reason' => 'Unforeseen circumstances',
                ],
            ],
            // More Published Events
            [
                'title' => 'Environmental Awareness Day',
                'description' => 'Learn about environmental conservation, participate in tree planting activities, and discover ways to reduce your carbon footprint. Activities for all ages.',
                'short_description' => 'Environmental conservation activities and education.',
                'start_date' => Carbon::now()->addDays(75)->setTime(9, 0),
                'end_date' => Carbon::now()->addDays(75)->setTime(15, 0),
                'location' => 'Riverside Park, Environmental Center',
                'status' => 'published',
                'featured_image' => 'events/featured/environmental-awareness-day.jpg',
                'metadata' => [
                    'registration_required' => false,
                    'outdoor_event' => true,
                ],
            ],
            [
                'title' => 'Career Development Workshop',
                'description' => 'Professional development workshop covering resume writing, interview skills, networking strategies, and career planning. Open to job seekers and career changers.',
                'short_description' => 'Professional development for job seekers and career changers.',
                'start_date' => Carbon::now()->addDays(20)->setTime(13, 0),
                'end_date' => Carbon::now()->addDays(20)->setTime(17, 0),
                'location' => 'Career Center, 789 Business District',
                'status' => 'published',
                'featured_image' => 'events/featured/career-development-workshop.jpg',
                'metadata' => [
                    'registration_required' => true,
                    'max_attendees' => 40,
                ],
            ],
        ];

        foreach ($events as $event) {
            Event::firstOrCreate(
                [
                    'title' => $event['title'],
                    'start_date' => $event['start_date'],
                ],
                $event
            );
        }
    }
}
