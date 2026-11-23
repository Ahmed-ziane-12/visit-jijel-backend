<?php

use App\Enums\BusinessType;
use App\Enums\CalendarEventSource;
use App\Enums\DestinationCategory;
use App\Enums\DestinationState;
use App\Enums\EventStatus;
use App\Enums\ItineraryItemType;
use App\Enums\ItineraryStatus;
use App\Enums\ItineraryVisibility;
use App\Enums\ListingStatus;
use App\Enums\MediaCollection;
use App\Enums\MediaResourceType;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\CalendarEvent;
use App\Models\Destination;
use App\Models\Event;
use App\Models\Itinerary;
use App\Models\Listing;
use App\Models\Media;
use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function enumTestOwner(): User
{
    return User::factory()->has(Profile::factory()->businessOwner())->create();
}

function enumTestClient(): User
{
    return User::factory()->has(Profile::factory()->client())->create();
}

it('casts domain columns to backed enums', function () {
    $owner = enumTestOwner();

    $business = Business::factory()->create([
        'owner_id' => $owner->id,
        'type' => 'restaurant',
    ]);
    $destination = Destination::factory()->create([
        'category' => 'beach',
        'state' => 'active',
    ]);
    $listing = Listing::factory()->create([
        'business_id' => $business->id,
        'status' => 'published',
    ]);
    $event = Event::factory()->create([
        'created_by' => $owner->id,
        'status' => 'cancelled',
    ]);
    $itinerary = Itinerary::factory()->create([
        'user_id' => $owner->id,
        'visibility' => 'shared',
        'status' => 'published',
    ]);
    $calendarEvent = CalendarEvent::factory()->create([
        'user_id' => $owner->id,
        'source' => 'event',
    ]);
    $media = Media::factory()->create([
        'model_type' => Business::class,
        'model_id' => $business->id,
        'collection' => 'gallery',
        'resource_type' => 'video',
    ]);

    $day = $itinerary->days()->create(['day_date' => '2026-11-01', 'day_number' => 1]);
    $item = $day->items()->create([
        'title' => 'Custom stop',
        'item_type' => 'custom',
        'sort_order' => 0,
    ]);

    expect($owner->profile->role)->toBe(UserRole::BusinessOwner)
        ->and($business->type)->toBe(BusinessType::Restaurant)
        ->and($destination->category)->toBe(DestinationCategory::Beach)
        ->and($destination->state)->toBe(DestinationState::Active)
        ->and($listing->status)->toBe(ListingStatus::Published)
        ->and($event->status)->toBe(EventStatus::Cancelled)
        ->and($itinerary->visibility)->toBe(ItineraryVisibility::Shared)
        ->and($itinerary->status)->toBe(ItineraryStatus::Published)
        ->and($calendarEvent->source)->toBe(CalendarEventSource::Event)
        ->and($media->collection)->toBe(MediaCollection::Gallery)
        ->and($media->resource_type)->toBe(MediaResourceType::Video)
        ->and($item->item_type)->toBe(ItineraryItemType::Custom);
});

it('serializes enum-backed attributes with their original string values', function () {
    $owner = enumTestOwner();

    $business = Business::factory()->create(['owner_id' => $owner->id, 'type' => 'hotel']);
    $destination = Destination::factory()->create(['category' => 'beach', 'state' => 'inactive']);
    $listing = Listing::factory()->create([
        'business_id' => $business->id,
        'status' => 'archived',
    ]);
    $itinerary = Itinerary::factory()->create([
        'user_id' => $owner->id,
        'visibility' => 'public',
    ]);
    $calendarEvent = CalendarEvent::factory()->create([
        'user_id' => $owner->id,
        'source' => 'itinerary',
    ]);
    $media = Media::factory()->create([
        'model_type' => Business::class,
        'model_id' => $business->id,
        'collection' => 'cover',
        'resource_type' => 'image',
    ]);

    $day = $itinerary->days()->create(['day_date' => '2026-11-01', 'day_number' => 1]);
    $item = $day->items()->create([
        'title' => 'Viewpoint',
        'item_type' => 'destination',
        'sort_order' => 0,
    ]);

    expect($business->fresh()->toArray()['type'])->toBe('hotel')
        ->and($destination->fresh()->toArray()['category'])->toBe('beach')
        ->and($destination->fresh()->toArray()['state'])->toBe('inactive')
        ->and($listing->fresh()->toArray()['status'])->toBe('archived')
        ->and($itinerary->fresh()->toArray()['visibility'])->toBe('public')
        ->and($calendarEvent->fresh()->toArray()['source'])->toBe('itinerary')
        ->and($media->fresh()->toArray()['collection'])->toBe('cover')
        ->and($media->fresh()->toArray()['resource_type'])->toBe('image')
        ->and($item->fresh()->toArray()['item_type'])->toBe('destination');
});

it('rejects an invalid registration role', function () {
    $this->postJson('/api/v1/register', [
        'name' => 'Bad Role',
        'email' => 'bad-role@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'admin',
    ])->assertStatus(422)->assertJsonValidationErrors('role');
});

it('rejects an invalid business type', function () {
    Sanctum::actingAs(enumTestOwner());

    $this->postJson('/api/v1/businesses', [
        'type' => 'bakery',
        'name' => 'Not a supported type',
    ])->assertStatus(422)->assertJsonValidationErrors('type');
});

it('rejects an invalid listing status', function () {
    $owner = enumTestOwner();
    $business = Business::factory()->create(['owner_id' => $owner->id]);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/businesses/{$business->id}/listings", [
        'title' => 'Sea view room',
        'status' => 'deleted',
    ])->assertStatus(422)->assertJsonValidationErrors('status');
});

it('rejects an invalid event status', function () {
    Sanctum::actingAs(enumTestOwner());

    $this->postJson('/api/v1/events', [
        'title' => 'Beach concert',
        'starts_at' => now()->addDay()->toDateTimeString(),
        'ends_at' => now()->addDays(2)->toDateTimeString(),
        'status' => 'deleted',
    ])->assertStatus(422)->assertJsonValidationErrors('status');
});

it('rejects an invalid itinerary visibility', function () {
    Sanctum::actingAs(enumTestClient());

    $this->postJson('/api/v1/itineraries', [
        'title' => 'Weekend trip',
        'visibility' => 'secret',
    ])->assertStatus(422)->assertJsonValidationErrors('visibility');
});

it('rejects an invalid calendar event source', function () {
    Sanctum::actingAs(enumTestClient());

    $this->postJson('/api/v1/calendar-events', [
        'title' => 'Lunch',
        'starts_at' => now()->addDay()->toDateTimeString(),
        'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
        'source' => 'google',
    ])->assertStatus(422)->assertJsonValidationErrors('source');
});

it('rejects an invalid itinerary item type', function () {
    $client = enumTestClient();
    $itinerary = Itinerary::factory()->create(['user_id' => $client->id]);
    $day = $itinerary->days()->create(['day_date' => '2026-11-01', 'day_number' => 1]);

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/itineraries/{$itinerary->id}/days/{$day->id}/items", [
        'title' => 'Unsupported stop',
        'item_type' => 'hotel',
    ])->assertStatus(422)->assertJsonValidationErrors('item_type');
});

it('rejects an invalid destination category', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

    $this->postJson('/admin/v1/destinations', [
        'name' => 'Unknown place',
        'category' => 'desert',
    ])->assertStatus(422)->assertJsonValidationErrors('category');
});
