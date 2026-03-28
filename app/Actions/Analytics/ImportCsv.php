<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Actions\Action;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

final class ImportCsvResult
{
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly int $skipped,
        /** @var list<string> */
        public readonly array $errors,
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
        fgetcsv($handle, 0, ';');

        $created = 0;
        $updated = 0;
        $skipped = 0;
        /** @var list<string> $errors */
        $errors = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $lineNumber++;

            if (count($row) < 4) {
                $skipped++;

                continue;
            }

            $rawDate = trim((string) $row[0]);
            $rawCat = trim((string) $row[1]);
            $rawName = trim((string) $row[2]);
            $rawValue = trim((string) $row[3]);
            $rawNote = isset($row[4]) ? trim((string) $row[4]) : '';

            // Validate date
            $date = Carbon::createFromFormat('Y-m-d', $rawDate);
            if (! $date instanceof Carbon) {
                $errors[] = "Riga {$lineNumber}: data '{$rawDate}' non valida (formato atteso: YYYY-MM-DD).";
                $skipped++;

                continue;
            }

            // Resolve category
            $catKey = mb_strtolower($rawCat);
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

            $value = (float) str_replace(',', '.', $rawValue);

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
}
