<?php

namespace App\Livewire\Games;

use App\Actions\CreateGame as CreateGameAction;
use App\Enums\GameStatus;
use App\Enums\HomeAway;
use App\Enums\PermissionName;
use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Games\Concerns\FetchesFpbGame;
use App\Models\Game;
use App\Models\Team;
use App\Services\Fpb\FpbGamesImporter;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('New game')]
class Create extends Component
{
    use FetchesFpbGame;
    use InteractsWithAuthenticatedUser;

    public string $teamId = '';

    public string $fpbId = '';

    public string $opponent = '';

    public string $dateTime = '';

    public string $place = '';

    public string $homeAway = '';

    public string $competition = '';

    public string $journey = '';

    public string $homePoints = '';

    public string $awayPoints = '';

    public string $status = '';

    public function mount(): void
    {
        $this->authorize('create', Game::class);
    }

    public function save(CreateGameAction $createGame, FpbGamesImporter $importer): void
    {
        $this->authorize('create', Game::class);

        $validated = $this->validate($this->rules());

        $game = $createGame->handle(
            $this->actor(),
            (int) $validated['teamId'],
            $validated['fpbId'] ?? null,
            $validated['opponent'],
            CarbonImmutable::parse($validated['dateTime']),
            $validated['place'] ?? null,
            HomeAway::from($validated['homeAway']),
            $validated['competition'] ?? null,
            $validated['journey'] ?? null,
            $this->points($validated['homePoints'] ?? null),
            $this->points($validated['awayPoints'] ?? null),
            GameStatus::from($validated['status']),
        );

        $this->attachFetchedOpponentLogo($game, $importer);

        Flux::toast(variant: 'success', text: __('Game created.'));

        $this->redirect(route('games.index'), navigate: true);
    }

    protected function authorizeFormAccess(): void
    {
        $this->authorize('create', Game::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'teamId' => ['required', 'integer', Rule::exists(Team::class, 'id')],
            'fpbId' => ['nullable', 'string', 'max:255', Rule::unique(Game::class, 'fpb_id')],
            'opponent' => ['required', 'string', 'max:255'],
            'dateTime' => ['required', 'date'],
            'place' => ['nullable', 'string', 'max:255'],
            'homeAway' => ['required', Rule::enum(HomeAway::class)],
            'competition' => ['nullable', 'string', 'max:255'],
            'journey' => ['nullable', 'string', 'max:255'],
            'homePoints' => ['nullable', 'integer', 'min:0', 'max:999'],
            'awayPoints' => ['nullable', 'integer', 'min:0', 'max:999'],
            'status' => ['required', Rule::enum(GameStatus::class)],
        ];
    }

    private function points(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return Collection<int, Team>
     */
    #[Computed]
    public function teams(): Collection
    {
        $actor = $this->actor();

        $query = Team::query()->with(['club', 'season'])->orderBy('name');

        if (! $actor->can(PermissionName::CreateClubs->value)) {
            $query->where('club_id', $actor->club_id);
        }

        return $query->get();
    }
}
