<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'undo' => fn () => $request->session()->get('undo'),
            ],
            // The bell shows only unread notifications (read = dismissed), most
            // recent first. Lazy closures so the query runs once per response.
            'notifications' => fn () => Notification::query()
                ->unread()
                ->latest('id')
                ->get()
                ->map(fn (Notification $n) => [
                    'id' => $n->id,
                    'type' => $n->type,
                    'level' => $n->level,
                    'title' => $n->title,
                    'body' => $n->body,
                    'action_url' => $n->action_url,
                    'created_at' => $n->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
