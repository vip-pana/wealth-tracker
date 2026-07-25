<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Analytics\ImportCsv;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportCsvTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('import.csv', $body);
    }

    public function test_imports_a_simple_row(): void
    {
        $cat = Category::factory()->create(['name' => 'Liquidità']);

        $result = app(ImportCsv::class)->run($this->csv(
            "date;category;name;value;notes\n2026-01-01;Liquidità;Conto;1500,50;\n"
        ));

        $this->assertSame(1, $result->created);
        $this->assertSame(0, $result->updated);
        $this->assertSame(0, $result->skipped);
        $this->assertDatabaseHas('assets', [
            'category_id' => $cat->id,
            'name' => 'Conto',
            'value' => 1500.50,
            'date' => '2026-01-01',
        ]);
    }

    public function test_parses_european_thousands_separator(): void
    {
        Category::factory()->create(['name' => 'ETF']);

        $result = app(ImportCsv::class)->run($this->csv(
            "date;category;name;value;notes\n2026-01-01;ETF;World;1.234,56;\n"
        ));

        $this->assertSame(1, $result->created);
        $asset = Asset::firstOrFail();
        $this->assertEqualsWithDelta(1234.56, (float) $asset->value, 0.001);
    }

    public function test_parses_plain_dot_decimal(): void
    {
        Category::factory()->create(['name' => 'ETF']);

        app(ImportCsv::class)->run($this->csv(
            "date;category;name;value;notes\n2026-01-01;ETF;World;1234.56;\n"
        ));

        $this->assertEqualsWithDelta(1234.56, (float) Asset::firstOrFail()->value, 0.001);
    }

    public function test_round_trips_export_format(): void
    {
        // ExportCsv writes values as number_format($v, 2, ',', '.') — e.g. "12.345,67".
        // Importing that string back must reproduce the original amount, not 12.345.
        Category::factory()->create(['name' => 'ETF']);

        app(ImportCsv::class)->run($this->csv(
            "date;category;name;value;notes\n2026-01-01;ETF;World;12.345,67;\n"
        ));

        $this->assertEqualsWithDelta(12345.67, (float) Asset::firstOrFail()->value, 0.001);
    }

    public function test_strips_utf8_bom_from_first_cell(): void
    {
        Category::factory()->create(['name' => 'ETF']);

        // BOM precedes the header row; the header must still be skipped and the data row imported.
        $result = app(ImportCsv::class)->run($this->csv(
            "\xEF\xBB\xBFdate;category;name;value;notes\n2026-01-01;ETF;World;100,00;\n"
        ));

        $this->assertSame(1, $result->created);
        $this->assertDatabaseHas('assets', ['name' => 'World']);
    }

    public function test_matches_category_ignoring_case_and_surrounding_whitespace(): void
    {
        $cat = Category::factory()->create(['name' => 'Liquidità']);

        $result = app(ImportCsv::class)->run($this->csv(
            "date;category;name;value;notes\n2026-01-01;  LIQUIDITÀ  ;Conto;100,00;\n"
        ));

        $this->assertSame(1, $result->created);
        $this->assertDatabaseHas('assets', ['category_id' => $cat->id, 'name' => 'Conto']);
    }

    public function test_updates_an_existing_asset_instead_of_duplicating(): void
    {
        $cat = Category::factory()->create(['name' => 'ETF']);
        Asset::factory()->create([
            'category_id' => $cat->id,
            'name' => 'World',
            'date' => '2026-01-01',
            'value' => 100,
        ]);

        $result = app(ImportCsv::class)->run($this->csv(
            "date;category;name;value;notes\n2026-01-01;ETF;World;250,00;\n"
        ));

        $this->assertSame(0, $result->created);
        $this->assertSame(1, $result->updated);
        $this->assertSame(1, Asset::where('name', 'World')->count());
        $this->assertEqualsWithDelta(250.0, (float) Asset::where('name', 'World')->firstOrFail()->value, 0.01);
    }

    public function test_skips_and_reports_malformed_dates(): void
    {
        Category::factory()->create(['name' => 'ETF']);

        $result = app(ImportCsv::class)->run($this->csv(
            "date;category;name;value;notes\n2026-13-99;ETF;World;100,00;\n"
        ));

        $this->assertSame(0, $result->created);
        $this->assertSame(1, $result->skipped);
        $this->assertNotEmpty($result->errors);
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_skips_unparseable_date_without_crashing(): void
    {
        Category::factory()->create(['name' => 'ETF']);

        $result = app(ImportCsv::class)->run($this->csv(
            "date;category;name;value;notes\nbanana;ETF;World;100,00;\n"
        ));

        $this->assertSame(1, $result->skipped);
        $this->assertNotEmpty($result->errors);
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_skips_rows_with_unknown_category(): void
    {
        Category::factory()->create(['name' => 'ETF']);

        $result = app(ImportCsv::class)->run($this->csv(
            "date;category;name;value;notes\n2026-01-01;Inesistente;World;100,00;\n"
        ));

        $this->assertSame(1, $result->skipped);
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_skips_rows_with_too_few_columns(): void
    {
        Category::factory()->create(['name' => 'ETF']);

        $result = app(ImportCsv::class)->run($this->csv(
            "date;category;name;value;notes\n2026-01-01;ETF;World\n"
        ));

        $this->assertSame(1, $result->skipped);
        $this->assertDatabaseCount('assets', 0);
    }
}
