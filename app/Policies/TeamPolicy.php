<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeamPolicy
{
    // /**
    //  * Determine whether the user can view any models.
    //  */
    // public function viewAny(User $user): bool
    // {
    //     //
    // }

    // /**
    //  * Determine whether the user can view the model.
    //  */
    // public function view(User $user, Team $team): bool
    // {
    //     //
    // }

    // /**
    //  * Determine whether the user can create models.
    //  */
    // public function create(User $user): bool
    // {
    //     //
    // }

    // /**
    //  * Determine whether the user can update the model.
    //  */
    // public function update(User $user, Team $team): bool
    // {
    //     //
    // }

    // /**
    //  * Determine whether the user can delete the model.
    //  */
    // public function delete(User $user, Team $team): bool
    // {
    //     //
    // }

    // /**
    //  * Determine whether the user can restore the model.
    //  */
    // public function restore(User $user, Team $team): bool
    // {
    //     //
    // }

    // /**
    //  * Determine whether the user can permanently delete the model.
    //  */
    // public function forceDelete(User $user, Team $team): bool
    // {
    //     //
    // }

    /**
     * Determine if the user can add team members.
     */
    public function addTeamMember(User $user, Team $team): bool
    {
        return $team->user_id === $user->id ||
            $team->users()->where('user_id', $user->id)
                ->where('role', 'admin')
                ->exists();
    }

    /**
     * Determine if the user can remove team members.
     */
    public function removeTeamMember(User $user, Team $team): bool
    {
        return $team->user_id === $user->id ||
            $team->users()->where('user_id', $user->id)
                ->where('role', 'admin')
                ->exists();
    }
}
