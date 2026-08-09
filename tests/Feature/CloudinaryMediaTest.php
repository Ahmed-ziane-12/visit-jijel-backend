<?php

use App\Models\Business;
use App\Models\Destination;
use App\Models\Listing;
use App\Models\Media;
use App\Models\Profile;
use App\Models\User;
use App\Services\CloudinaryService;
use Laravel\Sanctum\Sanctum;

function mediaOwner(): User
{
    return User::factory()->has(Profile::factory()->businessOwner())->create();
}

function mediaUser(): User
{
    return User::factory()->has(Profile::factory()->client())->create();
}

function mediaActingAs(User $user): User
{
    Sanctum::actingAs($user);

    return $user;
}

function mediaPayload(int $modelId, string $modelType = 'business'): array
{
    return [
        'model_type' => $modelType,
        'model_id' => $modelId,
        'collection' => 'gallery',
        'cloudinary_public_id' => "jijel/{$modelType}s/test-{$modelId}",
        'url' => 'https://res.cloudinary.com/test/image/upload/v1/test',
        'secure_url' => 'https://res.cloudinary.com/test/image/upload/v1/test',
        'format' => 'jpg',
        'resource_type' => 'image',
    ];
}

it('rejects non-whitelisted folders in the signature endpoint', function () {
    mediaActingAs(mediaOwner());

    $this->postJson('/api/v1/media/signature', ['folder' => 'jijel/malicious'])
        ->assertStatus(422);
});

it('signs and returns a transformation when one is provided', function () {
    mediaActingAs(mediaOwner());

    $this->postJson('/api/v1/media/signature', [
        'folder' => 'jijel/destinations',
        'transformation' => 'w_1600,c_limit,q_auto,f_auto',
    ])
        ->assertStatus(200)
        ->assertJsonPath('transformation', 'w_1600,c_limit,q_auto,f_auto')
        ->assertJsonStructure(['signature', 'timestamp', 'api_key', 'cloud_name']);
});

it('destroys cloudinary files when a destination is deleted', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

    $destination = Destination::factory()->create();
    Media::factory()->create([
        'model_type' => Destination::class,
        'model_id' => $destination->id,
        'cloudinary_public_id' => 'jijel/destinations/dest-1',
    ]);

    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('destroy')
            ->once()
            ->with('jijel/destinations/dest-1')
            ->andReturn(true);
    });

    $this->deleteJson("/admin/v1/destinations/{$destination->id}")
        ->assertStatus(200);

    $this->assertDatabaseMissing('destinations', ['id' => $destination->id]);
    $this->assertDatabaseMissing('media', [
        'model_type' => Destination::class,
        'model_id' => $destination->id,
    ]);
});

it('allows an owner to attach media to their own business', function () {
    $owner = mediaActingAs(mediaOwner());
    $business = Business::factory()->create(['owner_id' => $owner->id]);

    $this->postJson('/api/v1/media/store', mediaPayload($business->id))
        ->assertStatus(201)
        ->assertJson(['model_id' => $business->id]);

    $this->assertDatabaseHas('media', [
        'model_type' => Business::class,
        'model_id' => $business->id,
    ]);
});

it('denies media attachment to someone elses business', function () {
    $owner = mediaOwner();
    $business = Business::factory()->create(['owner_id' => $owner->id]);

    mediaActingAs(mediaUser());
    $this->postJson('/api/v1/media/store', mediaPayload($business->id))
        ->assertStatus(403);

    $this->assertDatabaseCount('media', 0);
});

it('denies clients from attaching media to a destination', function () {
    $destination = Destination::factory()->create();

    mediaActingAs(mediaUser());
    $this->postJson('/api/v1/media/store', mediaPayload($destination->id, 'destination'))
        ->assertStatus(403);
});

it('allows admins to attach media to a destination', function () {
    $destination = Destination::factory()->create();

    mediaActingAs(User::factory()->create(['is_admin' => true]));
    $this->postJson('/api/v1/media/store', mediaPayload($destination->id, 'destination'))
        ->assertStatus(201);
});

it('moves a newly selected cover to the front of the media list', function () {
    $owner = mediaActingAs(mediaOwner());
    $business = Business::factory()->create(['owner_id' => $owner->id]);

    Media::factory()->create([
        'model_type' => Business::class,
        'model_id' => $business->id,
        'collection' => 'gallery',
        'is_cover' => true,
        'sort_order' => 0,
    ]);
    Media::factory()->create([
        'model_type' => Business::class,
        'model_id' => $business->id,
        'collection' => 'gallery',
        'is_cover' => false,
        'sort_order' => 1,
    ]);

    $this->postJson('/api/v1/media/store', [...mediaPayload($business->id), 'is_cover' => true])
        ->assertStatus(201);

    $this->assertDatabaseHas('media', [
        'model_type' => Business::class,
        'model_id' => $business->id,
        'cloudinary_public_id' => "jijel/businesss/test-{$business->id}",
        'is_cover' => true,
        'sort_order' => 0,
    ]);
    $this->assertDatabaseHas('media', [
        'model_type' => Business::class,
        'model_id' => $business->id,
        'is_cover' => false,
        'sort_order' => 1,
    ]);
    $this->assertDatabaseHas('media', [
        'model_type' => Business::class,
        'model_id' => $business->id,
        'is_cover' => false,
        'sort_order' => 2,
    ]);
});

it('allows an owner to set one of their media as the cover', function () {
    $owner = mediaActingAs(mediaOwner());
    $business = Business::factory()->create(['owner_id' => $owner->id]);

    $first = Media::factory()->create([
        'model_type' => Business::class,
        'model_id' => $business->id,
        'collection' => 'gallery',
        'is_cover' => true,
        'sort_order' => 0,
    ]);
    $second = Media::factory()->create([
        'model_type' => Business::class,
        'model_id' => $business->id,
        'collection' => 'gallery',
        'is_cover' => false,
        'sort_order' => 1,
    ]);

    $this->postJson('/api/v1/media/cover', ['media_id' => $second->id])
        ->assertStatus(200)
        ->assertJsonPath('is_cover', true)
        ->assertJsonPath('sort_order', 0);

    $this->assertDatabaseHas('media', [
        'id' => $first->id,
        'is_cover' => false,
        'sort_order' => 1,
    ]);
    $this->assertDatabaseHas('media', [
        'id' => $second->id,
        'is_cover' => true,
        'sort_order' => 0,
    ]);
});

it('denies setting a cover on someone elses media', function () {
    $owner = mediaOwner();
    $business = Business::factory()->create(['owner_id' => $owner->id]);
    $media = Media::factory()->create([
        'model_type' => Business::class,
        'model_id' => $business->id,
        'collection' => 'gallery',
        'is_cover' => true,
        'sort_order' => 0,
    ]);

    mediaActingAs(mediaUser());
    $this->postJson('/api/v1/media/cover', ['media_id' => $media->id])
        ->assertStatus(403);
});

it('allows an owner to delete media from their own business', function () {
    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('destroy')
            ->once()
            ->with('jijel/businesses/test-1')
            ->andReturn(true);
    });

    $owner = mediaActingAs(mediaOwner());
    $business = Business::factory()->create(['owner_id' => $owner->id]);
    $media = Media::factory()->create([
        'model_type' => Business::class,
        'model_id' => $business->id,
        'cloudinary_public_id' => 'jijel/businesses/test-1',
    ]);

    $this->deleteJson('/api/v1/media/delete', ['media_id' => $media->id])
        ->assertStatus(200);

    $this->assertDatabaseMissing('media', ['id' => $media->id]);
});

it('denies media deletion by a non-owner', function () {
    $owner = mediaOwner();
    $business = Business::factory()->create(['owner_id' => $owner->id]);
    $media = Media::factory()->create([
        'model_type' => Business::class,
        'model_id' => $business->id,
        'cloudinary_public_id' => 'jijel/businesses/test-1',
    ]);

    mediaActingAs(mediaUser());
    $this->deleteJson('/api/v1/media/delete', ['media_id' => $media->id])
        ->assertStatus(403);

    $this->assertDatabaseHas('media', ['id' => $media->id]);
});

it('allows an owner to delete media from their own listing', function () {
    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('destroy')
            ->once()
            ->with('jijel/listings/test-1')
            ->andReturn(true);
    });

    $owner = mediaActingAs(mediaOwner());
    $business = Business::factory()->create(['owner_id' => $owner->id]);
    $listing = Listing::factory()->create(['business_id' => $business->id]);
    $media = Media::factory()->create([
        'model_type' => Listing::class,
        'model_id' => $listing->id,
        'cloudinary_public_id' => 'jijel/listings/test-1',
    ]);

    $this->deleteJson('/api/v1/media/delete', ['media_id' => $media->id])
        ->assertStatus(200);

    $this->assertDatabaseMissing('media', ['id' => $media->id]);
});

it('promotes the first remaining media to cover when the cover is deleted', function () {
    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('destroy')
            ->once()
            ->with('jijel/businesses/cover-1')
            ->andReturn(true);
    });

    $owner = mediaActingAs(mediaOwner());
    $business = Business::factory()->create(['owner_id' => $owner->id]);

    $cover = Media::factory()->create([
        'model_type' => Business::class,
        'model_id' => $business->id,
        'cloudinary_public_id' => 'jijel/businesses/cover-1',
        'collection' => 'gallery',
        'is_cover' => true,
        'sort_order' => 0,
    ]);
    $first = Media::factory()->create([
        'model_type' => Business::class,
        'model_id' => $business->id,
        'cloudinary_public_id' => 'jijel/businesses/first-1',
        'collection' => 'gallery',
        'is_cover' => false,
        'sort_order' => 1,
    ]);
    Media::factory()->create([
        'model_type' => Business::class,
        'model_id' => $business->id,
        'cloudinary_public_id' => 'jijel/businesses/second-1',
        'collection' => 'gallery',
        'is_cover' => false,
        'sort_order' => 2,
    ]);

    $this->deleteJson('/api/v1/media/delete', ['media_id' => $cover->id])
        ->assertStatus(200);

    $this->assertDatabaseMissing('media', ['id' => $cover->id]);
    $this->assertDatabaseHas('media', [
        'id' => $first->id,
        'is_cover' => true,
    ]);
});

it('does not promote media when deleting a non-cover item', function () {
    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('destroy')
            ->once()
            ->with('jijel/businesses/other-1')
            ->andReturn(true);
    });

    $owner = mediaActingAs(mediaOwner());
    $business = Business::factory()->create(['owner_id' => $owner->id]);

    $cover = Media::factory()->create([
        'model_type' => Business::class,
        'model_id' => $business->id,
        'cloudinary_public_id' => 'jijel/businesses/cover-1',
        'collection' => 'gallery',
        'is_cover' => true,
        'sort_order' => 0,
    ]);
    $other = Media::factory()->create([
        'model_type' => Business::class,
        'model_id' => $business->id,
        'cloudinary_public_id' => 'jijel/businesses/other-1',
        'collection' => 'gallery',
        'is_cover' => false,
        'sort_order' => 1,
    ]);

    $this->deleteJson('/api/v1/media/delete', ['media_id' => $other->id])
        ->assertStatus(200);

    $this->assertDatabaseHas('media', [
        'id' => $cover->id,
        'is_cover' => true,
    ]);
});
