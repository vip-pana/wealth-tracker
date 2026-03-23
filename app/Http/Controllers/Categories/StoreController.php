<?php

declare(strict_types=1);

namespace App\Http\Controllers\Categories;

use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    public function __invoke(StoreCategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return redirect()->back()->with('success', 'Categoria creata.');
    }
}
