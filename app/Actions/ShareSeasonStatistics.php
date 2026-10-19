<?php

namespace App\Actions;

use App\Models\Team;
use Illuminate\Support\Str;

class ShareSeasonStatistics
{
    public function publish(Team $team): Team
    {
        if ($team->share_token !== null) {
            return $team;
        }

        return $this->storeToken($team);
    }

    public function rotate(Team $team): Team
    {
        return $this->storeToken($team);
    }

    public function revoke(Team $team): Team
    {
        $team->forceFill(['share_token' => null])->save();

        return $team;
    }

    private function storeToken(Team $team): Team
    {
        $team->forceFill(['share_token' => Str::random(40)])->save();

        return $team;
    }
}
