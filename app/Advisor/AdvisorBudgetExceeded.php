<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Advisor;

/**
 * The advisor was asked to spend tokens it no longer has. Carries a message
 * written for the user, not for a log: every surface that catches this shows
 * getMessage() verbatim, so the person reading it learns which cap was hit and
 * when it frees up rather than seeing "something went wrong".
 *
 * Distinct from the provider's generic RuntimeException on purpose — the chat
 * paths catch that one and report "the model didn't answer", which would be a
 * lie here and would send the owner off checking a model that is working fine.
 */
class AdvisorBudgetExceeded extends \RuntimeException {}
