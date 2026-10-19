<?php

namespace App\Livewire\Teams;

use App\Models\Team;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

#[Layout('layouts.collect', ['titleAsGiven' => true])]
class SharedSeasonStatistics extends SeasonStatistics
{
    public function mount(Team $team): void
    {
        abort_if($team->share_token === null, 404);

        $this->shared = true;
        $this->team = $team->loadMissing('season');
    }

    public function render(): View
    {
        return parent::render()->title($this->team->name.' · '.$this->team->season->name);
    }
}
