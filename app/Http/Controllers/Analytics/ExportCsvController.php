<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Actions\Analytics\ExportCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportCsvRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportCsvController extends Controller
{
    public function __construct(
        private readonly ExportCsv $exportCsv,
    ) {}

    public function __invoke(ExportCsvRequest $request): StreamedResponse
    {
        return $this->exportCsv->run($request->categoryId(), $request->dateFrom(), $request->dateTo());
    }
}
