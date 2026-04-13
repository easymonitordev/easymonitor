<?php

namespace Database\Factories;

use App\Models\StatusPage;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StatusPage>
 */
class StatusPageFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'user_id' => User::factory(),
            'team_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'description' => fake()->optional()->sentence(),
            'visibility' => 'public',
            'access_key' => null,
            'theme' => 'business',
        ];
    }

    public function unlisted(): static
    {
        return $this->state(fn () => [
            'visibility' => 'unlisted',
            'access_key' => StatusPage::generateAccessKey(),
        ]);
    }

    public function private(): static
    {
        return $this->state(fn () => [
            'visibility' => 'private',
        ]);
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn () => [
            'team_id' => $team->id,
            'user_id' => $team->owner_id,
        ]);
    }
}
