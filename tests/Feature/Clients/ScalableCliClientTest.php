<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Http\Clients\ScalableCliClient;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ScalableCliClientTest extends TestCase
{
    private function client(bool $enabled = true): ScalableCliClient
    {
        return new ScalableCliClient($enabled, 'sc', 30);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function envelope(array $payload): string
    {
        return (string) json_encode(['ok' => true, 'command' => 'x', 'data' => $payload]);
    }

    public function test_positions_maps_holdings_to_market_value(): void
    {
        Process::fake([
            '*broker*holdings*' => Process::result($this->envelope([
                'result' => ['items' => [
                    ['isin' => 'IE00B6R52259', 'name' => 'iShares MSCI ACWI (Acc)', 'valuation' => 15497.4, 'valuation_currency' => 'EUR'],
                    ['isin' => 'IE00B579F325', 'name' => 'Invesco Physical Gold ETC', 'valuation' => 1481.48, 'valuation_currency' => 'EUR'],
                ]],
            ])),
        ]);

        $positions = $this->client()->positions();

        $this->assertSame([
            ['isin' => 'IE00B6R52259', 'name' => 'iShares MSCI ACWI (Acc)', 'value' => 15497.4],
            ['isin' => 'IE00B579F325', 'name' => 'Invesco Physical Gold ETC', 'value' => 1481.48],
        ], $positions);
    }

    public function test_cash_balance_is_total_minus_securities_minus_crypto(): void
    {
        Process::fake([
            '*broker*overview*' => Process::result($this->envelope([
                'result' => ['valuation' => ['total' => 20556.88, 'securities' => 16978.88, 'crypto' => 0]],
            ])),
        ]);

        $this->assertEqualsWithDelta(3578.0, (float) $this->client()->cashBalance(), 0.001);
    }

    public function test_no_session_returns_null(): void
    {
        Process::fake([
            '*' => Process::result((string) json_encode(['ok' => false, 'error' => ['code' => 'no_session']])),
        ]);

        $client = $this->client();
        $this->assertNull($client->positions());
        $this->assertNull($client->cashBalance());
        $this->assertFalse($client->isLoggedIn());
    }

    public function test_non_zero_exit_returns_null(): void
    {
        Process::fake(['*' => Process::result('', '', 1)]);

        $this->assertNull($this->client()->positions());
    }

    public function test_unexpected_shape_returns_null(): void
    {
        Process::fake(['*' => Process::result($this->envelope([]))]);

        $this->assertNull($this->client()->positions());
        $this->assertNull($this->client()->cashBalance());
    }

    public function test_disabled_client_runs_nothing(): void
    {
        Process::fake();

        $client = $this->client(enabled: false);

        $this->assertNull($client->positions());
        $this->assertNull($client->cashBalance());
        $this->assertFalse($client->isLoggedIn());
        Process::assertNothingRan();
    }

    public function test_is_logged_in_when_whoami_succeeds(): void
    {
        Process::fake([
            '*whoami*' => Process::result($this->envelope(['result' => ['personOverview' => ['id' => 'x']]])),
        ]);

        $this->assertTrue($this->client()->isLoggedIn());
    }

    public function test_logout_clears_the_session(): void
    {
        Process::fake([
            '*logout*' => Process::result($this->envelope(['logged_out' => true])),
        ]);

        $this->assertTrue($this->client()->logout());
    }

    public function test_logout_reports_failure_on_a_non_zero_exit(): void
    {
        Process::fake(['*logout*' => Process::result('', '', 1)]);

        $this->assertFalse($this->client()->logout());
    }
}
