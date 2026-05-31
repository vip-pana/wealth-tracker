<?php

declare(strict_types=1);

namespace App\Http\Controllers\Categories;

use App\Http\Clients\EnableBankingClient;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\BankConnection;
use App\Models\Category;
use App\Models\Goal;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    public function __construct(
        private readonly EnableBankingClient $enableBanking,
    ) {}

    public function __invoke(): Response
    {
        $categories = Category::orderBy('sort_order')
            ->withCount('assets')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'icon' => $c->icon,
                'sort_order' => $c->sort_order,
                'macro_category' => $c->macro_category?->value,
                'assets_count' => $c->assets_count,
            ]);

        $prices = AssetPrice::orderBy('ticker')->get()->map(fn (AssetPrice $p) => [
            'ticker' => $p->ticker,
            'price' => $p->price,
            'currency' => $p->currency,
            'fetched_at' => $p->fetched_at?->toISOString(),
            'last_status' => $p->last_status,
            'last_attempt_at' => $p->last_attempt_at?->toISOString(),
            'last_error' => $p->last_error,
        ]);

        return Inertia::render('Settings', [
            'categories' => $categories,
            'prices' => $prices,
            'trashed' => $this->trashedItems(),
            'bankConnections' => $this->bankConnections(),
            'linkableAssets' => Asset::query()
                ->whereNull('ticker')
                ->whereDate('date', now()->format('Y-m-01'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Asset $a): array => ['id' => $a->id, 'name' => $a->name])
                ->all(),
            'banks' => $this->banks(),
        ]);
    }

    /** @return list<array{id: int, status: string, aspsp_name: string, valid_until: string|null, accounts: list<array{id: int, iban: string|null, name: string|null, asset_id: int|null}>}> */
    private function bankConnections(): array
    {
        $out = [];

        foreach (BankConnection::with('accounts')->latest()->get() as $c) {
            $accounts = [];
            foreach ($c->accounts as $a) {
                $accounts[] = [
                    'id' => $a->id,
                    'iban' => $a->iban,
                    'name' => $a->name,
                    'asset_id' => $a->asset_id,
                ];
            }

            $out[] = [
                'id' => $c->id,
                'status' => $c->isActive() ? 'active' : ($c->status === BankConnection::STATUS_PENDING ? 'pending' : 'expired'),
                'aspsp_name' => $c->aspsp_name,
                'valid_until' => $c->valid_until?->toISOString(),
                'accounts' => $accounts,
            ];
        }

        return $out;
    }

    /**
     * The connectable banks for the user's country, cached (they rarely change)
     * to avoid a live API call on every Settings load.
     *
     * @return list<array{name: string, country: string}>
     */
    private function banks(): array
    {
        /** @var list<array{name: string, country: string}> */
        return Cache::remember('enable_banking.aspsps.IT', now()->addDay(), function (): array {
            $out = [];
            foreach ($this->enableBanking->aspsps('IT') as $a) {
                $name = $a['name'] ?? null;
                if (is_string($name) && $name !== '') {
                    $out[] = [
                        'name' => $name,
                        'country' => is_string($a['country'] ?? null) ? $a['country'] : 'IT',
                    ];
                }
            }

            return $out;
        });
    }

    /** @return list<array{type: string, label: string, deleted_at: string|null, restore_url: string}> */
    private function trashedItems(): array
    {
        $items = [];

        foreach (Asset::onlyTrashed()->latest('deleted_at')->get() as $a) {
            $items[] = [
                'type' => 'Asset',
                'label' => $a->name,
                'deleted_at' => $a->deleted_at?->toISOString(),
                'restore_url' => route('assets.restore', $a->id, absolute: false),
            ];
        }

        foreach (Category::onlyTrashed()->latest('deleted_at')->get() as $c) {
            $items[] = [
                'type' => 'Categoria',
                'label' => $c->name,
                'deleted_at' => $c->deleted_at?->toISOString(),
                'restore_url' => route('categories.restore', $c->id, absolute: false),
            ];
        }

        foreach (Goal::onlyTrashed()->latest('deleted_at')->get() as $g) {
            $items[] = [
                'type' => 'Obiettivo',
                'label' => $g->name,
                'deleted_at' => $g->deleted_at?->toISOString(),
                'restore_url' => route('goal.restore', $g->id, absolute: false),
            ];
        }

        return $items;
    }
}
