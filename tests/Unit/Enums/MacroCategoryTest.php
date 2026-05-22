<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\MacroCategory;
use Tests\TestCase;

class MacroCategoryTest extends TestCase
{
    public function test_fondo_pensione_is_illiquid(): void
    {
        $this->assertTrue(MacroCategory::FondoPensione->isIlliquid());
    }

    public function test_other_macros_are_liquid(): void
    {
        $this->assertFalse(MacroCategory::Liquidita->isIlliquid());
        $this->assertFalse(MacroCategory::ETF->isIlliquid());
        $this->assertFalse(MacroCategory::Cripto->isIlliquid());
    }

    public function test_fondo_pensione_is_annual(): void
    {
        $this->assertTrue(MacroCategory::FondoPensione->isAnnual());
    }

    public function test_other_macros_are_not_annual(): void
    {
        $this->assertFalse(MacroCategory::Liquidita->isAnnual());
        $this->assertFalse(MacroCategory::ETF->isAnnual());
        $this->assertFalse(MacroCategory::Cripto->isAnnual());
    }

    public function test_illiquid_values_returns_fondo_pensione_value(): void
    {
        $this->assertSame(['Fondo Pensione'], MacroCategory::illiquidValues());
    }
}
