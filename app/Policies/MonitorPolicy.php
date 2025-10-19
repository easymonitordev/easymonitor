<?php

namespace App\Policies;

use App\Models\Monitor;
use App\Models\User;

class MonitorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Monitor $monitor): bool
    {
        // User can view their own monitors
        if ($monitor->user_id === $user->id) {
            return true;
        }

        // User can view team monitors if they're part of the team
        if ($monitor->team_id) {
            $team = $monitor->team;

            return $team->isOwner($user) || $team->hasUser($user);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Monitor $monitor): bool
    {
        // User can update their own monitors
        if ($monitor->user_id === $user->id) {
            return true;
        }

        // For team monitors, only owner and admins can update
        if ($monitor->team_id) {
            $team = $monitor->team;
            $role = $team->userRole($user);

            return $team->isOwner($user) || $role === 'admin';
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Monitor $monitor): bool
    {
        // User can delete their own monitors
        if ($monitor->user_id === $user->id) {
            return true;
        }

        // For team monitors, only owner and admins can delete
        if ($monitor->team_id) {
            $team = $monitor->team;
            $role = $team->userRole($user);

            return $team->isOwner($user) || $role === 'admin';
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Monitor $monitor): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Monitor $monitor): bool
    {
        return false;
    }
}
