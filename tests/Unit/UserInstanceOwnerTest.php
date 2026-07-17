<?php

declare(strict_types=1);

use App\Models\User;

it('treats the first registered user as the instance owner', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    expect($first->isInstanceOwner())->toBeTrue()
        ->and($second->isInstanceOwner())->toBeFalse();
});
