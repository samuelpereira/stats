<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Game;
use App\Models\User;

class GamePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::CreateUsers->value);
    }

    public function view(User $user, Game $game): bool
    {
        return $this->manages($user, $game);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::CreateUsers->value);
    }

    public function update(User $user, Game $game): bool
    {
        return $this->manages($user, $game);
    }

    public function delete(User $user, Game $game): bool
    {
        return $this->manages($user, $game);
    }

    public function collectAny(User $user): bool
    {
        if ($user->hasRole(RoleName::Administrator)) {
            return true;
        }

        return ($user->hasRole(RoleName::Club) || $user->hasRole(RoleName::User))
            && $user->club_id !== null;
    }

    public function importLog(User $user, Game $game): bool
    {
        return $user->hasRole(RoleName::Administrator);
    }

    public function collect(User $user, Game $game): bool
    {
        if (! $this->collectAny($user)) {
            return false;
        }

        if ($user->hasRole(RoleName::Administrator)) {
            return true;
        }

        $game->loadMissing('team');

        return $user->canCollectStatisticsForTeam($game->team);
    }

    /**
     * Administrators may add actions to any period that has already ended,
     * during the game or after it.
     */
    public function addLateEvent(User $user, Game $game): bool
    {
        return $user->hasRole(RoleName::Administrator) && $game->endedPeriods() !== [];
    }

    private function manages(User $actor, Game $game): bool
    {
        if (! $actor->can(PermissionName::CreateUsers->value)) {
            return false;
        }

        $game->loadMissing('team');

        if ($actor->can(PermissionName::CreateClubs->value)) {
            return true;
        }

        return $actor->hasRole(RoleName::Club) && $actor->club_id === $game->team->club_id;
    }
}
