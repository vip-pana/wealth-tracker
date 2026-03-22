<?php

declare(strict_types=1);

namespace App\Enums;

enum MacroCategory: string
{
    case Liquidita = 'Liquidità';
    case ETF = 'ETF';
    case Cripto = 'Cripto';
}
