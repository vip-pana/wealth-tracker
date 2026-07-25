<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ScalableRefreshControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'services.scalable.cli.enabled' => true,
            'services.scalable.cash_category_id' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function envelope(array $data): string
    {
        return (string) json_encode(['ok' => true, 'data' => $data]);
    }

    public function test_flashes_success_when_a_holding_syncs(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create(['category_id' => $cat->id, 'name' => 'ACWI', 'isin' => 'IE00B6R52259', 'date' => now()->format('Y-m-01')]);
        Process::fake([
            '*whoami*' => Process::result($this->envelope(['result' => ['personOverview' => ['id' => 'x']]])),
            '*broker*holdings*' => Process::result($this->envelope([
                'result' => ['items' => [['isin' => 'IE00B6R52259', 'name' => 'ACWI', 'valuation' => 500.0, 'valuation_currency' => 'EUR']]],
            ])),
            '*broker*overview*' => Process::result($this->envelope(['result' => ['valuation' => ['total' => 500.0, 'securities' => 500.0, 'crypto' => 0]]])),
        ]);

        $this->post('/scalable/refresh')
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('error');
    }

    public function test_flashes_error_when_the_session_lapsed(): void
    {
        Process::fake(['*whoami*' => Process::result((string) json_encode(['ok' => false, 'error' => ['code' => 'no_session']]))]);

        $this->post('/scalable/refresh')
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_flashes_error_when_unconfigured(): void
    {
        config(['services.scalable.cli.enabled' => false]);

        $this->post('/scalable/refresh')
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
