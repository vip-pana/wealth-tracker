<?php

declare(strict_types=1);

namespace App\Http\Controllers\Categories;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;

class DestroyController extends Controller
{
    public function __invoke(Category $category): RedirectResponse
    {
        if ($category->assets()->exists()) {
            return redirect()->back()->withErrors([
                'category' => 'Non puoi eliminare una categoria con asset associati.',
            ]);
        }

        $category->delete();

        return redirect()->back()
            ->with('success', 'Categoria eliminata.')
            ->with('undo', route('categories.restore', $category->id, absolute: false));
    }
}
