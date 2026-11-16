<?php

namespace App\Actions;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class FinishCollectedGame
{
    public function handle(User $actor, Game $game): Game
    {
        if (! $actor->can('collect', $game)) {
            throw new AuthorizationException;
        }

        if (! $game->canFinishAfterFourthQuarter()) {
            throw new AuthorizationException;
        }

        $totals = $game->collectedScoreTotals();

        $game->update([
            'home_points' => $totals['home'],
            'away_points' => $totals['away'],
            'status' => GameStatus::Played,
        ]);

        return $game->refresh();
    }
}
