<?php

declare(strict_types=1);

namespace App\Advisor;

use RuntimeException;

class AdvisorPrompt
{
    /**
     * Load an advisor system prompt by name from resources/advisor-prompts.
     *
     * The real, tuned prompts (<name>.txt) are gitignored so they stay private.
     * A committed <name>.txt.example is used as a fallback, so a fresh clone
     * runs with a working baseline advisor out of the box.
     */
    public static function load(string $name): string
    {
        $base = resource_path('advisor-prompts/'.$name.'.txt');

        foreach ([$base, $base.'.example'] as $path) {
            if (is_file($path)) {
                return trim((string) file_get_contents($path));
            }
        }

        throw new RuntimeException("Advisor prompt '{$name}' not found (looked for {$base} and {$base}.example).");
    }
}
