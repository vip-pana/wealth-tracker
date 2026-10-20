<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

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
     *
     * Whatever is loaded, AdvisorBoundaries::CLAUSES is prepended: the loadable
     * file sets the advisor's task and tone, the constant sets the limits. This
     * is the single chokepoint through which every system prompt passes, so a
     * customised or private prompt cannot ship without the boundary.
     */
    public static function load(string $name): string
    {
        return AdvisorBoundaries::CLAUSES."\n\n---\n\n".self::loadFile($name);
    }

    /**
     * The overridable part of the prompt: the tuned <name>.txt when present,
     * else the committed <name>.txt.example.
     */
    private static function loadFile(string $name): string
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
