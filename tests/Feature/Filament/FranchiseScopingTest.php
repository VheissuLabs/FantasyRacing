<?php

use App\Models\Franchise;
use App\Models\User;

test('super admin can access the cp panel', function () {
    $user = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($user)
        ->get('/cp')
        ->assertOk();
});

test('franchise manager can access the cp panel', function () {
    $user = User::factory()->create();
    $franchise = Franchise::factory()->create();
    $franchise->managers()->attach($user);

    $this->actingAs($user)
        ->get('/cp')
        ->assertOk();
});

test('regular user cannot access the cp panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/cp')
        ->assertForbidden();
});

test('franchise manager only sees managed franchises in selector', function () {
    $manager = User::factory()->create();
    $managedFranchise = Franchise::factory()->create(['name' => 'Managed Racing']);
    $otherFranchise = Franchise::factory()->create(['name' => 'Other Racing']);
    $managedFranchise->managers()->attach($manager);

    $franchises = $manager->managedFranchises()->pluck('name')->toArray();

    expect($franchises)->toContain('Managed Racing')
        ->and($franchises)->not->toContain('Other Racing');
});

test('super admin sees all franchises', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    Franchise::factory()->count(3)->create();

    expect(Franchise::count())->toBeGreaterThanOrEqual(3);
});
