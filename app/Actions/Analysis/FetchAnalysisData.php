<?php

declare(strict_types=1);

namespace App\Actions\Analysis;

use App\Actions\Action;
use App\Actions\FetchAvailableMonths;
use App\Http\Requests\AnalysisRequest;
use App\Models\Category;

class FetchAnalysisData extends Action
{
    public function __construct(
        private readonly FilterAssets $filterAssets,
        private readonly FetchAvailableMonths $fetchAvailableMonths,
    ) {}

    /** @return array<string, mixed> */
    public function run(AnalysisRequest $request): array
    {
        $categoryId = $request->integer('category_id') ?: null;
        $dateFrom = $request->has('date_from') ? $request->string('date_from')->value() : null;
        $dateTo = $request->has('date_to') ? $request->string('date_to')->value() : null;

        $categories = Category::orderBy('sort_order')->get()->map(fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'color' => $c->color,
        ]);

        return [
            'assets' => $this->filterAssets->run($categoryId, $dateFrom, $dateTo),
            'categories' => $categories,
            'availableMonths' => $this->fetchAvailableMonths->run(),
            'filters' => [
                'category_id' => $categoryId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ];
    }
}
