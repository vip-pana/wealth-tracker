<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backup;

use App\Actions\Backup\CreateBackup;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Throwable;

class StoreController extends Controller
{
    public function __construct(
        private readonly CreateBackup $createBackup,
    ) {}

    public function __invoke(): RedirectResponse
    {
        try {
            $artifact = $this->createBackup->run();
        } catch (Throwable $e) {
            return redirect()->back()->with('error', 'Backup fallito: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Backup creato: '.basename($artifact));
    }
}
