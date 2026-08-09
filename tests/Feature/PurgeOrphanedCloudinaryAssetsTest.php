<?php

use App\Models\Media;
use App\Services\CloudinaryService;

it('reports orphaned assets in a dry run without deleting anything', function () {
    Media::factory()->create(['cloudinary_public_id' => 'jijel/destinations/kept']);

    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('listAssetIds')
            ->once()
            ->with('jijel/destinations')
            ->andReturn([
                'jijel/destinations/kept',
                'jijel/destinations/orphan-1',
                'jijel/destinations/orphan-2',
            ]);
        $mock->shouldReceive('destroy')->never();
    });

    $this->artisan('cloudinary:purge-orphans')
        ->expectsOutputToContain('Found 2 orphaned asset(s) in "jijel/destinations".')
        ->expectsOutputToContain('Dry run — nothing was deleted.')
        ->assertSuccessful();
});

it('deletes orphaned assets when run', function () {
    Media::factory()->create(['cloudinary_public_id' => 'jijel/destinations/kept']);

    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('listAssetIds')
            ->once()
            ->andReturn([
                'jijel/destinations/kept',
                'jijel/destinations/orphan-1',
            ]);
        $mock->shouldReceive('destroy')
            ->once()
            ->with('jijel/destinations/orphan-1')
            ->andReturn(true);
    });

    $this->artisan('cloudinary:purge-orphans --run')
        ->expectsOutputToContain('Deleted 1 asset(s), 0 failed.')
        ->assertSuccessful();
});

it('reports when there are no orphaned assets', function () {
    Media::factory()->create(['cloudinary_public_id' => 'jijel/destinations/kept']);

    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('listAssetIds')
            ->once()
            ->andReturn(['jijel/destinations/kept']);
    });

    $this->artisan('cloudinary:purge-orphans')
        ->expectsOutputToContain('No orphaned assets found in "jijel/destinations".')
        ->assertSuccessful();
});
