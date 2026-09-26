<?php

namespace App\Enums;

enum MalaysianState: string
{
    case Johor = 'johor';
    case Kedah = 'kedah';
    case Kelantan = 'kelantan';
    case Melaka = 'melaka';
    case NegeriSembilan = 'negeri-sembilan';
    case Pahang = 'pahang';
    case Perak = 'perak';
    case Perlis = 'perlis';
    case PulauPinang = 'pulau-pinang';
    case Sabah = 'sabah';
    case Sarawak = 'sarawak';
    case Selangor = 'selangor';
    case Terengganu = 'terengganu';
    case KualaLumpur = 'kuala-lumpur';
    case Labuan = 'labuan';
    case Putrajaya = 'putrajaya';

    public function label(): string
    {
        return match ($this) {
            self::NegeriSembilan => 'Negeri Sembilan',
            self::PulauPinang => 'Pulau Pinang',
            self::KualaLumpur => 'WP Kuala Lumpur',
            self::Labuan => 'WP Labuan',
            self::Putrajaya => 'WP Putrajaya',
            default => ucfirst($this->value),
        };
    }
}
