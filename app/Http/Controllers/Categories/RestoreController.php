<?php

declare(strict_types=1);

namespace App\Http\Controllers\Categories;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;

class RestoreController extends Controller
{
    public function __invoke(int $category): RedirectResponse
    {
        Category::onlyTrashed()->findOrFail($category)->restore();

        return redirect()->back()->with('success', 'Categoria ripristinata.');
    }
}
