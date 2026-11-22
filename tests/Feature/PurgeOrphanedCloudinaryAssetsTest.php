<?php

use App\Models\Media;
use App\Services\CloudinaryService;

function oldAsset(string $publicId): array
{
    return [
        'public_id' => $publicId,
        'created_at' => now()->subDay()->toIso8601String(),
    ];
}

it('reports orphaned assets in a dry run without deleting anything', function () {
    Media::factory()->create(['cloudinary_public_id' => 'jijel/destinations/kept']);

    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('listAssets')
            ->once()
            ->with('jijel/destinations')
            ->andReturn([
                oldAsset('jijel/destinations/kept'),
                oldAsset('jijel/destinations/orphan-1'),
                oldAsset('jijel/destinations/orphan-2'),
            ]);
        $mock->shouldReceive('destroy')->never();
    });

    $this->artisan('cloudinary:purge-orphans --folder=jijel/destinations')
        ->expectsOutputToContain('Found 2 orphaned asset(s) in "jijel/destinations".')
        ->expectsOutputToContain('Dry run — nothing was deleted.')
        ->assertSuccessful();
});

it('deletes orphaned assets when run', function () {
    Media::factory()->create(['cloudinary_public_id' => 'jijel/destinations/kept']);

    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('listAssets')
            ->once()
            ->andReturn([
                oldAsset('jijel/destinations/kept'),
                oldAsset('jijel/destinations/orphan-1'),
            ]);
        $mock->shouldReceive('destroy')
            ->once()
            ->with('jijel/destinations/orphan-1')
            ->andReturn(true);
    });

    $this->artisan('cloudinary:purge-orphans --folder=jijel/destinations --run')
        ->expectsOutputToContain('Deleted 1 asset(s), 0 failed.')
        ->assertSuccessful();
});

it('reports when there are no orphaned assets', function () {
    Media::factory()->create(['cloudinary_public_id' => 'jijel/destinations/kept']);

    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('listAssets')
            ->once()
            ->andReturn([oldAsset('jijel/destinations/kept')]);
    });

    $this->artisan('cloudinary:purge-orphans --folder=jijel/destinations')
        ->expectsOutputToContain('No orphaned assets found in "jijel/destinations".')
        ->assertSuccessful();
});

it('never deletes assets that are newer than the minimum age', function () {
    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('listAssets')
            ->once()
            ->andReturn([[
                'public_id' => 'jijel/destinations/just-uploaded',
                'created_at' => now()->subMinute()->toIso8601String(),
            ]]);
        $mock->shouldReceive('destroy')->never();
    });

    $this->artisan('cloudinary:purge-orphans --folder=jijel/destinations --run')
        ->expectsOutputToContain('Skipped 1 recent asset(s).')
        ->assertSuccessful();
});

it('rejects folders outside the application media folders', function () {
    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('listAssets')->never();
    });

    $this->artisan('cloudinary:purge-orphans --folder=jijel/private')
        ->expectsOutputToContain('is not an application media folder.')
        ->assertFailed();
});

it('fails clearly when cloudinary is not configured', function () {
    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturnFalse();
    });

    $this->artisan('cloudinary:purge-orphans')
        ->expectsOutputToContain('Cloudinary is not configured.')
        ->assertFailed();
});
