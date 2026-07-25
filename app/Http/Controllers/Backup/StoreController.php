<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backup;

use App\Actions\Backup\CreateBackup;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class StoreController extends Controller
{
    public function __construct(
        private readonly CreateBackup $createBackup,
    ) {}

    public function __invoke(): RedirectResponse
    {
        try {
            $path = $this->createBackup->run();
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', 'Backup fallito: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Backup creato: '.basename($path));
    }
}
