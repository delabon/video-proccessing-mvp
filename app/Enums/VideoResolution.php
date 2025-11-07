<?php

namespace App\Enums;

enum VideoResolution: string
{
    case FourK = '4k';
    case TwoK = '2k';
    case Fhd = '1080p';
    case Hd = '720p';
    case Sd = '480p';

    public function dimension(): array
    {
        return match ($this) {
            self::FourK => ['w' => 3840, 'h' => 2160],
            self::TwoK => ['w' => 2560, 'h' => 1440],
            self::Fhd => ['w' => 1920, 'h' => 1080],
            self::Hd => ['w' => 1280, 'h' => 720],
            self::Sd => ['w' => 854, 'h' => 480],
        };
    }

    public static function dimensions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $case) => [
                $case->value => $case->dimension(),
            ])
            ->toArray();
    }

    public function bitrate(): int
    {
        return match ($this) {
            self::FourK => 16000,
            self::TwoK => 12000,
            self::Fhd => 8000,
            self::Hd => 5000,
            self::Sd => 2500,
        };
    }

    public static function bitrates(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $case) => [
                $case->value => $case->bitrate(),
            ])
            ->toArray();
    }
}
