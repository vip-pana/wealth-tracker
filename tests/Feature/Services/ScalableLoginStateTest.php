<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\Scalable\ScalableLoginState;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScalableLoginStateTest extends TestCase
{
    private function state(): ScalableLoginState
    {
        return new ScalableLoginState;
    }

    public function test_idle_snapshot_by_default(): void
    {
        $this->assertSame('idle', $this->state()->snapshot()['status']);
    }

    public function test_url_issued_carries_url_and_code(): void
    {
        $state = $this->state();
        $state->markPending();
        $state->markUrlIssued('https://secure.scalable.capital/activate?user_code=ABCD-1234', 'ABCD-1234');

        $snapshot = $state->snapshot();
        $this->assertSame(ScalableLoginState::URL_ISSUED, $snapshot['status']);
        $this->assertSame('ABCD-1234', $snapshot['user_code']);
        $this->assertTrue($state->isInProgress());
    }

    public function test_complete_and_failed_are_terminal(): void
    {
        $state = $this->state();
        $state->markPending();
        $state->markComplete();
        $this->assertSame(ScalableLoginState::COMPLETE, $state->snapshot()['status']);
        $this->assertFalse($state->isInProgress());

        $state->markFailed('boom');
        $this->assertSame('boom', $state->snapshot()['error']);
        $this->assertFalse($state->isInProgress());
    }

    public function test_in_progress_is_false_for_an_orphaned_pending(): void
    {
        $state = $this->state();
        $state->markPending();

        // A pending older than the TTL window is an orphan from a dead worker.
        Carbon::setTestNow(Carbon::now()->addMinutes(25));
        $this->assertFalse($state->isInProgress());
        Carbon::setTestNow();
    }
}
