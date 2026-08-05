<?php

declare(strict_types=1);

namespace App\Http\Controllers\Categories;

use App\Enums\MacroCategory;
use App\Http\Clients\EnableBankingClient;
use App\Http\Clients\ScalableCliClient;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\BankConnection;
use App\Models\Category;
use App\Models\Goal;
use App\Models\ScalableConnection;
use App\Services\Scalable\ScalableLoginState;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    public function __construct(
        private readonly EnableBankingClient $enableBanking,
        private readonly ScalableCliClient $scalableCli,
        private readonly ScalableLoginState $scalableLogin,
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
                'sort_order' => $c->sort_order,
                'macro_category' => $c->macro_category?->value,
                'investable' => $c->investable,
                'assets_count' => $c->assets_count,
            ]);

        // TER per ticker, from the assets that carry it (the price table itself
        // has no cost data). Keyed by ticker so the prices table can show it.
        $expenseByTicker = Asset::query()
            ->whereNotNull('ticker')
            ->whereNotNull('expense_ratio')
            ->orderByDesc('date')
            ->get(['ticker', 'expense_ratio'])
            ->keyBy('ticker')
            ->map(fn (Asset $a): float => (float) $a->expense_ratio);

        $prices = AssetPrice::orderBy('ticker')->get()->map(fn (AssetPrice $p) => [
            'ticker' => $p->ticker,
            'price' => $p->price,
            'currency' => $p->currency,
            'expense_ratio' => $expenseByTicker[$p->ticker] ?? null,
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
            'bankRedirectReady' => $this->bankRedirectReady(),
            'scalable' => $this->scalableState(),
            'linkableAssets' => Asset::query()
                ->whereNull('ticker')
                ->whereDate('date', now()->format('Y-m-01'))
                ->whereHas('category', fn ($q) => $q->where('macro_category', MacroCategory::Liquidita->value))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Asset $a): array => ['id' => $a->id, 'name' => $a->name])
                ->all(),
            'banks' => $this->banks(),
            'transactionAssets' => $this->transactionAssets(),
            'accountEmail' => Auth::user()?->email,
        ]);
    }

    /**
     * Assets whose quantity is managed by imported transactions, for the
     * Settings "unlink" list — the same role the bank-connections list plays.
     * One row per logical asset (latest dated row carrying transactions).
     *
     * @return list<array{id: int, name: string, quantity: float|null, transactions_count: int}>
     */
    private function transactionAssets(): array
    {
        $rows = Asset::query()
            ->has('transactions')
            ->withCount('transactions')
            ->orderByDesc('date')
            ->get()
            ->unique('isin')
            ->map(fn (Asset $a): array => [
                'id' => $a->id,
                'name' => $a->name,
                'quantity' => $a->quantity,
                'transactions_count' => (int) $a->transactions_count,
            ])
            ->all();

        return array_values($rows);
    }

    /**
     * State of the Scalable broker sync for the Settings card: whether the CLI
     * is enabled, its live session state, the outcome of the last sync, and any
     * in-flight in-app login. Null timestamps/status mean never synced yet.
     *
     * @return array{configured: bool, cli_logged_in: bool|null, last_sync_status: string|null, last_sync_error: string|null, last_sync_at: string|null, login: array{status: string, url: string|null, user_code: string|null, error: string|null, started_at: string|null}}
     */
    private function scalableState(): array
    {
        $connection = ScalableConnection::current();
        $cliEnabled = Config::boolean('services.scalable.cli.enabled', false);

        return [
            'configured' => $cliEnabled,
            // Live session check, cached briefly so rapid Settings reloads don't
            // each spawn a `sc whoami`.
            'cli_logged_in' => $cliEnabled
                ? Cache::remember('scalable.cli.logged_in', now()->addSeconds(30), fn (): bool => $this->scalableCli->isLoggedIn())
                : null,
            'last_sync_status' => $connection->last_sync_status,
            'last_sync_error' => $connection->last_sync_error,
            'last_sync_at' => $connection->last_sync_at?->toISOString(),
            'login' => $this->scalableLogin->snapshot(),
        ];
    }

    /**
     * Whether the configured consent redirect looks usable: an https URL on a
     * public host. A localhost/placeholder value means no tunnel is set up yet,
     * so starting a consent flow would fail — the UI warns about it.
     */
    private function bankRedirectReady(): bool
    {
        $url = Config::string('services.enable_banking.redirect_url', '');
        $host = parse_url($url, PHP_URL_HOST);

        return str_starts_with($url, 'https://')
            && is_string($host)
            && ! in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true);
    }

    /** @return list<array{id: int, status: string, aspsp_name: string, aspsp_country: string, valid_until: string|null, accounts: list<array{id: int, iban: string|null, name: string|null, linked_asset_id: int|null, linked_name: string|null, synced_at: string|null, last_sync_status: string|null, last_sync_error: string|null}>}> */
    private function bankConnections(): array
    {
        $currentMonth = now()->format('Y-m-01');
        $out = [];

        foreach (BankConnection::with('accounts')->latest()->get() as $c) {
            $accounts = [];
            foreach ($c->accounts as $a) {
                // Resolve the linked logical asset to its current-month row so the
                // dropdown shows the current selection and the last-sync time.
                $linkedAssetId = null;
                $syncedAt = null;
                if ($a->linked_name !== null && $a->linked_category_id !== null) {
                    $linkedRow = Asset::where('name', $a->linked_name)
                        ->where('category_id', $a->linked_category_id)
                        ->whereDate('date', $currentMonth)
                        ->first(['id', 'synced_at']);
                    $linkedAssetId = $linkedRow?->id;
                    $syncedAt = $linkedRow?->synced_at?->toISOString();
                }

                $accounts[] = [
                    'id' => $a->id,
                    'iban' => $a->iban,
                    'name' => $a->name,
                    'linked_asset_id' => $linkedAssetId,
                    'linked_name' => $a->linked_name,
                    'synced_at' => $syncedAt,
                    'last_sync_status' => $a->last_sync_status,
                    'last_sync_error' => $a->last_sync_error,
                ];
            }

            $out[] = [
                'id' => $c->id,
                'status' => $c->isActive() ? 'active' : ($c->status === BankConnection::STATUS_PENDING ? 'pending' : 'expired'),
                'aspsp_name' => $c->aspsp_name,
                'aspsp_country' => $c->aspsp_country,
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
