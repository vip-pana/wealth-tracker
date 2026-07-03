<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Actions\Action;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

final readonly class ImportCsvResult
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $skipped,
        /** @var list<string> */
        public array $errors,
    ) {}
}

class ImportCsv extends Action
{
    public function run(UploadedFile $file): ImportCsvResult
    {
        /** @var array<string, int> $categoryMap */
        $categoryMap = Category::all()
            ->mapWithKeys(fn (Category $c): array => [mb_strtolower(trim($c->name)) => $c->id])
            ->all();

        $realPath = $file->getRealPath();
        $handle = fopen($realPath !== false ? $realPath : '', 'r');

        if (! is_resource($handle)) {
            return new ImportCsvResult(0, 0, 0, ['Impossibile aprire il file.']);
        }

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Skip header row
        fgetcsv($handle, 0, ';', escape: '\\');

        $created = 0;
        $updated = 0;
        $skipped = 0;
        /** @var list<string> $errors */
        $errors = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle, 0, ';', escape: '\\')) !== false) {
            $lineNumber++;

            if (count($row) < 4) {
                $skipped++;

                continue;
            }

            $rawDate = trim((string) $row[0]);
            $rawCat = trim((string) $row[1]);
            $rawName = trim((string) $row[2]);
            $rawValue = trim((string) $row[3]);
            $rawNote = isset($row[4]) ? trim($row[4]) : '';

            // Validate date — createFromFormat silently overflows out-of-range parts
            // (e.g. 2026-13-99 -> 2027-04-09) and throws on unparseable input, so we
            // round-trip the formatted result to reject anything that didn't match exactly.
            $date = $this->parseDate($rawDate);
            if (! $date instanceof Carbon) {
                $errors[] = "Riga {$lineNumber}: data '{$rawDate}' non valida (formato atteso: YYYY-MM-DD).";
                $skipped++;

                continue;
            }

            // Resolve category
            $catKey = mb_strtolower(trim($rawCat));
            if (! isset($categoryMap[$catKey])) {
                $errors[] = "Riga {$lineNumber}: categoria '{$rawCat}' non trovata.";
                $skipped++;

                continue;
            }

            // Skip empty name
            if ($rawName === '') {
                $errors[] = "Riga {$lineNumber}: nome asset mancante.";
                $skipped++;

                continue;
            }

            $value = $this->parseDecimal($rawValue);

            $asset = Asset::updateOrCreate(
                [
                    'category_id' => $categoryMap[$catKey],
                    'name' => $rawName,
                    'date' => $date->format('Y-m-d'),
                ],
                [
                    'value' => $value,
                    'notes' => $rawNote !== '' ? $rawNote : null,
                ],
            );

            if ($asset->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        fclose($handle);

        return new ImportCsvResult($created, $updated, $skipped, $errors);
    }

    private function parseDate(string $raw): ?Carbon
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d', $raw);
        } catch (\Throwable) {
            return null;
        }

        if (! $date instanceof Carbon || $date->format('Y-m-d') !== $raw) {
            return null;
        }

        return $date;
    }

    private function parseDecimal(string $raw): float
    {
        $raw = str_replace(' ', '', $raw);
        $lastComma = strrpos($raw, ',');
        $lastDot = strrpos($raw, '.');

        if ($lastComma !== false && $lastDot !== false) {
            // Both present: the rightmost is the decimal separator, the other groups thousands.
            $decimalSep = $lastComma > $lastDot ? ',' : '.';
            $thousandsSep = $decimalSep === ',' ? '.' : ',';
            $raw = str_replace($thousandsSep, '', $raw);
            $raw = str_replace($decimalSep, '.', $raw);
        } elseif ($lastComma !== false) {
            // Only a comma: treat it as the decimal separator (European format).
            $raw = str_replace(',', '.', $raw);
        }

        return (float) $raw;
    }
}
