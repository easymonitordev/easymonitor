<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StatusPage;
use App\Models\User;

class StatusPagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Admin view (editing context). Public rendering uses its own visibility gate.
     */
    public function view(User $user, StatusPage $statusPage): bool
    {
        if ($statusPage->user_id === $user->id) {
            return true;
        }

        if ($statusPage->team_id) {
            $team = $statusPage->team;

            return $team && ($team->isOwner($user) || $team->hasUser($user));
        }

        return false;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, StatusPage $statusPage): bool
    {
        if ($statusPage->user_id === $user->id) {
            return true;
        }

        if ($statusPage->team_id) {
            $team = $statusPage->team;
            if (! $team) {
                return false;
            }

            return $team->isOwner($user) || $team->userRole($user) === 'admin';
        }

        return false;
    }

    public function delete(User $user, StatusPage $statusPage): bool
    {
        return $this->update($user, $statusPage);
    }
}
