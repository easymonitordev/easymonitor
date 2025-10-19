<?php

namespace App\Livewire\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class ManageMembers extends Component
{
    use AuthorizesRequests;

    public Team $team;

    public string $email = '';

    public string $role = 'member';

    /**
     * Mount the component and authorize access
     */
    public function mount(Team $team): void
    {
        $this->authorize('view', $team);
        $this->team = $team;
    }

    /**
     * Get the validation rules
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', 'in:admin,member'],
        ];
    }

    /**
     * Add a new member to the team
     */
    public function addMember(): void
    {
        $this->authorize('addMember', $this->team);

        $validated = $this->validate();

        $user = User::where('email', $validated['email'])->first();

        if ($this->team->hasUser($user)) {
            $this->addError('email', __('This user is already a member of the team.'));

            return;
        }

        if ($this->team->isOwner($user)) {
            $this->addError('email', __('The team owner is already part of the team.'));

            return;
        }

        $this->team->users()->attach($user->id, ['role' => $validated['role']]);

        session()->flash('message', __('Member added successfully.'));

        $this->reset('email', 'role');
        $this->role = 'member';
    }

    /**
     * Remove a member from the team
     */
    public function removeMember(int $userId): void
    {
        $this->authorize('removeMember', $this->team);

        $user = User::findOrFail($userId);

        if ($this->team->isOwner($user)) {
            session()->flash('error', __('Cannot remove the team owner.'));

            return;
        }

        $this->team->users()->detach($userId);

        session()->flash('message', __('Member removed successfully.'));
    }

    /**
     * Update a member's role in the team
     */
    public function updateRole(int $userId, string $role): void
    {
        $this->authorize('addMember', $this->team);

        if (! in_array($role, ['admin', 'member'])) {
            return;
        }

        $user = User::findOrFail($userId);

        if ($this->team->isOwner($user)) {
            session()->flash('error', __('Cannot change the role of the team owner.'));

            return;
        }

        $this->team->users()->updateExistingPivot($userId, ['role' => $role]);

        session()->flash('message', __('Member role updated successfully.'));
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.teams.manage-members', [
            'members' => $this->team->users()->withPivot('role')->get(),
        ]);
    }
}
