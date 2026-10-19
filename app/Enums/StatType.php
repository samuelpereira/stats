<?php

namespace App\Enums;

enum StatType: string
{
    case TwoPointMade = 'two_point';
    case TwoPointMissed = 'two_point_missed';
    case ThreePointMade = 'three_point';
    case ThreePointMissed = 'three_point_missed';
    case FreeThrowMade = 'free_throw';
    case FreeThrowMissed = 'free_throw_missed';
    case DefensiveRebound = 'defensive_rebound';
    case OffensiveRebound = 'offensive_rebound';
    case Turnover = 'turnover';
    case Steal = 'steal';
    case Assist = 'assist';
    case Block = 'block';
    case Substitution = 'substitution';
    case Foul = 'foul';
    case Timeout = 'timeout';
    case QuarterStarted = 'quarter_started';
    case QuarterEnded = 'quarter_ended';

    public function logLabel(): string
    {
        return match ($this) {
            self::TwoPointMade => '2pt',
            self::TwoPointMissed => '2pt miss',
            self::ThreePointMade => '3pt',
            self::ThreePointMissed => '3pt miss',
            self::FreeThrowMade => '1pt',
            self::FreeThrowMissed => '1pt miss',
            self::DefensiveRebound => 'dreb',
            self::OffensiveRebound => 'oreb',
            self::Turnover => 'tover',
            self::Steal => 'stl',
            self::Assist => 'asst',
            self::Block => 'blk',
            self::Substitution => 'sub',
            self::Foul => 'foul',
            self::Timeout => 'timeout',
            self::QuarterStarted => 'period start',
            self::QuarterEnded => 'period end',
        };
    }

    public function pickerTitle(): string
    {
        return match ($this) {
            self::TwoPointMade => '2-point basket',
            self::TwoPointMissed => 'Missed 2-point',
            self::ThreePointMade => '3-point basket',
            self::ThreePointMissed => 'Missed 3-point',
            self::FreeThrowMade => 'Free throw',
            self::FreeThrowMissed => 'Missed free throw',
            self::DefensiveRebound => 'Defensive Rebound',
            self::OffensiveRebound => 'Offensive Rebound',
            self::Turnover => 'Turnover',
            self::Steal => 'Steal',
            self::Assist => 'Assist',
            self::Block => 'Block',
            self::Substitution => 'Substitution',
            self::Foul => 'Foul',
            self::Timeout => 'Timeout',
            self::QuarterStarted => 'Period start',
            self::QuarterEnded => 'Period end',
        };
    }

    /**
     * @return list<string>
     */
    public function buttonLines(): array
    {
        return match ($this) {
            self::TwoPointMade, self::TwoPointMissed => ['2pt'],
            self::ThreePointMade, self::ThreePointMissed => ['3pt'],
            self::FreeThrowMade, self::FreeThrowMissed => ['1pt'],
            self::DefensiveRebound => app()->isLocale('pt') ? ['Ressalto', 'Defensivo'] : ['Defensive', 'Rebound'],
            self::OffensiveRebound => app()->isLocale('pt') ? ['Ressalto', 'Ofensivo'] : ['Offensive', 'Rebound'],
            self::Assist => ['Assist'],
            self::Block => app()->isLocale('pt') ? ['Desarme', 'Lançamento'] : ['Block'],
            self::Turnover => ['Perda', 'Over'],
            self::Steal => app()->isLocale('pt') ? ['Roubo', 'Bola'] : ['Steal'],
            default => [$this->logLabel()],
        };
    }

    public function points(): int
    {
        return match ($this) {
            self::TwoPointMade => 2,
            self::ThreePointMade => 3,
            self::FreeThrowMade => 1,
            default => 0,
        };
    }

    public function isFieldGoal(): bool
    {
        return match ($this) {
            self::TwoPointMade, self::TwoPointMissed, self::ThreePointMade, self::ThreePointMissed => true,
            default => false,
        };
    }

    public function shootingLog(): ?string
    {
        return match ($this) {
            self::TwoPointMade, self::TwoPointMissed => 'fg',
            self::ThreePointMade, self::ThreePointMissed => '3pt',
            self::FreeThrowMade, self::FreeThrowMissed => 'ft',
            default => null,
        };
    }

    public function isMiss(): bool
    {
        return match ($this) {
            self::TwoPointMissed, self::ThreePointMissed, self::FreeThrowMissed => true,
            default => false,
        };
    }

    public function isSolid(): bool
    {
        return match ($this) {
            self::TwoPointMade, self::ThreePointMade, self::FreeThrowMade => true,
            default => false,
        };
    }

    public function isPeriod(): bool
    {
        return $this === self::QuarterStarted || $this === self::QuarterEnded;
    }

    public function isEditableAction(): bool
    {
        return match ($this) {
            self::QuarterStarted, self::QuarterEnded => false,
            default => true,
        };
    }

    public function isAssignableStat(): bool
    {
        return match ($this) {
            self::Substitution, self::Timeout, self::QuarterStarted, self::QuarterEnded => false,
            default => true,
        };
    }

    /**
     * Types that may replace this action when editing a log line.
     *
     * @return list<self>
     */
    public function editableTypeOptions(): array
    {
        return match ($this) {
            self::TwoPointMade,
            self::TwoPointMissed,
            self::ThreePointMade,
            self::ThreePointMissed,
            self::FreeThrowMade,
            self::FreeThrowMissed => [
                self::TwoPointMade,
                self::TwoPointMissed,
                self::ThreePointMade,
                self::ThreePointMissed,
                self::FreeThrowMade,
                self::FreeThrowMissed,
            ],
            self::DefensiveRebound,
            self::OffensiveRebound,
            self::Turnover,
            self::Steal,
            self::Assist,
            self::Block => [
                self::DefensiveRebound,
                self::OffensiveRebound,
                self::Turnover,
                self::Steal,
                self::Assist,
                self::Block,
            ],
            self::Foul => [self::Foul],
            default => [],
        };
    }

    public function canSwapTo(self $type): bool
    {
        return in_array($type, $this->editableTypeOptions(), true);
    }

    public function hasEditableTypePicker(): bool
    {
        return count($this->editableTypeOptions()) > 1;
    }
}
