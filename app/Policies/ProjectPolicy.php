<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the project.
     *
     * A user can view a project if they own it, or if they are a member
     * of the team that owns it.
     */
    public function view(User $user, Project $project): bool
    {
        if ($project->user_id === $user->id) {
            return true;
        }

        if ($project->team_id) {
            $team = $project->team;

            return $team && ($team->isOwner($user) || $team->hasUser($user));
        }

        return false;
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the project.
     *
     * Only the project owner, or the team owner/admin for team projects.
     */
    public function update(User $user, Project $project): bool
    {
        if ($project->user_id === $user->id) {
            return true;
        }

        if ($project->team_id) {
            $team = $project->team;
            if (! $team) {
                return false;
            }

            return $team->isOwner($user) || $team->userRole($user) === 'admin';
        }

        return false;
    }

    /**
     * Determine whether the user can delete the project.
     *
     * Same rules as update.
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    /**
     * Determine whether the user can restore the project.
     */
    public function restore(User $user, Project $project): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the project.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        return false;
    }
}
