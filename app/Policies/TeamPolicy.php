<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::CreateUsers->value);
    }

    public function view(User $user, Team $team): bool
    {
        return $this->manages($user, $team);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::CreateUsers->value);
    }

    public function update(User $user, Team $team): bool
    {
        return $this->manages($user, $team);
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->manages($user, $team);
    }

    public function viewSeasonStatistics(User $user, Team $team): bool
    {
        if ($user->hasRole(RoleName::Administrator)) {
            return true;
        }

        return $user->hasRole(RoleName::Club) && $user->club_id === $team->club_id;
    }

    private function manages(User $actor, Team $team): bool
    {
        if (! $actor->can(PermissionName::CreateUsers->value)) {
            return false;
        }

        if ($actor->can(PermissionName::CreateClubs->value)) {
            return true;
        }

        return $actor->hasRole(RoleName::Club) && $actor->club_id === $team->club_id;
    }
}
