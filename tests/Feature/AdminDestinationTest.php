<?php

use App\Models\Destination;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('allows an admin to create a destination with arabic fields', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

    $this->postJson('/admin/v1/destinations', [
        'name' => 'Corniche',
        'arabic_name' => 'الكورنيش',
        'description' => 'A seaside promenade.',
        'arabic_description' => 'ممشى بحري.',
        'address' => 'Jijel',
        'arabic_address' => 'جيجل',
        'latitude' => 36.8,
        'longitude' => 5.76,
        'category' => 'beach',
        'arabic_category' => 'شاطئ',
    ])->assertStatus(201)
        ->assertJsonPath('arabic_name', 'الكورنيش')
        ->assertJsonPath('arabic_description', 'ممشى بحري.')
        ->assertJsonPath('arabic_address', 'جيجل')
        ->assertJsonPath('arabic_category', 'شاطئ');

    $this->assertDatabaseHas('destinations', [
        'name' => 'Corniche',
        'arabic_name' => 'الكورنيش',
        'arabic_category' => 'شاطئ',
    ]);
});

it('allows an admin to update arabic fields on a destination', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $destination = Destination::factory()->create();

    $this->putJson("/admin/v1/destinations/{$destination->id}", [
        'arabic_name' => 'شاطئ تامونت',
        'arabic_description' => 'شاطئ رملي.',
        'arabic_address' => 'تامونت',
        'arabic_category' => 'شاطئ',
    ])->assertStatus(200)
        ->assertJsonPath('arabic_name', 'شاطئ تامونت')
        ->assertJsonPath('arabic_description', 'شاطئ رملي.')
        ->assertJsonPath('arabic_address', 'تامونت')
        ->assertJsonPath('arabic_category', 'شاطئ');

    $this->assertDatabaseHas('destinations', [
        'id' => $destination->id,
        'arabic_name' => 'شاطئ تامونت',
    ]);
});
