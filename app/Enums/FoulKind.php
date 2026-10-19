<?php

namespace App\Enums;

enum FoulKind: string
{
    case Technical = 'technical';
    case Disruptive = 'disruptive';
    case Flagrant = 'flagrant';
    case Disqualifying = 'disqualifying';

    public function logLabel(): string
    {
        return match ($this) {
            self::Technical => 'tech foul',
            self::Disruptive => 'disruptive foul',
            self::Flagrant => 'flagrant foul',
            self::Disqualifying => 'disqualifying foul',
        };
    }

    public function pickerTitle(): string
    {
        return match ($this) {
            self::Technical => 'Technical',
            self::Disruptive => 'Disruptive',
            self::Flagrant => 'Flagrant',
            self::Disqualifying => 'Disqualifying',
        };
    }

    public function allowsCoach(): bool
    {
        return $this === self::Technical;
    }

    public function countsInTechnicalColumn(): bool
    {
        return match ($this) {
            self::Technical, self::Flagrant, self::Disqualifying => true,
            self::Disruptive => false,
        };
    }
}
