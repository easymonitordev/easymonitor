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
     *
     * Access follows the effective team: if monitor is inside a project,
     * the project's team governs; otherwise the monitor's team_id.
     */
    public function view(User $user, Monitor $monitor): bool
    {
        if ($monitor->effectiveUserId() === $user->id) {
            return true;
        }

        $team = $monitor->effectiveTeam();

        if ($team) {
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
     *
     * Only the effective owner, or team owner/admin on the effective team.
     */
    public function update(User $user, Monitor $monitor): bool
    {
        if ($monitor->effectiveUserId() === $user->id) {
            return true;
        }

        $team = $monitor->effectiveTeam();

        if ($team) {
            return $team->isOwner($user) || $team->userRole($user) === 'admin';
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Monitor $monitor): bool
    {
        return $this->update($user, $monitor);
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
