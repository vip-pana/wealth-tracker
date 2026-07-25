<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Actions\Analytics\ImportCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\ImportCsvRequest;
use Illuminate\Http\RedirectResponse;

class ImportCsvController extends Controller
{
    public function __construct(
        private readonly ImportCsv $importCsv,
    ) {}

    public function __invoke(ImportCsvRequest $request): RedirectResponse
    {
        $result = $this->importCsv->run($request->csvFile());

        $parts = [];
        if ($result->created > 0) {
            $parts[] = "{$result->created} creati";
        }
        if ($result->updated > 0) {
            $parts[] = "{$result->updated} aggiornati";
        }
        if ($result->skipped > 0) {
            $parts[] = "{$result->skipped} saltati";
        }

        $message = implode(', ', $parts) ?: 'Nessuna riga importata.';

        if (count($result->errors) > 0) {
            $errorSummary = implode(' | ', array_slice($result->errors, 0, 5));
            $message .= '. Errori: '.$errorSummary;

            return redirect()->back()->with('error', $message);
        }

        return redirect()->back()->with('success', $message);
    }
}
