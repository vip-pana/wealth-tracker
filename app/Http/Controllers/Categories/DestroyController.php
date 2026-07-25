<?php

declare(strict_types=1);

namespace App\Http\Controllers\Categories;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
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

        // A bank account links its logical asset by category; the FK's
        // nullOnDelete never fires under soft-deletes, so guard it here.
        if (BankAccount::where('linked_category_id', $category->id)->exists()) {
            return redirect()->back()->withErrors([
                'category' => 'Non puoi eliminare una categoria collegata a un conto bancario. Scollega prima il conto in Impostazioni → Conti bancari.',
            ]);
        }

        $category->delete();

        return redirect()->back()
            ->with('success', 'Categoria eliminata.')
            ->with('undo', route('categories.restore', $category->id, absolute: false));
    }
}
