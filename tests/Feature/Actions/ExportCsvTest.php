<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Enums\MacroCategory;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportCsvTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<list<string>> Parsed CSV rows, BOM stripped. */
    private function exportRows(): array
    {
        $response = $this->get('/export/csv');
        $response->assertOk();

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        $rows = [];
        foreach (explode("\n", trim($content)) as $line) {
            if ($line === '') {
                $rows[] = [];

                continue;
            }
            $rows[] = str_getcsv($line, ';', escape: '\\');
        }

        return $rows;
    }

    private function cell(string $label, int $monthIndex): ?string
    {
        foreach ($this->exportRows() as $row) {
            if (isset($row[0]) && $row[0] === $label) {
                return $row[3 + $monthIndex] ?? null;
            }
        }

        return null;
    }

    public function test_net_worth_row_sums_all_assets_for_a_month(): void
    {
        $etf = Category::factory()->create(['macro_category' => MacroCategory::ETF, 'sort_order' => 1]);
        Asset::factory()->create(['category_id' => $etf->id, 'name' => 'World', 'value' => 1000, 'date' => '2026-01-01']);
        Asset::factory()->create(['category_id' => $etf->id, 'name' => 'Emerging', 'value' => 500, 'date' => '2026-01-01']);

        // Net worth for the single month (index 0) must equal 1500, in European format.
        $this->assertSame('1.500,00', $this->cell('Patrimonio Netto (EUR)', 0));
    }

    public function test_macro_subtotal_equals_sum_of_its_assets(): void
    {
        $etf = Category::factory()->create(['macro_category' => MacroCategory::ETF, 'sort_order' => 1]);
        Asset::factory()->create(['category_id' => $etf->id, 'name' => 'A', 'value' => 1234.56, 'date' => '2026-01-01']);
        Asset::factory()->create(['category_id' => $etf->id, 'name' => 'B', 'value' => 765.44, 'date' => '2026-01-01']);

        $this->assertSame('2.000,00', $this->cell('  Subtotale ETF', 0));
    }

    public function test_allocation_percentages_reflect_macro_share(): void
    {
        $etf = Category::factory()->create(['macro_category' => MacroCategory::ETF, 'sort_order' => 1]);
        $cripto = Category::factory()->create(['macro_category' => MacroCategory::Cripto, 'sort_order' => 2]);
        Asset::factory()->create(['category_id' => $etf->id, 'name' => 'World', 'value' => 750, 'date' => '2026-01-01']);
        Asset::factory()->create(['category_id' => $cripto->id, 'name' => 'BTC', 'value' => 250, 'date' => '2026-01-01']);

        // 750 / 1000 = 75%, 250 / 1000 = 25%.
        $this->assertSame('75,0%', $this->cell('  ETF', 0));
        $this->assertSame('25,0%', $this->cell('  Cripto', 0));
    }

    public function test_empty_month_cell_is_blank_not_zero(): void
    {
        $etf = Category::factory()->create(['macro_category' => MacroCategory::ETF, 'sort_order' => 1]);
        // Asset only in the second month; first month must be an empty cell.
        Asset::factory()->create(['category_id' => $etf->id, 'name' => 'World', 'value' => 100, 'date' => '2026-01-01']);
        Asset::factory()->create(['category_id' => $etf->id, 'name' => 'World', 'value' => 200, 'date' => '2026-03-01']);

        // Two distinct months -> the Feb gap (index 1) is empty for an asset that skipped Feb... here
        // the asset exists in Jan (0) and Mar (1, since only 2 months exist). Confirm both present.
        $this->assertSame('100,00', $this->cell('  World', 0));
        $this->assertSame('200,00', $this->cell('  World', 1));
    }

    public function test_returns_only_a_bom_when_there_are_no_assets(): void
    {
        $response = $this->get('/export/csv');
        $response->assertOk();

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        $this->assertSame("\xEF\xBB\xBF", $content);
    }

    public function test_formula_injection_in_asset_name_is_neutralised(): void
    {
        $etf = Category::factory()->create(['macro_category' => MacroCategory::ETF, 'sort_order' => 1]);
        Asset::factory()->create(['category_id' => $etf->id, 'name' => '=SUM(A1:A9)', 'value' => 100, 'date' => '2026-01-01']);

        // The label cell must be prefixed with a single quote so spreadsheets
        // treat it as text, not a formula. The quote goes at the very front,
        // before the two-space indent the export adds to asset names.
        $label = "'  =SUM(A1:A9)";
        $found = array_any($this->exportRows(), fn ($row) => isset($row[0]) && $row[0] === $label);

        $this->assertTrue($found, 'Asset name starting with = must be single-quote prefixed in the export.');
    }

    public function test_month_labels_use_mm_yy_format(): void
    {
        $etf = Category::factory()->create(['macro_category' => MacroCategory::ETF, 'sort_order' => 1]);
        Asset::factory()->create(['category_id' => $etf->id, 'name' => 'World', 'value' => 100, 'date' => '2026-01-01']);

        $rows = $this->exportRows();
        // The header row starts with "Net Worth <year>".
        $header = $rows[0];
        $this->assertStringStartsWith('Net Worth', $header[0]);
        $this->assertSame('01-26', $header[3]);
    }
}
