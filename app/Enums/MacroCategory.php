<?php

declare(strict_types=1);

namespace App\Enums;

enum MacroCategory: string
{
    case Liquidita = 'Liquidità';
    case ETF = 'ETF';
    case Cripto = 'Cripto';
    case FondoPensione = 'Fondo Pensione';

    public function isIlliquid(): bool
    {
        return $this === self::FondoPensione;
    }

    public function isAnnual(): bool
    {
        return $this === self::FondoPensione;
    }

    /** @return list<string> */
    public static function illiquidValues(): array
    {
        return array_values(array_map(
            fn (self $m): string => $m->value,
            array_filter(self::cases(), fn (self $m): bool => $m->isIlliquid()),
        ));
    }
}
