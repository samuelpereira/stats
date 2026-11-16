<?php

namespace App\Livewire\Forms;

use App\Enums\FoulKind;
use App\Enums\StatType;
use Illuminate\Validation\Rule;
use Livewire\Form;

class LateGameEventForm extends Form
{
    public ?int $quarter = null;

    public string $clock = '';

    public string $type = '';

    /** "player:{id}", "coach", "team" or "opponent". */
    public string $actor = '';

    public ?string $foulKind = null;

    /** The player who goes out, for a substitution ("actor" is who comes in). */
    public ?int $outgoing = null;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'quarter' => ['required', 'integer', 'min:1', 'max:255'],
            'clock' => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
            'type' => ['required', Rule::in(self::typeValues())],
            'actor' => ['required', 'regex:/^(player:\d+|coach|team|opponent)$/'],
            'foulKind' => ['nullable', Rule::enum(FoulKind::class)],
            'outgoing' => ['nullable', 'integer', Rule::requiredIf($this->type === StatType::Substitution->value)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'clock.regex' => __('The clock must use minutes and seconds, such as 05:00.'),
            'actor.required' => __('Choose who made the action.'),
            'actor.regex' => __('Choose who made the action.'),
            'outgoing.required' => __('Choose who goes out.'),
        ];
    }

    /**
     * Actions that can be added to an ended period: every stat, the substitution and the timeout.
     *
     * @return list<StatType>
     */
    public static function types(): array
    {
        return array_values(array_filter(
            StatType::cases(),
            fn (StatType $type): bool => ! $type->isPeriod(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function typeValues(): array
    {
        return array_map(fn (StatType $type): string => $type->value, self::types());
    }

    public function stat(): StatType
    {
        return StatType::from($this->type);
    }

    public function forOpponent(): bool
    {
        return $this->actor === 'opponent';
    }

    public function playerId(): ?int
    {
        if (! str_starts_with($this->actor, 'player:')) {
            return null;
        }

        return (int) substr($this->actor, 7);
    }

    public function foulKindEnum(): ?FoulKind
    {
        return $this->foulKind === null || $this->foulKind === ''
            ? null
            : FoulKind::tryFrom($this->foulKind);
    }

    public function start(int $quarter, string $clock): void
    {
        $this->reset();
        $this->resetErrorBag();
        $this->quarter = $quarter;
        $this->clock = $clock;
    }
}
