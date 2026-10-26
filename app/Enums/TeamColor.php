<?php

namespace App\Enums;

enum TeamColor: string
{
    case Red = 'red';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case Green = 'green';
    case Cyan = 'cyan';
    case Purple = 'purple';
    case Pink = 'pink';
    case White = 'white';
    case Maroon = 'maroon';
    case Brown = 'brown';
    case Olive = 'olive';
    case DarkGreen = 'dark-green';
    case Blue = 'blue';
    case DarkPurple = 'dark-purple';
    case Black = 'black';
    case Gray = 'gray';

    public function label(): string
    {
        return match ($this) {
            self::Red => 'Red',
            self::Orange => 'Orange',
            self::Yellow => 'Yellow',
            self::Green => 'Green',
            self::Cyan => 'Cyan',
            self::Purple => 'Purple',
            self::Pink => 'Pink',
            self::White => 'White',
            self::Maroon => 'Maroon',
            self::Brown => 'Brown',
            self::Olive => 'Olive',
            self::DarkGreen => 'Dark green',
            self::Blue => 'Blue',
            self::DarkPurple => 'Dark purple',
            self::Black => 'Black',
            self::Gray => 'Gray',
        };
    }

    public function hex(): string
    {
        return match ($this) {
            self::Red => '#e53935',
            self::Orange => '#f57c00',
            self::Yellow => '#f5c400',
            self::Green => '#43a047',
            self::Cyan => '#00a0e4',
            self::Purple => '#9c27b0',
            self::Pink => '#e91e63',
            self::White => '#ffffff',
            self::Maroon => '#7a1f1f',
            self::Brown => '#8d4a1f',
            self::Olive => '#8a8f1e',
            self::DarkGreen => '#1b6b32',
            self::Blue => '#1a3aad',
            self::DarkPurple => '#4a1a7a',
            self::Black => '#111111',
            self::Gray => '#9a9a9a',
        };
    }

    public function textHex(): string
    {
        return match ($this) {
            self::White, self::Yellow, self::Gray => '#111111',
            default => '#ffffff',
        };
    }
}
