<?php

declare(strict_types=1);

namespace Tests\Unit\Advisor;

use App\Advisor\Tools\AdvisorWidgetCollector;
use App\Models\AdvisorMessage;
use Tests\TestCase;

class AdvisorWidgetCollectorTest extends TestCase
{
    private function armed(): AdvisorWidgetCollector
    {
        $collector = new AdvisorWidgetCollector;
        // A bare model instance is enough to arm it; add() only checks the type.
        $collector->for(new AdvisorMessage);

        return $collector;
    }

    public function test_add_is_a_no_op_when_not_armed(): void
    {
        $collector = new AdvisorWidgetCollector;
        $collector->add('profile_proposal', ['horizon' => 'long']);

        $this->assertSame([], $collector->widgets());
    }

    public function test_keeps_only_the_last_profile_proposal(): void
    {
        $collector = $this->armed();
        $collector->add('profile_proposal', ['risk_tolerance' => 'medium']);
        $collector->add('profile_proposal', ['risk_tolerance' => 'high']);

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('high', $widgets[0]['data']['risk_tolerance']);
    }

    public function test_keeps_multiple_widgets_of_other_types(): void
    {
        $collector = $this->armed();
        $collector->add('position_card', ['name' => 'ACWI']);
        $collector->add('position_card', ['name' => 'Bitcoin']);

        $this->assertCount(2, $collector->widgets());
    }

    public function test_dedupes_proposal_but_preserves_other_widgets_and_order(): void
    {
        $collector = $this->armed();
        $collector->add('position_card', ['name' => 'ACWI']);
        $collector->add('profile_proposal', ['risk_tolerance' => 'medium']);
        $collector->add('profile_proposal', ['risk_tolerance' => 'high']);

        $widgets = $collector->widgets();
        $this->assertCount(2, $widgets);
        $this->assertSame('position_card', $widgets[0]['type']);
        $this->assertSame('profile_proposal', $widgets[1]['type']);
        $this->assertSame('high', $widgets[1]['data']['risk_tolerance']);
    }
}
