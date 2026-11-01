<?php

namespace App\Livewire\Players;

use App\Actions\UpdatePlayer as UpdatePlayerAction;
use App\Enums\PermissionName;
use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Models\Player;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Closure;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Edit player')]
class Edit extends Component
{
    use InteractsWithAuthenticatedUser, WithFileUploads;

    #[Locked]
    public Player $player;

    #[Locked]
    public string $listSeasonId = '';

    #[Locked]
    public string $listTeamId = '';

    public string $name = '';

    public string $teamId = '';

    public string $fpbId = '';

    public string $dateOfBirth = '';

    public string $number = '';

    public string $position = '';

    public int $order = 0;

    public bool $enabled = true;

    public ?TemporaryUploadedFile $photo = null;

    public function mount(Player $player): void
    {
        $this->authorize('update', $player);

        $this->player = $player;
        $this->listSeasonId = request()->has('season') ? request()->string('season')->toString() : '';
        $this->listTeamId = request()->has('team') ? request()->string('team')->toString() : '';
        $this->name = $player->name;
        $this->teamId = (string) $player->team_id;
        $this->fpbId = $player->fpb_id ?? '';
        $this->dateOfBirth = $player->date_of_birth?->toDateString() ?? '';
        $this->number = $player->number === null ? '' : (string) $player->number;
        $this->position = $player->position ?? '';
        $this->order = $player->order;
        $this->enabled = $player->enabled;
    }

    public function save(UpdatePlayerAction $updatePlayer): void
    {
        $this->authorize('update', $this->player);

        $validated = $this->validate($this->rules());

        $updatePlayer->handle(
            $this->actor(),
            $this->player,
            $validated['name'],
            (int) $validated['teamId'],
            $validated['fpbId'] ?? null,
            $this->parsedDateOfBirth($validated['dateOfBirth'] ?? null),
            $this->shirtNumber($validated['number'] ?? null),
            $validated['position'] ?? null,
            (int) $validated['order'],
            (bool) $validated['enabled'],
            $this->photo,
        );

        Flux::toast(variant: 'success', text: __('Player updated.'));

        $this->redirect($this->playersIndexUrl(), navigate: true);
    }

    public function playersIndexUrl(): string
    {
        $filters = [];

        if ($this->listSeasonId !== '') {
            $filters['season'] = $this->listSeasonId;
        }

        if ($this->listTeamId !== '') {
            $filters['team'] = $this->listTeamId;
        }

        return route('players.index', $filters);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $slug = Player::slugFromName($this->name);
        $playerId = $this->player->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($slug, $playerId): void {
                    if ($slug === '') {
                        $fail(__('The name must contain letters or numbers.'));

                        return;
                    }

                    $exists = Player::query()
                        ->where('team_id', $this->teamId)
                        ->where('slug', $slug)
                        ->whereKeyNot($playerId)
                        ->exists();

                    if ($exists) {
                        $fail(__('A player with this name already exists on this team.'));
                    }
                },
            ],
            'teamId' => ['required', 'integer', Rule::exists(Team::class, 'id')],
            'fpbId' => ['nullable', 'string', 'max:255'],
            'dateOfBirth' => ['nullable', 'date', 'before:today'],
            'number' => [
                'nullable',
                'integer',
                'between:0,99',
                Rule::unique(Player::class, 'number')->where(fn ($query) => $query->where('team_id', $this->teamId))->ignore($playerId),
            ],
            'position' => ['nullable', 'string', 'max:255'],
            'order' => ['required', 'integer', 'min:0'],
            'enabled' => ['boolean'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    private function parsedDateOfBirth(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->startOfDay();
    }

    private function shirtNumber(mixed $value): ?int
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
