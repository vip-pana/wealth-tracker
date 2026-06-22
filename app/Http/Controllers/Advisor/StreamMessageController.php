<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Actions\Advisor\ContinueChat;
use App\Contracts\AdvisorProvider;
use App\Http\Controllers\Controller;
use App\Models\AdvisorSession;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamMessageController extends Controller
{
    public function __construct(
        private readonly AdvisorProvider $provider,
        private readonly ContinueChat $continueChat,
    ) {}

    public function __invoke(Request $request, AdvisorSession $session): StreamedResponse
    {
        abort_unless($this->provider->isConfigured(), 422, 'Consulente AI non configurato.');

        /** @var array{message: string} $data */
        $data = $request->validate([
            'message' => 'required|string|max:4000',
        ]);

        return response()->stream(function () use ($session, $data): void {
            try {
                $this->continueChat->runStreaming($session, $data['message'], function (string $chunk): void {
                    echo $chunk;
                    // Push each delta to the client immediately rather than
                    // buffering the whole reply.
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                });
            } catch (\Throwable) {
                // The reply failed mid-stream; emit a short marker the client
                // shows as an error. Nothing is persisted (no orphan turn).
                echo "\n\u{26A0} Il consulente non ha risposto. Riprova.";
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no', // disable proxy buffering so chunks flow
        ]);
    }
}
