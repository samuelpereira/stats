<?php

namespace App\Livewire\Games\Concerns;

use App\Models\Game;
use App\Models\Team;
use App\Services\Fpb\FpbGamesImporter;
use Flux\Flux;
use Throwable;

trait FetchesFpbGame
{
    public string $opponentLogoUrl = '';

    public function fetchFromFpb(FpbGamesImporter $importer): void
    {
        $this->authorizeFormAccess();

        $this->validate([
            'teamId' => ['required', 'integer'],
            'fpbId' => ['required', 'string', 'max:255'],
        ]);

        $team = Team::query()->with('club')->find((int) $this->teamId);

        if (! $team instanceof Team) {
            $this->addError('teamId', __('Select a team'));

            return;
        }

        try {
            $sheet = $importer->fetchGameForTeam($team, $this->fpbId);
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: __('FPB import failed: :message', ['message' => $exception->getMessage()]));

            return;
        }

        $this->opponent = $sheet['opponent'];
        $this->dateTime = $sheet['date_time']->format('Y-m-d\TH:i');
        $this->place = $sheet['place'] ?? '';
        $this->homeAway = $sheet['home_away']->value;
        $this->competition = $sheet['competition'] ?? '';
        $this->journey = $sheet['journey'] ?? '';
        $this->homePoints = $sheet['home_points'] === null ? '' : (string) $sheet['home_points'];
        $this->awayPoints = $sheet['away_points'] === null ? '' : (string) $sheet['away_points'];
        $this->status = $sheet['status']->value;
        $this->opponentLogoUrl = $sheet['opponent_logo'] ?? '';

        Flux::toast(variant: 'success', text: __('FPB game details loaded.'));
    }

    protected function attachFetchedOpponentLogo(Game $game, FpbGamesImporter $importer): void
    {
        if ($this->opponentLogoUrl === '') {
            return;
        }

        $importer->attachOpponentLogo($game, $this->opponentLogoUrl);
    }

    abstract protected function authorizeFormAccess(): void;
}
