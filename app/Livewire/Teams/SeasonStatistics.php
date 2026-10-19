<?php

namespace App\Livewire\Teams;

use App\Actions\ShareSeasonStatistics;
use App\Models\Team;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Season statistics')]
class SeasonStatistics extends Component
{
    #[Locked]
    public Team $team;

    #[Locked]
    public bool $shared = false;

    public bool $sharing = false;

    public function mount(Team $team): void
    {
        $this->authorize('viewSeasonStatistics', $team);

        $this->team = $team->loadMissing('season');
    }

    public function openShare(ShareSeasonStatistics $share): void
    {
        $this->guardShare();
        $share->publish($this->team);
        $this->sharing = true;
    }

    public function rotateShare(ShareSeasonStatistics $share): void
    {
        $this->guardShare();
        $share->rotate($this->team);

        Flux::toast(variant: 'success', text: __('New share link created.'));
    }

    public function revokeShare(ShareSeasonStatistics $share): void
    {
        $this->guardShare();
        $share->revoke($this->team);
        $this->sharing = false;

        Flux::toast(text: __('Sharing stopped.'));
    }

    public function copiedShareLink(): void
    {
        $this->guardShare();

        Flux::toast(variant: 'success', text: __('Link copied.'));
    }

    public function render(): View
    {
        return view('livewire.teams.season-statistics', [
            'gamesHistory' => $this->team->gamesHistory(),
            'matchupHistory' => $this->team->matchupHistory(),
            'boxScore' => $this->team->seasonBoxScore(),
            'shareUrl' => $this->shareUrl(),
        ]);
    }

    private function guardShare(): void
    {
        abort_if($this->shared, 404);

        $this->authorize('viewSeasonStatistics', $this->team);
    }

    private function shareUrl(): ?string
    {
        if ($this->team->share_token === null) {
            return null;
        }

        return route('teams.season-statistics.shared', ['team' => $this->team->share_token]);
    }
}
