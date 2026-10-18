<?php

namespace App\Livewire\Concerns;

use Livewire\WithPagination;

trait PaginatesListings
{
    use WithPagination;

    protected function listingPerPage(): int
    {
        return 15;
    }
}
