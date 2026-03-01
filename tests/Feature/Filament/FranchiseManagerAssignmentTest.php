<?php

use App\Models\Franchise;
use App\Models\User;

test('franchise has managers relationship', function () {
    $franchise = Franchise::factory()->create();
    $user = User::factory()->create();

    $franchise->managers()->attach($user);

    expect($franchise->managers)->toHaveCount(1)
        ->and($franchise->managers->first()->id)->toBe($user->id);
});

test('user has managed franchises relationship', function () {
    $user = User::factory()->create();
    $franchise = Franchise::factory()->create();

    $franchise->managers()->attach($user);

    expect($user->managedFranchises)->toHaveCount(1)
        ->and($user->managedFranchises->first()->id)->toBe($franchise->id);
});

test('attaching user as manager grants panel access', function () {
    $user = User::factory()->create();
    $franchise = Franchise::factory()->create();

    expect($user->canAccessPanel(app(\Filament\Panel::class)))->toBeFalse();

    $franchise->managers()->attach($user);
    $user->unsetRelation('managedFranchises');

    expect($user->canAccessPanel(app(\Filament\Panel::class)))->toBeTrue();
});

test('detaching manager revokes panel access if no other franchises', function () {
    $user = User::factory()->create();
    $franchise = Franchise::factory()->create();

    $franchise->managers()->attach($user);
    $franchise->managers()->detach($user);
    $user->unsetRelation('managedFranchises');

    expect($user->canAccessPanel(app(\Filament\Panel::class)))->toBeFalse();
});
